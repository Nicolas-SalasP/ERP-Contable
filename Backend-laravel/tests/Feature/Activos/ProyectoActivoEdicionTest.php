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

class ProyectoActivoEdicionTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Empresa Proyectos', 'regimen_tributario' => '14_A']);
        $this->usuario = User::create(['nombre' => 'Jefe Proyectos', 'email' => 'proy@erp.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $this->rolSuperAdmin->id, 'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id]);
    }

    public function test_editar_proyecto_en_construccion_actualiza_datos_correctamente()
    {
        $proyecto = ProyectoActivo::create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Nombre Viejo',
            'estado' => 'EN_CONSTRUCCION',
            'valor_total_original' => 0,
            'vida_util_meses' => 60
        ]);

        $response = $this->actingAs($this->usuario)->putJson("/api/activos/proyectos/{$proyecto->id_proyecto}", [
            'nombre' => 'Nombre Nuevo Corregido',
            'vida_util_meses' => 120
        ]);

        $response->assertStatus(200);

        $proyecto->refresh();
        $this->assertEquals('Nombre Nuevo Corregido', $proyecto->nombre);
        $this->assertEquals(120, $proyecto->vida_util_meses);
    }

    public function test_rechaza_editar_proyecto_que_ya_fue_capitalizado_y_esta_operativo()
    {
        $proyecto = ProyectoActivo::create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Planta Industrial',
            'estado' => 'ACTIVO_OPERATIVO',
            'valor_total_original' => 1000000,
            'vida_util_meses' => 60
        ]);

        $response = $this->actingAs($this->usuario)->putJson("/api/activos/proyectos/{$proyecto->id_proyecto}", [
            'nombre' => 'Intento de Hackeo'
        ]);

        $this->assertTrue(in_array($response->getStatusCode(), [400, 422, 403]));
        $this->assertEquals('Planta Industrial', $proyecto->fresh()->nombre, "El sistema permitió editar un proyecto ya cerrado.");
    }

    public function test_editar_proyecto_ajeno_retorna_error_404_por_seguridad()
    {
        $empresaAjena = Empresa::create(['rut' => '88.888.888-8', 'razon_social' => 'Otra']);
        $proyectoAjeno = ProyectoActivo::create([
            'empresa_id' => $empresaAjena->id,
            'nombre' => 'Aje',
            'estado' => 'EN_CONSTRUCCION',
            'valor_total_original' => 0,
            'vida_util_meses' => 60
        ]);

        $response = $this->actingAs($this->usuario)->putJson("/api/activos/proyectos/{$proyectoAjeno->id_proyecto}", [
            'nombre' => 'Mio Ahora',
            'vida_util_meses' => 120
        ]);

        $response->assertStatus(404);
    }
}