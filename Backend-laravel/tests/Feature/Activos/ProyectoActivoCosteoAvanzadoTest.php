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

class ProyectoActivoCosteoAvanzadoTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Costeo SPA', 'regimen_tributario' => '14_A']);
        $this->usuario = User::create(['nombre' => 'Ingeniero', 'email' => 'ing@erp.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $this->rolSuperAdmin->id, 'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id]);
    }

    public function test_rechaza_imputaciones_con_diferencia_decimal_mayor_a_un_centavo()
    {
        $proyecto = ProyectoActivo::create(['empresa_id' => $this->empresa->id, 'nombre' => 'Planta', 'estado' => 'EN_CONSTRUCCION', 'valor_total_original' => 0, 'vida_util_meses' => 60]);
        $prov = Proveedor::create(['empresa_id' => $this->empresa->id, 'codigo_interno' => 'P1', 'razon_social' => 'P1', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);

        $factura = Factura::create(['empresa_id' => $this->empresa->id, 'codigo_unico' => 11, 'proveedor_id' => $prov->id, 'numero_factura' => 'F-1', 'monto_neto' => 100.50, 'monto_iva' => 0, 'monto_bruto' => 100.50, 'tipo' => 'COMPRA', 'fecha_emision' => now()]);

        $response = $this->actingAs($this->usuario)->postJson("/api/activos/proyectos/{$proyecto->id_proyecto}/facturas", [
            'factura_id' => $factura->id,
            'monto' => 100.52
        ]);

        $response->assertStatus(422)
            ->assertSee('Monto Incorrecto');
    }

    public function test_permite_imputacion_con_diferencia_de_redondeo_minima()
    {
        $proyecto = ProyectoActivo::create(['empresa_id' => $this->empresa->id, 'nombre' => 'Planta 2', 'estado' => 'EN_CONSTRUCCION', 'valor_total_original' => 0, 'vida_util_meses' => 60]);
        $prov = Proveedor::create(['empresa_id' => $this->empresa->id, 'codigo_interno' => 'P1', 'razon_social' => 'P1', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);

        $factura = Factura::create(['empresa_id' => $this->empresa->id, 'codigo_unico' => 22, 'proveedor_id' => $prov->id, 'numero_factura' => 'F-2', 'monto_neto' => 100.50, 'monto_iva' => 0, 'monto_bruto' => 100.50, 'tipo' => 'COMPRA', 'fecha_emision' => now()]);

        $response = $this->actingAs($this->usuario)->postJson("/api/activos/proyectos/{$proyecto->id_proyecto}/facturas", [
            'factura_id' => $factura->id,
            'monto' => 100.50
        ]);

        $response->assertStatus(200);
        $this->assertEquals(100.50, $proyecto->fresh()->valor_total_original);
    }

    public function test_rechaza_imputacion_de_montos_negativos()
    {
        $proyecto = ProyectoActivo::create(['empresa_id' => $this->empresa->id, 'nombre' => 'P3', 'estado' => 'EN_CONSTRUCCION', 'valor_total_original' => 0, 'vida_util_meses' => 60]);
        $prov = Proveedor::create(['empresa_id' => $this->empresa->id, 'codigo_interno' => 'P1', 'razon_social' => 'P1', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        $factura = Factura::create(['empresa_id' => $this->empresa->id, 'codigo_unico' => 33, 'proveedor_id' => $prov->id, 'numero_factura' => 'F-3', 'monto_neto' => 100, 'monto_iva' => 0, 'monto_bruto' => 100, 'tipo' => 'COMPRA', 'fecha_emision' => now()]);

        $response = $this->actingAs($this->usuario)->postJson("/api/activos/proyectos/{$proyecto->id_proyecto}/facturas", [
            'factura_id' => $factura->id,
            'monto' => -100
        ]);

        $response->assertStatus(422);
    }
}