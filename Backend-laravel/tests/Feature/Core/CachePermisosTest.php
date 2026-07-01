<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Domains\Core\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * Verifica que el middleware EnsureUserHasPermission cachea los permisos efectivos
 * del usuario y que la cache se invalida al cambiar el rol.
 */
class CachePermisosTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    /** El formato de clave de cache incluye userId y empresaActivaId. */
    public function test_cache_key_contiene_usuario_y_empresa(): void
    {
        $key = EnsureUserHasPermission::cacheKeyPermisos(42, 7);

        $this->assertSame('permisos_u42_e7', $key);
    }

    /** Los permisos se cachean: dos requests usan el mismo valor sin repetir cómputo. */
    public function test_permisos_se_cachean_en_segunda_llamada(): void
    {
        $empresa = $this->crearEmpresa();
        $usuario = $this->crearUsuario($empresa, $this->rolContador);

        Sanctum::actingAs($usuario);

        // Primer request: llena la cache.
        $r1 = $this->getJson('/api/contabilidad/plan-cuentas');
        $r1->assertStatus(200);

        $cacheKey = EnsureUserHasPermission::cacheKeyPermisos(
            (int) $usuario->id,
            (int) ($usuario->empresa_activa_id ?? 0)
        );

        // La cache debe existir tras el primer request.
        $this->assertTrue(Cache::has($cacheKey), 'La clave de cache de permisos debe existir tras el primer request.');

        // Segundo request: debe seguir funcionando con la cache.
        $r2 = $this->getJson('/api/contabilidad/plan-cuentas');
        $r2->assertStatus(200);
    }

    /** Al cambiar el rol, la clave de cache del usuario afectado se elimina. */
    public function test_cache_se_invalida_al_cambiar_rol(): void
    {
        $empresa = $this->crearEmpresa();

        // Admin que realiza el cambio.
        $admin = $this->crearUsuario($empresa, $this->rolSuperAdmin);

        // Usuario al que se le cambiará el rol.
        $objetivo = $this->crearUsuario($empresa, $this->rolContador);

        $cacheKey = EnsureUserHasPermission::cacheKeyPermisos(
            (int) $objetivo->id,
            (int) ($objetivo->empresa_activa_id ?? 0)
        );

        // Precarga la cache manualmente para simular un request previo.
        Cache::put($cacheKey, ['contabilidad.ver'], now()->addMinutes(5));
        $this->assertTrue(Cache::has($cacheKey), 'La cache debe existir antes del cambio de rol.');

        // El admin cambia el rol del objetivo.
        Sanctum::actingAs($admin);
        $response = $this->putJson("/api/usuarios/{$objetivo->id}/rol", [
            'rol_id' => $this->rolAuditor->id,
        ]);
        $response->assertStatus(200);

        // La cache debe haber sido invalidada.
        $this->assertFalse(Cache::has($cacheKey), 'La cache de permisos debe eliminarse al cambiar el rol.');
    }

    /** Un usuario sin permisos recibe 403, no 200 (sanity check del middleware con cache). */
    public function test_usuario_sin_permisos_recibe_403(): void
    {
        $empresa = $this->crearEmpresa();
        $usuario = $this->crearUsuario($empresa, $this->rolUsuarioBasico);

        Sanctum::actingAs($usuario);

        // rolUsuarioBasico no tiene permisos, por lo que rutas protegidas deben rechazar.
        $response = $this->getJson('/api/contabilidad/plan-cuentas');
        $response->assertStatus(403);
    }
}
