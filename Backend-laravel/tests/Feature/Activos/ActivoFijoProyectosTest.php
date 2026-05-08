<?php

namespace Tests\Feature\Activos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Core\Models\Pais;
use App\Domains\Activos\Models\ProyectoActivo;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Contabilidad\Models\PlanCuenta;

class ActivoFijoProyectosTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;
    protected $cuentaActivo;
    protected $cuentaDepre;
    protected $cuentaGasto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Empresa Test', 'regimen_tributario' => '14_D3', 'tasa_impuesto' => 25.00]);
        $this->usuario = User::create(['nombre' => 'QA', 'email' => 'qa@erp.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $this->rolSuperAdmin->id, 'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id]);

        $this->cuentaActivo = PlanCuenta::create(['empresa_id' => $this->empresa->id, 'codigo' => '112105', 'nombre' => 'Maquinarias', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);
        $this->cuentaDepre = PlanCuenta::create(['empresa_id' => $this->empresa->id, 'codigo' => '112106', 'nombre' => 'Dep Acum Maq', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);
        $this->cuentaGasto = PlanCuenta::create(['empresa_id' => $this->empresa->id, 'codigo' => '609102', 'nombre' => 'Gasto Deprec Maq', 'tipo' => 'GASTO', 'imputable' => true, 'activo' => true]);
    }

    public function test_crear_nuevo_proyecto_asigna_estado_en_construccion_por_defecto()
    {
        $response = $this->actingAs($this->usuario)->postJson('/api/activos/proyectos', [
            'nombre' => 'Bodega Nueva',
            'vida_util_meses' => 240,
            'anio_fabricacion' => (int) date('Y'),
            'tipo_activo_id' => $this->cuentaActivo->id,
            'cuenta_depreciacion_id' => $this->cuentaDepre->id,
            'cuenta_gasto_id' => $this->cuentaGasto->id
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('proyectos_activos', [
            'nombre' => 'Bodega Nueva',
            'estado' => 'EN_CONSTRUCCION'
        ]);
    }

    public function test_flujo_completo_imputar_costos_y_capitalizar_proyecto()
    {
        $proyecto = ProyectoActivo::create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Edificio Nueva Sucursal',
            'estado' => 'EN_CONSTRUCCION',
            'valor_total_original' => 0,
            'vida_util_meses' => 120,
            'tipo_activo_id' => $this->cuentaActivo->id,
            'cuenta_depreciacion_id' => $this->cuentaDepre->id,
            'cuenta_gasto_id' => $this->cuentaGasto->id,
        ]);

        $prov = Proveedor::create(['empresa_id' => $this->empresa->id, 'codigo_interno' => 'P1', 'razon_social' => 'Constructor', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        $factura = Factura::create(['empresa_id' => $this->empresa->id, 'codigo_unico' => 123456, 'proveedor_id' => $prov->id, 'numero_factura' => 'F-111', 'fecha_emision' => now(), 'monto_neto' => 500000, 'monto_iva' => 0, 'monto_bruto' => 500000, 'tipo' => 'COMPRA']);

        $this->actingAs($this->usuario)->postJson("/api/activos/proyectos/{$proyecto->id_proyecto}/facturas", ['factura_id' => $factura->id, 'monto' => 500000])->assertStatus(200);

        $this->assertEquals(500000, $proyecto->fresh()->valor_total_original);
        $this->assertEquals($proyecto->id_proyecto, $factura->fresh()->proyecto_activo_id);

        $this->actingAs($this->usuario)->putJson("/api/activos/proyectos/{$proyecto->id_proyecto}/activar")->assertStatus(200);

        $this->assertDatabaseHas('activos_fijos', [
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Edificio Nueva Sucursal',
            'valor_adquisicion' => 500000,
            'estado' => 'ACTIVO'
        ]);
    }

    public function test_capitalizacion_consolida_multiples_facturas_exactamente()
    {
        $proyecto = ProyectoActivo::create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Ensamblaje Servidor',
            'estado' => 'EN_CONSTRUCCION',
            'valor_total_original' => 0,
            'vida_util_meses' => 48,
            'tipo_activo_id' => $this->cuentaActivo->id,
            'cuenta_depreciacion_id' => $this->cuentaDepre->id,
            'cuenta_gasto_id' => $this->cuentaGasto->id,
        ]);

        $prov = Proveedor::create(['empresa_id' => $this->empresa->id, 'codigo_interno' => 'P-SRV', 'razon_social' => 'Tech', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        $f1 = Factura::create(['empresa_id' => $this->empresa->id, 'codigo_unico' => 111, 'proveedor_id' => $prov->id, 'numero_factura' => 'F-1', 'monto_neto' => 100000, 'monto_iva' => 0, 'monto_bruto' => 100000, 'tipo' => 'COMPRA', 'fecha_emision' => now()]);
        $f2 = Factura::create(['empresa_id' => $this->empresa->id, 'codigo_unico' => 222, 'proveedor_id' => $prov->id, 'numero_factura' => 'F-2', 'monto_neto' => 250000, 'monto_iva' => 0, 'monto_bruto' => 250000, 'tipo' => 'COMPRA', 'fecha_emision' => now()]);

        $this->actingAs($this->usuario)->postJson("/api/activos/proyectos/{$proyecto->id_proyecto}/facturas", ['factura_id' => $f1->id, 'monto' => 100000]);
        $this->actingAs($this->usuario)->postJson("/api/activos/proyectos/{$proyecto->id_proyecto}/facturas", ['factura_id' => $f2->id, 'monto' => 250000]);

        $this->actingAs($this->usuario)->putJson("/api/activos/proyectos/{$proyecto->id_proyecto}/activar")->assertStatus(200);

        $this->assertDatabaseHas('activos_fijos', ['nombre' => 'Ensamblaje Servidor', 'valor_adquisicion' => 350000, 'estado' => 'ACTIVO']);
    }

    public function test_activar_proyecto_dos_veces_lanza_excepcion()
    {
        $proyecto = ProyectoActivo::create(['empresa_id' => $this->empresa->id, 'nombre' => 'Proyecto Ya Activo', 'tipo_activo_id' => $this->cuentaActivo->id, 'cuenta_depreciacion_id' => $this->cuentaDepre->id, 'cuenta_gasto_id' => $this->cuentaGasto->id, 'valor_total_original' => 500000, 'estado' => 'ACTIVO_OPERATIVO']);
        $response = $this->actingAs($this->usuario)->putJson("/api/activos/proyectos/{$proyecto->id_proyecto}/activar");
        $response->assertStatus(400)->assertSee('ya se encuentra activo u operativo');
    }

    public function test_imputar_monto_superior_al_neto_de_factura_falla()
    {
        $proyecto = ProyectoActivo::create(['empresa_id' => $this->empresa->id, 'nombre' => 'Maquinaria', 'estado' => 'EN_CONSTRUCCION', 'valor_total_original' => 0, 'vida_util_meses' => 120]);
        $prov = Proveedor::create(['empresa_id' => $this->empresa->id, 'codigo_interno' => 'P2', 'razon_social' => 'Prov Test', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        $factura = Factura::create(['empresa_id' => $this->empresa->id, 'codigo_unico' => 999888, 'proveedor_id' => $prov->id, 'numero_factura' => 'F-100', 'fecha_emision' => now(), 'monto_neto' => 50000, 'monto_iva' => 9500, 'monto_bruto' => 59500, 'tipo' => 'COMPRA', 'estado' => 'REGISTRADA']);

        $response = $this->actingAs($this->usuario)->postJson("/api/activos/proyectos/{$proyecto->id_proyecto}/facturas", ['factura_id' => $factura->id, 'monto' => 500000]);
        $response->assertStatus(422);
        $this->assertEquals(0, $proyecto->fresh()->valor_total_original);
    }

    public function test_activar_proyecto_con_costo_cero_lanza_error()
    {
        $proyecto = ProyectoActivo::create(['empresa_id' => $this->empresa->id, 'nombre' => 'Proyecto Vacío', 'estado' => 'EN_CONSTRUCCION', 'valor_total_original' => 0, 'vida_util_meses' => 60, 'tipo_activo_id' => $this->cuentaActivo->id, 'cuenta_depreciacion_id' => $this->cuentaDepre->id, 'cuenta_gasto_id' => $this->cuentaGasto->id]);
        $response = $this->actingAs($this->usuario)->putJson("/api/activos/proyectos/{$proyecto->id_proyecto}/activar");
        $response->assertStatus(400);
        $this->assertDatabaseMissing('activos_fijos', ['nombre' => 'Proyecto Vacío']);
    }

    public function test_no_se_pueden_imputar_facturas_a_proyectos_ya_activados()
    {
        $proyectoCerrado = ProyectoActivo::create(['empresa_id' => $this->empresa->id, 'nombre' => 'Edificio Terminado', 'estado' => 'ACTIVO_OPERATIVO', 'valor_total_original' => 1000000, 'vida_util_meses' => 120]);
        $prov = Proveedor::create(['empresa_id' => $this->empresa->id, 'codigo_interno' => 'P-ZMB', 'razon_social' => 'Prov', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        $facturaAtrasada = Factura::create(['empresa_id' => $this->empresa->id, 'codigo_unico' => 333, 'proveedor_id' => $prov->id, 'numero_factura' => 'F-ATRASADA', 'monto_neto' => 50000, 'monto_iva' => 0, 'monto_bruto' => 50000, 'tipo' => 'COMPRA', 'estado' => 'REGISTRADA', 'fecha_emision' => now()]);

        $response = $this->actingAs($this->usuario)->postJson("/api/activos/proyectos/{$proyectoCerrado->id_proyecto}/facturas", ['factura_id' => $facturaAtrasada->id, 'monto' => 50000]);
        $response->assertStatus(422)->assertSee('cerrado');
    }

    public function test_evita_imputar_la_misma_factura_dos_veces_al_mismo_proyecto()
    {
        $proyecto = ProyectoActivo::create(['empresa_id' => $this->empresa->id, 'nombre' => 'Software a Medida', 'estado' => 'EN_CONSTRUCCION', 'valor_total_original' => 0, 'vida_util_meses' => 36]);
        $prov = Proveedor::create(['empresa_id' => $this->empresa->id, 'codigo_interno' => 'P-DEV', 'razon_social' => 'Devs', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        $factura = Factura::create(['empresa_id' => $this->empresa->id, 'codigo_unico' => 444, 'proveedor_id' => $prov->id, 'numero_factura' => 'F-DOBLE', 'monto_neto' => 800000, 'monto_iva' => 0, 'monto_bruto' => 800000, 'tipo' => 'COMPRA', 'estado' => 'REGISTRADA', 'fecha_emision' => now()]);

        $this->actingAs($this->usuario)->postJson("/api/activos/proyectos/{$proyecto->id_proyecto}/facturas", ['factura_id' => $factura->id, 'monto' => 800000])->assertStatus(200);
        $response = $this->actingAs($this->usuario)->postJson("/api/activos/proyectos/{$proyecto->id_proyecto}/facturas", ['factura_id' => $factura->id, 'monto' => 800000]);
        $response->assertStatus(422)->assertSee('vinculada a otro proyecto');
    }
}