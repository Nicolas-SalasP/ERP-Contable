<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class EmpresaCambioTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function vincularEmpresa(int $userId, int $empresaId, int $rolId): void
    {
        DB::table('empresa_user')->insert([
            'user_id'    => $userId,
            'empresa_id' => $empresaId,
            'rol_id'     => $rolId,
            'created_at' => now(),
        ]);
    }

    // -----------------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------------

    public function test_mis_empresas_devuelve_lista_con_empresa_activa_marcada(): void
    {
        [$empresaA, $usuario] = $this->crearEmpresaConAdmin();
        $empresaB = $this->crearEmpresa();

        // Vincular usuario a ambas empresas en el pivote
        $this->vincularEmpresa($usuario->id, $empresaA->id, $this->rolAdministrador->id);
        $this->vincularEmpresa($usuario->id, $empresaB->id, $this->rolContador->id);

        // La empresa activa del usuario es A
        $this->assertEquals($empresaA->id, $usuario->empresa_activa_id);

        $response = $this->actingAs($usuario)->getJson('/api/empresa/mis-empresas');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(2, $data);

        // La empresa A debe estar marcada como activa, la B no
        $itemA = collect($data)->firstWhere('id', $empresaA->id);
        $itemB = collect($data)->firstWhere('id', $empresaB->id);

        $this->assertNotNull($itemA, 'Empresa A no está en la lista');
        $this->assertNotNull($itemB, 'Empresa B no está en la lista');
        $this->assertTrue($itemA['activa'], 'Empresa A debe tener activa=true');
        $this->assertFalse($itemB['activa'], 'Empresa B debe tener activa=false');
    }

    public function test_cambiar_empresa_exitoso_actualiza_activa_y_rol(): void
    {
        [$empresaA, $usuario] = $this->crearEmpresaConAdmin();
        $empresaB = $this->crearEmpresa();

        // Habilitar plan multitenant
        $usuario->update(['module_keys' => ['multitenant', 'contabilidad']]);

        // Vincular usuario a ambas empresas; en B tiene rol Contador
        $this->vincularEmpresa($usuario->id, $empresaA->id, $this->rolAdministrador->id);
        $this->vincularEmpresa($usuario->id, $empresaB->id, $this->rolContador->id);

        $response = $this->actingAs($usuario)->postJson('/api/empresa/cambiar', [
            'empresa_id' => $empresaB->id,
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['message' => 'Empresa activa actualizada']);
        $response->assertJsonPath('data.empresa_activa_id', $empresaB->id);
        $response->assertJsonPath('data.rol_id', $this->rolContador->id);
        $response->assertJsonPath('data.empresa.id', $empresaB->id);

        // Verificar cambio persistido en base de datos
        $usuario->refresh();
        $this->assertEquals($empresaB->id, $usuario->empresa_activa_id);
        $this->assertEquals($this->rolContador->id, $usuario->rol_id);
    }

    public function test_cambiar_empresa_sin_plan_multitenant_devuelve_403(): void
    {
        [$empresaA, $usuario] = $this->crearEmpresaConAdmin();
        $empresaB = $this->crearEmpresa();

        // Sin módulo multitenant (module_keys vacío o sin la clave)
        $usuario->update(['module_keys' => ['contabilidad']]);

        $this->vincularEmpresa($usuario->id, $empresaA->id, $this->rolAdministrador->id);
        $this->vincularEmpresa($usuario->id, $empresaB->id, $this->rolContador->id);

        $response = $this->actingAs($usuario)->postJson('/api/empresa/cambiar', [
            'empresa_id' => $empresaB->id,
        ]);

        $response->assertStatus(403);
        $response->assertJsonFragment(['code' => 'PLAN_NO_MULTITENANT']);
    }

    public function test_onboarding_inserta_fila_en_empresa_user(): void
    {
        // Regresión (auditoría 2026-07-07, crítico #1): empresa_user nunca se escribía en
        // ningún flujo real -- el onboarding dejaba al usuario con acceso "real" vía
        // empresa_id pero invisible para EmpresaCambioController::misEmpresas, rompiendo
        // multiempresa para toda empresa creada después de la migración del pivote.
        $usuario = \App\Domains\Core\Models\User::create([
            'nombre'                => 'Onboarding Test',
            'email'                 => 'pivote-onboarding@test.cl',
            'password'              => bcrypt('password123'),
            'empresa_id'            => null,
            'rol_id'                => $this->rolAdministrador->id,
            'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id,
        ]);

        $numero = 15222222;
        $rutValido = $numero . '-' . \App\Domains\Sii\Support\RutHelper::calcularDv($numero);

        Sanctum::actingAs($usuario);
        $response = $this->postJson('/api/empresas/onboarding', [
            'empresa_rut'          => $rutValido,
            'empresa_razon_social' => 'Empresa Pivote Test',
        ]);

        $response->assertOk();
        $usuario->refresh();

        $this->assertDatabaseHas('empresa_user', [
            'user_id'    => $usuario->id,
            'empresa_id' => $usuario->empresa_id,
            'rol_id'     => $this->rolAdministrador->id,
        ]);
    }

    public function test_invitar_usuario_inserta_fila_en_empresa_user(): void
    {
        [$empresaA, $admin] = $this->crearEmpresaConAdmin();

        app(\App\Domains\Core\Services\UsuarioService::class)
            ->invitarUsuario($empresaA->id, 'invitado-pivote@test.cl', $this->rolContador->id);

        $usuarioInvitado = \App\Domains\Core\Models\User::where('email', 'invitado-pivote@test.cl')->firstOrFail();

        $this->assertDatabaseHas('empresa_user', [
            'user_id'    => $usuarioInvitado->id,
            'empresa_id' => $empresaA->id,
            'rol_id'     => $this->rolContador->id,
        ]);
    }

    public function test_cambiar_empresa_ajena_devuelve_403(): void
    {
        [$empresaA, $usuario] = $this->crearEmpresaConAdmin();

        // Empresa que existe pero a la que el usuario NO pertenece
        $empresaAjena = $this->crearEmpresa();

        // Habilitar plan multitenant
        $usuario->update(['module_keys' => ['multitenant', 'contabilidad']]);

        // Solo vincular la empresa A, nunca la ajena
        $this->vincularEmpresa($usuario->id, $empresaA->id, $this->rolAdministrador->id);

        $response = $this->actingAs($usuario)->postJson('/api/empresa/cambiar', [
            'empresa_id' => $empresaAjena->id,
        ]);

        $response->assertStatus(403);
        $response->assertJsonFragment(['code' => 'EMPRESA_NO_AUTORIZADA']);
    }
}
