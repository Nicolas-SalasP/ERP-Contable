<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * Tests focalizados de gestion de roles y permisos por jerarquia.
 *
 * El sistema implementa una jerarquia numerica donde un rol con
 * jerarquia mas baja no puede tocar a uno de jerarquia mas alta.
 * Estos tests validan que esa regla se respete en todos los flujos.
 */
class RolesYPermisosTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empresa = $this->crearEmpresa();
    }

    public function test_listado_de_roles_devuelve_todos_los_roles_del_sistema()
    {
        $usuario = $this->crearUsuario($this->empresa, $this->rolSuperAdmin);

        Sanctum::actingAs($usuario);
        $response = $this->getJson('/api/usuarios/roles');

        $response->assertStatus(200);
        $body = $response->json();
        $roles = $body['data'] ?? $body;
        $this->assertIsArray($roles);
        $this->assertGreaterThanOrEqual(5, count($roles)); // Super Admin, Admin, Contador, Auditor, Usuario
    }

    public function test_crear_rol_con_permisos_array_valido()
    {
        $admin = $this->crearUsuario($this->empresa, $this->rolSuperAdmin);

        Sanctum::actingAs($admin);
        $response = $this->postJson('/api/usuarios/roles', [
            'nombre' => 'Vendedor Junior',
            'jerarquia' => 20,
            'permisos' => ['ventas.ver', 'clientes.ver'],
        ]);

        $this->assertContains($response->getStatusCode(), [200, 201]);

        $rol = Rol::where('nombre', 'Vendedor Junior')->first();
        $this->assertNotNull($rol);
        $this->assertIsArray($rol->permisos);
        $this->assertContains('ventas.ver', $rol->permisos);
    }

    public function test_jerarquia_de_rol_es_numero_entero_positivo()
    {
        $rol = Rol::create([
            'nombre' => 'Test Rol Jerarquia',
            'jerarquia' => 50,
            'permisos' => [],
        ]);

        $this->assertIsInt($rol->jerarquia);
        $this->assertGreaterThan(0, $rol->jerarquia);
    }

    public function test_solo_super_admin_jerarquia_100_puede_invitar_usuarios()
    {
        // Diseno actual: el sistema solo permite invitar si jerarquia >= 100.
        // Admin (jerarquia 80) NO puede invitar, solo Super Admin si.
        $admin = $this->crearUsuario($this->empresa, $this->rolAdministrador); // 80
        $superAdmin = $this->crearUsuario($this->empresa, $this->rolSuperAdmin, [
            'email' => 'superadm@test.cl',
        ]);

        // Admin no puede
        Sanctum::actingAs($admin);
        $r1 = $this->postJson('/api/usuarios/invitar', [
            'email' => 'nuevo1@test.cl',
            'rol_id' => $this->rolUsuarioBasico->id,
        ]);
        $r1->assertStatus(403);

        // Super Admin si puede
        Sanctum::actingAs($superAdmin);
        $r2 = $this->postJson('/api/usuarios/invitar', [
            'email' => 'nuevo2@test.cl',
            'rol_id' => $this->rolUsuarioBasico->id,
        ]);
        $this->assertContains($r2->getStatusCode(), [200, 201]);
    }

    public function test_admin_no_puede_actualizar_rol_de_super_admin()
    {
        $admin = $this->crearUsuario($this->empresa, $this->rolAdministrador);
        $superAdmin = $this->crearUsuario($this->empresa, $this->rolSuperAdmin, [
            'email' => 'super@test.cl',
        ]);

        Sanctum::actingAs($admin);
        $response = $this->putJson("/api/usuarios/{$superAdmin->id}/rol", [
            'rol_id' => $this->rolUsuarioBasico->id, // bajarlo a usuario basico
        ]);

        $this->assertContains($response->getStatusCode(), [400, 403, 422]);

        // Validar que el super admin sigue con su rol original
        $this->assertEquals($this->rolSuperAdmin->id, $superAdmin->fresh()->rol_id);
    }

    public function test_usuario_basico_no_puede_acceder_a_endpoints_de_admin()
    {
        $basico = $this->crearUsuario($this->empresa, $this->rolUsuarioBasico);
        $otroUsuario = $this->crearUsuario($this->empresa, $this->rolUsuarioBasico, [
            'email' => 'otro@test.cl',
        ]);

        Sanctum::actingAs($basico);

        // Intentar invitar (solo admin)
        $invitar = $this->postJson('/api/usuarios/invitar', [
            'email' => 'nuevo-malo@test.cl',
            'rol_id' => $this->rolUsuarioBasico->id,
        ]);
        $this->assertContains($invitar->getStatusCode(), [400, 403, 422]);

        // Intentar desvincular a otro usuario
        $desvincular = $this->deleteJson("/api/usuarios/{$otroUsuario->id}");
        $this->assertContains($desvincular->getStatusCode(), [400, 403, 422]);
    }

    public function test_rol_inexistente_al_invitar_es_rechazado()
    {
        $admin = $this->crearUsuario($this->empresa, $this->rolSuperAdmin);

        Sanctum::actingAs($admin);
        $response = $this->postJson('/api/usuarios/invitar', [
            'email' => 'test@test.cl',
            'rol_id' => 99999, // no existe
        ]);

        $this->assertContains($response->getStatusCode(), [400, 422, 500]);
    }
}
