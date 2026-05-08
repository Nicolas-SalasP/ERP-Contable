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
use App\Domains\Contabilidad\Models\PlanCuenta;

class ActivoFijoValidacionTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Empresa Test', 'regimen_tributario' => '14_D3', 'tasa_impuesto' => 25.00]);
        $this->usuario = User::create(['nombre' => 'QA', 'email' => 'qa@erp.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $this->rolSuperAdmin->id, 'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id]);
    }

    public function test_rechaza_creacion_de_activo_sin_campos_obligatorios()
    {
        $response = $this->actingAs($this->usuario)->postJson('/api/activos', []);
        $response->assertStatus(422)->assertJsonValidationErrors(['nombre', 'valor_adquisicion', 'fecha_adquisicion', 'vida_util_meses']);
    }

    public function test_rechaza_creacion_con_datos_negativos_incompletos_o_maliciosos()
    {
        $response = $this->actingAs($this->usuario)->postJson('/api/activos', [
            'nombre' => '<script>alert("xss")</script>',
            'valor_adquisicion' => -50000,
            'fecha_adquisicion' => 'fecha-inventada',
            'vida_util_meses' => 0
        ]);
        $response->assertStatus(422)->assertJsonValidationErrors(['valor_adquisicion', 'fecha_adquisicion', 'vida_util_meses']);
    }

    public function test_rechaza_activo_con_valor_residual_mayor_o_igual_a_adquisicion()
    {
        $response = $this->actingAs($this->usuario)->postJson('/api/activos', [
            'nombre' => 'Escritorio Mágico',
            'valor_adquisicion' => 50000,
            'valor_residual' => 100000,
            'fecha_adquisicion' => now()->format('Y-m-d'),
            'vida_util_meses' => 12
        ]);
        $response->assertStatus(422)->assertSee('El valor residual no puede ser mayor o igual');
    }

    public function test_depreciacion_rechaza_formatos_de_fecha_maliciosos()
    {
        $payloadsErroneos = ['2026-13', '2026/05', 'drop table activos;', '26-05'];
        foreach ($payloadsErroneos as $fechaMala) {
            $response = $this->actingAs($this->usuario)->postJson('/api/activos/depreciar-mes', ['mes_anio' => $fechaMala]);
            $response->assertStatus(422)->assertJsonValidationErrors(['mes_anio']);
        }
    }

    public function test_autogeneracion_de_codigo_correlativo_al_crear_activo()
    {
        $response1 = $this->actingAs($this->usuario)->postJson('/api/activos', [
            'nombre' => 'Mesa',
            'valor_adquisicion' => 150000,
            'fecha_adquisicion' => now()->format('Y-m-d'),
            'vida_util_meses' => 60
        ]);
        $response1->assertStatus(201);
        $this->assertStringStartsWith('AF-', $response1->json('data.codigo'));

        $response2 = $this->actingAs($this->usuario)->postJson('/api/activos', [
            'nombre' => 'Silla',
            'valor_adquisicion' => 80000,
            'fecha_adquisicion' => now()->format('Y-m-d'),
            'vida_util_meses' => 36
        ]);
        $this->assertNotEquals($response1->json('data.codigo'), $response2->json('data.codigo'));
    }

    public function test_endpoint_parametros_clasifica_bien_las_cuentas_contables()
    {
        PlanCuenta::insert([
            ['empresa_id' => $this->empresa->id, 'codigo' => '100', 'nombre' => 'Vehículos', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true],
            ['empresa_id' => $this->empresa->id, 'codigo' => '500', 'nombre' => 'Gasto por Depreciación', 'tipo' => 'GASTO', 'imputable' => true, 'activo' => true],
        ]);

        $response = $this->actingAs($this->usuario)->getJson('/api/activos/parametros');
        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('data.cuentas_activo'));
        $this->assertNotEmpty($response->json('data.cuentas_gasto'));
    }
}