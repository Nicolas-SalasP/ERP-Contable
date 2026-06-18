<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;
use App\Domains\Core\Models\PoliticaPrivacidad;
use App\Domains\Core\Models\Consentimiento;
use App\Domains\Rrhh\Models\Empleado;

class PrivacidadTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        // Ensure active policy exists.
        // RefreshDatabase re-runs migrations, so the seeded v1.0 row may already
        // exist. Use firstOrCreate to avoid a UNIQUE violation on 'version'.
        PoliticaPrivacidad::firstOrCreate(
            ['version' => '1.0'],
            [
                'titulo'        => 'Política de Privacidad',
                'contenido'     => 'Contenido de prueba.',
                'vigente_desde' => now()->toDateString(),
                'activa'        => true,
            ]
        );
    }

    public function test_politica_activa_es_devuelta(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();

        $response = $this->actingAs($admin)->getJson('/api/privacidad/politica');
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.version', '1.0');
    }

    public function test_sin_politica_activa_devuelve_404(): void
    {
        PoliticaPrivacidad::query()->update(['activa' => false]);
        [$empresa, $admin] = $this->crearEmpresaConAdmin();

        $response = $this->actingAs($admin)->getJson('/api/privacidad/politica');
        $response->assertStatus(404);
    }

    public function test_mi_consentimiento_sin_aceptar_devuelve_false(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();

        $response = $this->actingAs($admin)->getJson('/api/privacidad/mi-consentimiento');
        $response->assertStatus(200);
        $response->assertJsonPath('data.aceptada', false);
    }

    public function test_aceptar_registra_consentimiento_con_version_activa(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();

        $response = $this->actingAs($admin)->postJson('/api/privacidad/consentimiento');
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('consentimientos', [
            'titular_type'     => get_class($admin),
            'titular_id'       => $admin->id,
            'politica_version' => '1.0',
            'otorgado'         => true,
        ]);
    }

    public function test_mi_consentimiento_despues_de_aceptar_devuelve_true(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();

        $this->actingAs($admin)->postJson('/api/privacidad/consentimiento');

        $response = $this->actingAs($admin)->getJson('/api/privacidad/mi-consentimiento');
        $response->assertStatus(200);
        $response->assertJsonPath('data.aceptada', true);
        $response->assertJsonPath('data.version', '1.0');
    }

    public function test_aceptar_es_idempotente(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();

        $this->actingAs($admin)->postJson('/api/privacidad/consentimiento');
        $this->actingAs($admin)->postJson('/api/privacidad/consentimiento');

        $count = Consentimiento::where('titular_type', get_class($admin))
            ->where('titular_id', $admin->id)
            ->where('politica_version', '1.0')
            ->count();
        $this->assertEquals(1, $count);
    }

    public function test_revocar_establece_otorgado_false(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();

        $this->actingAs($admin)->postJson('/api/privacidad/consentimiento');

        $response = $this->actingAs($admin)->deleteJson('/api/privacidad/consentimiento');
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('consentimientos', [
            'titular_type' => get_class($admin),
            'titular_id'   => $admin->id,
            'otorgado'     => false,
        ]);
    }

    public function test_crear_empleado_genera_consentimiento_laboral(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();

        $response = $this->actingAs($admin)->postJson('/api/rrhh/empleados', [
            'rut'              => '15.234.567-8',
            'nombres'          => 'Juan',
            'apellido_paterno' => 'Pérez',
            'afp'              => 'Modelo',
            'tipo_salud'       => 'FONASA',
        ]);
        $response->assertStatus(201);

        $empleadoId = $response->json('data.id');

        $this->assertDatabaseHas('consentimientos', [
            'titular_type' => Empleado::class,
            'titular_id'   => $empleadoId,
            'base_licitud' => 'ejecucion_contrato',
            'finalidad'    => 'gestion_laboral_y_remuneraciones',
            'otorgado'     => true,
        ]);
    }

    public function test_admin_puede_crear_nueva_version_politica(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();

        $response = $this->actingAs($admin)->postJson('/api/privacidad/politica', [
            'version'   => '2.0',
            'titulo'    => 'Política v2',
            'contenido' => 'Nuevo contenido de política.',
        ]);
        $response->assertStatus(201);
        $response->assertJsonPath('data.version', '2.0');

        // Old version deactivated
        $this->assertDatabaseHas('politicas_privacidad', [
            'version' => '1.0',
            'activa'  => false,
        ]);
        // New version active
        $this->assertDatabaseHas('politicas_privacidad', [
            'version' => '2.0',
            'activa'  => true,
        ]);
    }

    public function test_no_admin_no_puede_crear_politica(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $usuario = $this->crearUsuario($empresa, $this->rolUsuarioBasico);

        $response = $this->actingAs($usuario)->postJson('/api/privacidad/politica', [
            'version'   => '2.0',
            'titulo'    => 'Política v2',
            'contenido' => 'Contenido.',
        ]);
        $response->assertStatus(403);
    }
}
