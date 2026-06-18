<?php

namespace Tests\Feature\Rrhh;

use App\Domains\Rrhh\Models\Empleado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Verifica el contrato HTTP del módulo RRHH y el RBAC por permiso.
 */
class RrhhApiTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    public function test_admin_puede_crear_y_listar_empleados(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();

        $crear = $this->actingAs($admin)->postJson('/api/rrhh/empleados', [
            'rut' => '15.234.567-8',
            'nombres' => 'María',
            'apellido_paterno' => 'López',
            'afp' => 'Modelo',
            'tipo_salud' => 'FONASA',
            'banco_numero_cuenta' => '99988877',
        ]);

        $crear->assertStatus(201);
        $crear->assertJsonPath('success', true);

        // El número de cuenta cifrado no debe aparecer en la respuesta
        $crear->assertJsonMissingPath('data.banco_numero_cuenta_cifrado');

        $listar = $this->actingAs($admin)->getJson('/api/rrhh/empleados');
        $listar->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $listar->json('data.total'));
    }

    public function test_usuario_sin_permiso_no_accede_a_empleados(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $usuarioBasico = $this->crearUsuario($empresa, $this->rolUsuarioBasico, [
            'email' => 'basico@test.cl',
        ]);

        $response = $this->actingAs($usuarioBasico)->getJson('/api/rrhh/empleados');
        $response->assertStatus(403);
    }

    public function test_no_puede_ver_empleado_de_otra_empresa_idor(): void
    {
        [$empresaA, $adminA] = $this->crearEmpresaConAdmin([], ['email' => 'a@test.cl']);
        [$empresaB] = $this->crearEmpresaConAdmin([], ['email' => 'b@test.cl']);

        $empleadoB = Empleado::create([
            'empresa_id' => $empresaB->id,
            'rut' => '20.111.222-3',
            'nombres' => 'Secreto',
            'apellido_paterno' => 'B',
        ]);

        $response = $this->actingAs($adminA)->getJson("/api/rrhh/empleados/{$empleadoB->id}");
        $this->assertContains($response->getStatusCode(), [403, 404]);
    }
}
