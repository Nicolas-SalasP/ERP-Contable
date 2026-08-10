<?php

namespace Tests\Feature\Integraciones;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Integraciones\Services\IntegracionApiKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/** Cobertura del CRUD admin de API-keys (Etapa 4): permisos gateados por module_keys, token en claro solo al emitir/rotar, aislamiento multitenant. */
class IntegracionApiKeyAdminTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    private function usuarioConModulo(Empresa $empresa): User
    {
        return $this->crearUsuario($empresa, $this->rolAdministrador, [
            'module_keys' => ['integraciones.api'],
        ]);
    }

    public function test_usuario_con_modulo_puede_emitir_una_key(): void
    {
        $empresa = $this->crearEmpresa();
        Sanctum::actingAs($this->usuarioConModulo($empresa));

        $respuesta = $this->postJson('/api/integraciones/admin/keys', [
            'nombre' => 'Tenri Web',
            'scopes' => ['inventario:leer'],
        ]);

        $respuesta->assertCreated()->assertJsonStructure(['data' => ['key', 'token']]);
        $this->assertStringStartsWith('tnri_', $respuesta->json('data.token'));
    }

    public function test_usuario_con_modulo_puede_emitir_una_key_con_scope_ventas_escribir(): void
    {
        $empresa = $this->crearEmpresa();
        Sanctum::actingAs($this->usuarioConModulo($empresa));

        $respuesta = $this->postJson('/api/integraciones/admin/keys', [
            'nombre' => 'Tenri Web (checkout)',
            'scopes' => ['inventario:leer', 'ventas:escribir'],
        ]);

        $respuesta->assertCreated();
        $this->assertEquals(['inventario:leer', 'ventas:escribir'], $respuesta->json('data.key.scopes'));
    }

    public function test_usuario_sin_modulo_no_puede_gestionar_keys(): void
    {
        $empresa = $this->crearEmpresa();
        // module_keys vacio -> 'integraciones.api' no habilitado por el plan.
        $usuario = $this->crearUsuario($empresa, $this->rolAdministrador, ['module_keys' => []]);
        Sanctum::actingAs($usuario);

        $this->postJson('/api/integraciones/admin/keys', [
            'nombre' => 'Tenri Web',
            'scopes' => ['inventario:leer'],
        ])->assertForbidden();

        $this->getJson('/api/integraciones/admin/keys')->assertForbidden();
    }

    public function test_index_no_expone_el_token_en_claro(): void
    {
        $empresa = $this->crearEmpresa();
        $usuario = $this->usuarioConModulo($empresa);
        Sanctum::actingAs($usuario);

        app(IntegracionApiKeyService::class)->emitir($empresa->id, 'Key existente', ['inventario:leer']);

        $respuesta = $this->getJson('/api/integraciones/admin/keys');

        $respuesta->assertOk();
        $this->assertArrayNotHasKey('token', $respuesta->json('data.0'));
        $this->assertArrayNotHasKey('token_hash', $respuesta->json('data.0'));
    }

    public function test_rotar_devuelve_token_nuevo_e_invalida_el_anterior(): void
    {
        $empresa = $this->crearEmpresa();
        Sanctum::actingAs($this->usuarioConModulo($empresa));

        $emitida = app(IntegracionApiKeyService::class)->emitir($empresa->id, 'Key', ['inventario:leer']);

        $respuesta = $this->postJson("/api/integraciones/admin/keys/{$emitida['key']->id}/rotar");

        $respuesta->assertOk();
        $this->assertNotSame($emitida['token'], $respuesta->json('data.token'));

        $this->withHeaders(['Authorization' => 'Bearer '.$emitida['token']])
            ->getJson('/api/integraciones/v1/ping')
            ->assertUnauthorized();
    }

    public function test_destroy_revoca_la_key(): void
    {
        $empresa = $this->crearEmpresa();
        Sanctum::actingAs($this->usuarioConModulo($empresa));

        $emitida = app(IntegracionApiKeyService::class)->emitir($empresa->id, 'Key', ['inventario:leer']);

        $this->deleteJson("/api/integraciones/admin/keys/{$emitida['key']->id}")->assertOk();

        $this->assertNotNull($emitida['key']->fresh()->revocada_at);
    }

    public function test_no_puede_gestionar_key_de_otra_empresa(): void
    {
        $empresaA = $this->crearEmpresa();
        $empresaB = $this->crearEmpresa();
        Sanctum::actingAs($this->usuarioConModulo($empresaA));

        $emitidaB = app(IntegracionApiKeyService::class)->emitir($empresaB->id, 'Key B', ['inventario:leer']);

        $this->postJson("/api/integraciones/admin/keys/{$emitidaB['key']->id}/rotar")->assertNotFound();
        $this->deleteJson("/api/integraciones/admin/keys/{$emitidaB['key']->id}")->assertNotFound();
        $this->assertNull($emitidaB['key']->fresh()->revocada_at);
    }

    public function test_scopes_invalidos_son_rechazados(): void
    {
        $empresa = $this->crearEmpresa();
        Sanctum::actingAs($this->usuarioConModulo($empresa));

        $this->postJson('/api/integraciones/admin/keys', [
            'nombre' => 'Key',
            'scopes' => ['scope-inventado'],
        ])->assertUnprocessable();
    }

    public function test_index_devuelve_paginacion(): void
    {
        $empresa = $this->crearEmpresa();
        Sanctum::actingAs($this->usuarioConModulo($empresa));

        app(IntegracionApiKeyService::class)->emitir($empresa->id, 'Key 1', ['inventario:leer']);
        app(IntegracionApiKeyService::class)->emitir($empresa->id, 'Key 2', ['inventario:leer']);

        $respuesta = $this->getJson('/api/integraciones/admin/keys');

        $respuesta->assertOk()->assertJsonStructure(['data', 'pagination' => ['total', 'total_pages', 'page']]);
        $this->assertSame(2, $respuesta->json('pagination.total'));
    }

    public function test_suscripcion_en_solo_lectura_no_puede_emitir_keys(): void
    {
        $empresa = $this->crearEmpresa();
        $usuario = $this->crearUsuario($empresa, $this->rolAdministrador, [
            'module_keys' => ['integraciones.api'],
            'subscription_status' => 'expired',
        ]);
        Sanctum::actingAs($usuario);

        $this->postJson('/api/integraciones/admin/keys', [
            'nombre' => 'Key',
            'scopes' => ['inventario:leer'],
        ])->assertStatus(403);
    }

    public function test_suscripcion_en_solo_lectura_no_puede_rotar_ni_revocar(): void
    {
        $empresa = $this->crearEmpresa();
        $usuario = $this->crearUsuario($empresa, $this->rolAdministrador, [
            'module_keys' => ['integraciones.api'],
        ]);
        Sanctum::actingAs($usuario);

        $emitida = app(IntegracionApiKeyService::class)->emitir($empresa->id, 'Key', ['inventario:leer']);

        $usuario->update(['subscription_status' => 'read_only']);

        $this->postJson("/api/integraciones/admin/keys/{$emitida['key']->id}/rotar")->assertStatus(403);
        $this->deleteJson("/api/integraciones/admin/keys/{$emitida['key']->id}")->assertStatus(403);
    }

    public function test_suscripcion_en_solo_lectura_igual_puede_listar_keys(): void
    {
        $empresa = $this->crearEmpresa();
        $usuario = $this->crearUsuario($empresa, $this->rolAdministrador, [
            'module_keys' => ['integraciones.api'],
            'subscription_status' => 'expired',
        ]);
        Sanctum::actingAs($usuario);

        $this->getJson('/api/integraciones/admin/keys')->assertOk();
    }
}
