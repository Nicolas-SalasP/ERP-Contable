<?php

namespace Tests\Feature\Activos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Activos\Models\ActivoFijo;
use App\Domains\Activos\Models\ProyectoActivo;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Core\Models\Pais;

class ActivoFijoSeguridadTest extends TestCase
{
    use RefreshDatabase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        EstadoSuscripcion::create(['id' => 1, 'nombre' => 'Activa']);
        $rol = Rol::create(['id' => 1, 'nombre' => 'Admin', 'jerarquia' => 100]);

        Pais::create(['iso' => 'CL', 'nombre' => 'Chile', 'moneda_defecto' => 'CLP', 'etiqueta_id' => 'RUT', 'activo' => true]);

        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Empresa Test QA', 'regimen_tributario' => '14_D3', 'tasa_impuesto' => 25.00]);
        $this->usuario = User::create(['nombre' => 'QA Tester', 'email' => 'qa@erp.cl', 'password' => bcrypt('password123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $rol->id, 'estado_suscripcion_id' => 1]);
    }

    public function test_usuario_no_autenticado_es_rechazado()
    {
        $response = $this->getJson('/api/activos');
        $response->assertStatus(401)->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_aislamiento_de_datos_multitenant_evita_fuga_de_informacion()
    {
        $empresaHacker = Empresa::create(['rut' => '66.666.666-6', 'razon_social' => 'Hacker SpA']);
        $hacker = User::create(['nombre' => 'Hacker', 'email' => 'hacker@erp.cl', 'password' => bcrypt('123'), 'empresa_id' => $empresaHacker->id, 'rol_id' => 1, 'estado_suscripcion_id' => 1]);

        ActivoFijo::create(['empresa_id' => $this->empresa->id, 'codigo' => 'AF-00001', 'nombre' => 'Servidor Confidencial', 'valor_adquisicion' => 1000000, 'fecha_adquisicion' => now(), 'vida_util_meses' => 60]);

        $response = $this->actingAs($hacker)->getJson('/api/activos');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'), "FALLO DE SEGURIDAD: Un usuario vio activos de otra empresa.");
    }

    public function test_analisis_proyecto_ajeno_retorna_404_por_seguridad()
    {
        $empresaAjena = Empresa::create(['rut' => '99.999.999-9', 'razon_social' => 'Empresa Enemiga']);
        $proyectoAjeno = ProyectoActivo::create(['empresa_id' => $empresaAjena->id, 'nombre' => 'Secreto Industrial', 'estado' => 'EN_CONSTRUCCION', 'valor_total_original' => 1000000, 'vida_util_meses' => 60]);

        $response = $this->actingAs($this->usuario)->getJson("/api/activos/proyectos/{$proyectoAjeno->id_proyecto}/analisis");
        $response->assertStatus(404)->assertJsonPath('success', false);
    }

    public function test_imputar_factura_de_otra_empresa_lanza_excepcion_y_falla()
    {
        $miProyecto = ProyectoActivo::create(['empresa_id' => $this->empresa->id, 'nombre' => 'Mi Construcción', 'estado' => 'EN_CONSTRUCCION', 'valor_total_original' => 0, 'vida_util_meses' => 120]);
        $empresaAjena = Empresa::create(['rut' => '88.888.888-8', 'razon_social' => 'Empresa B']);
        $provAjeno = Proveedor::create(['empresa_id' => $empresaAjena->id, 'codigo_interno' => 'P9', 'razon_social' => 'Prov', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);

        $facturaAjena = Factura::create(['empresa_id' => $empresaAjena->id, 'codigo_unico' => 555444, 'proveedor_id' => $provAjeno->id, 'numero_factura' => 'F-SECRETA', 'monto_neto' => 10000000, 'monto_iva' => 1900000, 'monto_bruto' => 11900000, 'tipo' => 'COMPRA', 'fecha_emision' => now()]);

        $response = $this->actingAs($this->usuario)->postJson("/api/activos/proyectos/{$miProyecto->id_proyecto}/facturas", [
            'factura_id' => $facturaAjena->id,
            'monto' => 10000000
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false)->assertSee('no existe o no pertenece a su empresa');
    }

    public function test_listados_generales_estan_aislados_por_empresa()
    {
        $empresaAjena = Empresa::create(['rut' => '33.333.333-3', 'razon_social' => 'Empresa Fantasma']);
        ActivoFijo::create(['empresa_id' => $empresaAjena->id, 'codigo' => 'AF-AJENO', 'nombre' => 'Secreto', 'valor_adquisicion' => 100, 'fecha_adquisicion' => now(), 'vida_util_meses' => 10, 'estado' => 'ACTIVO']);
        ProyectoActivo::create(['empresa_id' => $empresaAjena->id, 'nombre' => 'Proyecto Fantasma', 'estado' => 'EN_CONSTRUCCION', 'valor_total_original' => 100, 'vida_util_meses' => 10]);

        $this->assertCount(0, $this->actingAs($this->usuario)->getJson('/api/activos')->json('data'));
        $this->assertCount(0, $this->actingAs($this->usuario)->getJson('/api/activos/pendientes')->json('data'));
        $this->assertCount(0, $this->actingAs($this->usuario)->getJson('/api/activos/proyectos')->json('data'));
    }

    public function test_vendedor_no_puede_ejecutar_operaciones_contables_criticas()
    {
        $rolVendedor = Rol::create(['id' => 2, 'nombre' => 'Vendedor', 'jerarquia' => 10]);
        $vendedor = User::create(['nombre' => 'Ventas', 'email' => 'ventas@erp.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $rolVendedor->id, 'estado_suscripcion_id' => 1]);

        $response = $this->actingAs($vendedor)->postJson('/api/activos/depreciar-mes', ['mes_anio' => now()->format('Y-m')]);
        $response->assertStatus(403)->assertSee('Acceso denegado');
    }
}