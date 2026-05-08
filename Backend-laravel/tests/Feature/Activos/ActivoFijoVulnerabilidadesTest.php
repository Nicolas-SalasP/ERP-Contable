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

class ActivoFijoVulnerabilidadesTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Empresa Segura', 'regimen_tributario' => '14_A']);
        $this->usuario = User::create(['nombre' => 'Hacker QA', 'email' => 'hack@erp.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $this->rolSuperAdmin->id, 'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id]);
    }

    public function test_rechaza_peticiones_con_tipos_de_datos_manipulados_type_juggling()
    {
        $response = $this->actingAs($this->usuario)->postJson('/api/activos', [
            'nombre' => ['esto_es_un_array_malicioso'],
            'valor_adquisicion' => 100000,
            'fecha_adquisicion' => now()->format('Y-m-d'),
            'vida_util_meses' => 60
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nombre']);
    }

    public function test_rechaza_inyeccion_sql_en_parametros_de_ruta()
    {
        $idMalicioso = "1' OR '1'='1";
        $response = $this->actingAs($this->usuario)->getJson("/api/activos/proyectos/{$idMalicioso}/analisis");

        $this->assertTrue(in_array($response->getStatusCode(), [404, 400, 422]));
    }

    public function test_proteccion_contra_method_spoofing()
    {
        $response = $this->actingAs($this->usuario)->postJson('/api/activos/proyectos/1/activar');

        $response->assertStatus(405);
    }

    public function test_rutas_de_activos_estan_protegidas_por_middleware_auth()
    {
        $res1 = $this->getJson('/api/activos');
        $res2 = $this->postJson('/api/activos', []);

        $res1->assertStatus(401);
        $res2->assertStatus(401);
    }
}