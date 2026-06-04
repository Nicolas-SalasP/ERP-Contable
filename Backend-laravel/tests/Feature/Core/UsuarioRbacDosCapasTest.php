<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * H15 — RBAC en dos capas de UsuarioController:
 *   Capa 1 (gate de ruta permiso:usuarios.ver|gestionar): ¿puede gestionar usuarios?
 *   Capa 2 (lógica relativa del controller): ¿puede operar sobre ESTE usuario?
 *
 * Estas pruebas cubren la frontera entre capas: que el gate filtre por capacidad
 * y que la lógica relativa siga activa cuando el gate ya dejó pasar.
 */
class UsuarioRbacDosCapasTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    public function test_capa1_gate_bloquea_lectura_a_rol_sin_usuarios_ver(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $contador = $this->crearUsuario($empresa, $this->rolContador); // jerarquía 50, sin usuarios.ver
        Sanctum::actingAs($contador);

        $this->getJson('/api/usuarios')
            ->assertStatus(403)
            ->assertJsonStructure(['errors' => ['permiso']]);
    }

    public function test_capa1_separa_lectura_de_escritura(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $auditor = $this->crearUsuario($empresa, $this->rolAuditor); // tiene usuarios.ver, NO usuarios.gestionar
        Sanctum::actingAs($auditor);

        // Lectura: el gate de usuarios.ver pasa.
        $this->getJson('/api/usuarios')->assertStatus(200);

        // Escritura: el gate de usuarios.gestionar bloquea.
        $this->postJson('/api/usuarios/invitar', ['email' => 'x@y.cl', 'rol_id' => $this->rolUsuarioBasico->id])
            ->assertStatus(403)
            ->assertJsonStructure(['errors' => ['permiso']]);
    }

    public function test_capa2_sigue_activa_cuando_el_gate_pasa(): void
    {
        // Administrador (80) tiene usuarios.gestionar -> pasa el gate. Pero la lógica
        // relativa le impide tocar a un Super Admin (100).
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $superior = $this->crearUsuario($empresa, $this->rolSuperAdmin);
        Sanctum::actingAs($admin);

        $this->putJson("/api/usuarios/{$superior->id}/rol", ['rol_id' => $this->rolContador->id])
            ->assertStatus(403)
            ->assertJson(['message' => 'No puedes editar usuarios de jerarquía igual o superior.']);
    }

    public function test_administrador_gestiona_subordinado_pasa_ambas_capas(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $subordinado = $this->crearUsuario($empresa, $this->rolContador); // jerarquía 50 < 80
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/usuarios/{$subordinado->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('usuarios', ['id' => $subordinado->id]);
    }

    public function test_invitar_sigue_siendo_solo_super_admin_pese_al_gate(): void
    {
        // El Administrador (80) pasa el gate (tiene usuarios.gestionar) pero la regla
        // interna de invitar exige jerarquía 100. Semántica preservada.
        [, $admin] = $this->crearEmpresaConAdmin();
        Sanctum::actingAs($admin);

        $this->postJson('/api/usuarios/invitar', ['email' => 'nuevo@y.cl', 'rol_id' => $this->rolUsuarioBasico->id])
            ->assertStatus(403)
            ->assertJson(['message' => 'No tienes permisos para invitar usuarios.']);
    }
}
