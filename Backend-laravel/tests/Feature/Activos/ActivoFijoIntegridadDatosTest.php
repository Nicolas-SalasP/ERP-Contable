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
use App\Domains\Contabilidad\Models\PlanCuenta;

class ActivoFijoIntegridadDatosTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Empresa Integridad', 'regimen_tributario' => '14_A']);
        $this->usuario = User::create(['nombre' => 'Data Quality', 'email' => 'dq@erp.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $this->rolSuperAdmin->id, 'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id]);
    }

    public function test_activar_proyecto_cuyas_cuentas_fueron_borradas_falla_por_integridad()
    {
        $cuentaA = PlanCuenta::create(['empresa_id' => $this->empresa->id, 'codigo' => '100', 'nombre' => 'A', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);
        $cuentaD = PlanCuenta::create(['empresa_id' => $this->empresa->id, 'codigo' => '101', 'nombre' => 'D', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);
        $cuentaG = PlanCuenta::create(['empresa_id' => $this->empresa->id, 'codigo' => '102', 'nombre' => 'G', 'tipo' => 'GASTO', 'imputable' => true, 'activo' => true]);

        $proyecto = ProyectoActivo::create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Proyecto Trampa',
            'estado' => 'EN_CONSTRUCCION',
            'valor_total_original' => 1000,
            'vida_util_meses' => 60,
            'tipo_activo_id' => $cuentaA->id,
            'cuenta_depreciacion_id' => $cuentaD->id,
            'cuenta_gasto_id' => $cuentaG->id
        ]);

        $cuentaG->delete();

        $response = $this->actingAs($this->usuario)->putJson("/api/activos/proyectos/{$proyecto->id_proyecto}/activar");

        $response->assertStatus(400)
            ->assertSee('ya no existen en el plan de cuentas');
    }

    public function test_rutas_con_ids_inexistentes_retornan_error_404_limpio()
    {
        $res1 = $this->actingAs($this->usuario)->getJson('/api/activos/proyectos/999999/analisis');
        $res2 = $this->actingAs($this->usuario)->putJson('/api/activos/proyectos/999999/activar');
        $res3 = $this->actingAs($this->usuario)->putJson('/api/activos/999999/baja');

        $res1->assertStatus(404);
        $this->assertTrue(in_array($res2->getStatusCode(), [404, 400]));
        $this->assertTrue(in_array($res3->getStatusCode(), [404, 400]));
    }
}