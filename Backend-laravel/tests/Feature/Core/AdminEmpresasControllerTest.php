<?php

namespace Tests\Feature\Core;

use App\Support\HmacFirma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Cobertura de AdminEmpresasController (`internal/web/empresas*`,
 * `internal/web/usuarios*`), protegido por el middleware `web.api.key`
 * (VerifyWebApiKey -> HmacFirma::verifica), no por auth:sanctum. Son los
 * endpoints que el panel interno de Tenri usa para suspender/activar
 * empresas y bloquear/desbloquear usuarios — antes sin ningun test Feature
 * pese a ser acciones administrativas con impacto directo en el acceso
 * de un tenant completo.
 */
class AdminEmpresasControllerTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    private const SECRETO = 'secreto-admin-empresas-test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        config(['services.tenri_web.web_integration_key' => self::SECRETO]);
    }

    /** Headers HMAC validos para una request GET via getJson() (TestCase::json() serializa [] como "[]"). */
    private function firmarGet(): array
    {
        return HmacFirma::headers(self::SECRETO, json_encode([]));
    }

    /** Headers HMAC validos para una request con cuerpo JSON. */
    private function firmarConCuerpo(array $payload): array
    {
        return HmacFirma::headers(self::SECRETO, json_encode($payload));
    }

    // -------------------------------------------------------------------
    // Sin firma HMAC valida -> rechazado
    // -------------------------------------------------------------------

    public function test_index_sin_firma_hmac_es_rechazado(): void
    {
        $this->getJson('/api/internal/web/empresas')->assertStatus(401);
    }

    public function test_suspender_sin_firma_hmac_es_rechazado(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $this->postJson("/api/internal/web/empresas/{$empresa->id}/suspender")->assertStatus(401);

        $this->assertDatabaseHas('empresas', ['id' => $empresa->id, 'activa' => true]);
    }

    public function test_bloquear_usuario_sin_firma_hmac_es_rechazado(): void
    {
        [, $usuario] = $this->crearEmpresaConAdmin();

        $this->postJson("/api/internal/web/usuarios/{$usuario->id}/bloquear")->assertStatus(401);

        $this->assertDatabaseHas('usuarios', ['id' => $usuario->id, 'bloqueado_hasta' => null]);
    }

    // -------------------------------------------------------------------
    // Con firma valida -> lecturas
    // -------------------------------------------------------------------

    public function test_index_lista_empresas_con_conteo_de_usuarios(): void
    {
        [$empresa, ] = $this->crearEmpresaConAdmin();
        $this->crearUsuario($empresa);

        $response = $this->getJson('/api/internal/web/empresas', $this->firmarGet());

        $response->assertStatus(200);
        $empresas = collect($response->json('empresas'));
        $fila = $empresas->firstWhere('id', $empresa->id);

        $this->assertNotNull($fila);
        $this->assertSame(2, $fila['usuarios_count']);
    }

    public function test_show_retorna_empresa_y_sus_usuarios(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();

        $response = $this->getJson("/api/internal/web/empresas/{$empresa->id}", $this->firmarGet());

        $response->assertStatus(200)
            ->assertJsonPath('empresa.id', $empresa->id)
            ->assertJsonPath('empresa.usuarios.0.id', $usuario->id);
    }

    public function test_show_empresa_inexistente_retorna_404(): void
    {
        $response = $this->getJson('/api/internal/web/empresas/999999', $this->firmarGet());

        $response->assertStatus(404);
    }

    public function test_usuarios_lista_todos_los_usuarios_de_todas_las_empresas(): void
    {
        [$empresaA, $adminA] = $this->crearEmpresaConAdmin();
        [$empresaB, $adminB] = $this->crearEmpresaConAdmin();

        $response = $this->getJson('/api/internal/web/usuarios', $this->firmarGet());

        $response->assertStatus(200);
        $ids = collect($response->json('usuarios'))->pluck('id');
        $this->assertTrue($ids->contains($adminA->id));
        $this->assertTrue($ids->contains($adminB->id));
    }

    // -------------------------------------------------------------------
    // Suspender / activar empresa
    // -------------------------------------------------------------------

    public function test_suspender_empresa_la_marca_inactiva_y_revoca_tokens(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $token = $usuario->createToken('test')->plainTextToken;
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $response = $this->postJson(
            "/api/internal/web/empresas/{$empresa->id}/suspender",
            [],
            $this->firmarConCuerpo([])
        );

        $response->assertStatus(200)->assertJsonPath('empresa.activa', false);
        $this->assertDatabaseHas('empresas', ['id' => $empresa->id, 'activa' => false]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_activar_empresa_la_marca_activa(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin(['activa' => false]);

        $response = $this->postJson(
            "/api/internal/web/empresas/{$empresa->id}/activar",
            [],
            $this->firmarConCuerpo([])
        );

        $response->assertStatus(200)->assertJsonPath('empresa.activa', true);
        $this->assertDatabaseHas('empresas', ['id' => $empresa->id, 'activa' => true]);
    }

    public function test_suspender_empresa_inexistente_retorna_404(): void
    {
        $response = $this->postJson(
            '/api/internal/web/empresas/999999/suspender',
            [],
            $this->firmarConCuerpo([])
        );

        $response->assertStatus(404);
    }

    /**
     * Regresion multiempresa: un usuario con empresa_id=A y empresa_activa_id=B
     * debe perder el token al suspender B, aunque B no sea su empresa "hogar".
     */
    public function test_suspender_empresa_revoca_token_de_usuario_con_empresa_activa_distinta_de_empresa_hogar(): void
    {
        [$empresaA] = $this->crearEmpresaConAdmin();
        [$empresaB] = $this->crearEmpresaConAdmin();

        $usuario = $this->crearUsuario($empresaA, null, ['empresa_activa_id' => $empresaB->id]);
        $usuario->createToken('test');
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $response = $this->postJson(
            "/api/internal/web/empresas/{$empresaB->id}/suspender",
            [],
            $this->firmarConCuerpo([])
        );

        $response->assertStatus(200)->assertJsonPath('empresa.activa', false);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * Simetrico: suspender la empresa "hogar" (A) tambien debe revocar el token
     * del usuario aunque este operando activamente en otra empresa (B).
     */
    public function test_suspender_empresa_hogar_revoca_token_aunque_empresa_activa_sea_otra(): void
    {
        [$empresaA] = $this->crearEmpresaConAdmin();
        [$empresaB] = $this->crearEmpresaConAdmin();

        $usuario = $this->crearUsuario($empresaA, null, ['empresa_activa_id' => $empresaB->id]);
        $usuario->createToken('test');
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $response = $this->postJson(
            "/api/internal/web/empresas/{$empresaA->id}/suspender",
            [],
            $this->firmarConCuerpo([])
        );

        $response->assertStatus(200)->assertJsonPath('empresa.activa', false);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    // -------------------------------------------------------------------
    // Cambiar plan
    // -------------------------------------------------------------------

    public function test_cambiar_plan_actualiza_plan_slug_y_module_keys_de_todos_los_usuarios_de_la_empresa(): void
    {
        [$empresa, $adminUno] = $this->crearEmpresaConAdmin();
        $otroUsuario = $this->crearUsuario($empresa);

        $payload = ['plan_slug' => 'erp-pyme-pro', 'module_keys' => ['clientes', 'rrhh']];

        $response = $this->putJson(
            "/api/internal/web/empresas/{$empresa->id}/plan",
            $payload,
            $this->firmarConCuerpo($payload)
        );

        $response->assertStatus(200)->assertJsonPath('updated', 2);
        $this->assertDatabaseHas('usuarios', ['id' => $adminUno->id, 'plan_slug' => 'erp-pyme-pro']);
        $this->assertDatabaseHas('usuarios', ['id' => $otroUsuario->id, 'plan_slug' => 'erp-pyme-pro']);
    }

    /**
     * Regresion multiempresa: cambiar el plan de la empresa B tambien debe
     * actualizar a un usuario cuya empresa "hogar" es A pero opera en B.
     */
    public function test_cambiar_plan_actualiza_usuario_con_empresa_activa_distinta_de_empresa_hogar(): void
    {
        [$empresaA] = $this->crearEmpresaConAdmin();
        [$empresaB] = $this->crearEmpresaConAdmin();

        $usuario = $this->crearUsuario($empresaA, null, ['empresa_activa_id' => $empresaB->id]);

        $payload = ['plan_slug' => 'erp-pyme-pro', 'module_keys' => ['clientes', 'rrhh']];

        $response = $this->putJson(
            "/api/internal/web/empresas/{$empresaB->id}/plan",
            $payload,
            $this->firmarConCuerpo($payload)
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('usuarios', ['id' => $usuario->id, 'plan_slug' => 'erp-pyme-pro']);
    }

    public function test_cambiar_plan_sin_module_keys_es_rechazado(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $payload = ['plan_slug' => 'erp-pyme-pro'];

        $response = $this->putJson(
            "/api/internal/web/empresas/{$empresa->id}/plan",
            $payload,
            $this->firmarConCuerpo($payload)
        );

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------
    // Bloquear / desbloquear usuario
    // -------------------------------------------------------------------

    public function test_bloquear_usuario_establece_bloqueado_hasta_y_revoca_tokens(): void
    {
        [, $usuario] = $this->crearEmpresaConAdmin();
        $usuario->createToken('test');
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $response = $this->postJson(
            "/api/internal/web/usuarios/{$usuario->id}/bloquear",
            [],
            $this->firmarConCuerpo([])
        );

        $response->assertStatus(200)->assertJsonPath('usuario.bloqueado', true);
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $bloqueadoHasta = DB::table('usuarios')->where('id', $usuario->id)->value('bloqueado_hasta');
        $this->assertNotNull($bloqueadoHasta);
    }

    public function test_bloquear_usuario_con_fecha_formato_invalido_es_rechazado(): void
    {
        [, $usuario] = $this->crearEmpresaConAdmin();

        $payload = ['hasta' => 'no-es-una-fecha'];

        $response = $this->postJson(
            "/api/internal/web/usuarios/{$usuario->id}/bloquear",
            $payload,
            $this->firmarConCuerpo($payload)
        );

        $response->assertStatus(422);
    }

    public function test_desbloquear_usuario_limpia_bloqueo_e_intentos_fallidos(): void
    {
        [, $usuario] = $this->crearEmpresaConAdmin();
        DB::table('usuarios')->where('id', $usuario->id)->update([
            'bloqueado_hasta' => now()->addDay(),
            'intentos_fallidos' => 5,
            'nivel_bloqueo' => 2,
        ]);

        $response = $this->postJson(
            "/api/internal/web/usuarios/{$usuario->id}/desbloquear",
            [],
            $this->firmarConCuerpo([])
        );

        $response->assertStatus(200)->assertJsonPath('usuario.bloqueado', false);
        $this->assertDatabaseHas('usuarios', [
            'id' => $usuario->id,
            'bloqueado_hasta' => null,
            'intentos_fallidos' => 0,
            'nivel_bloqueo' => 0,
        ]);
    }

    public function test_desbloquear_usuario_inexistente_retorna_404(): void
    {
        $response = $this->postJson(
            '/api/internal/web/usuarios/999999/desbloquear',
            [],
            $this->firmarConCuerpo([])
        );

        $response->assertStatus(404);
    }
}
