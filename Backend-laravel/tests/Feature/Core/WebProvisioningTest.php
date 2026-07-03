<?php

namespace Tests\Feature\Core;

use App\Domains\Core\Models\User;
use App\Support\HmacFirma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Cobertura de las rutas SSO/HMAC de provisioning (`internal/web/provision-user`,
 * `sync-plan`, `online-users`), protegidas por el middleware `web.api.key`
 * (VerifyWebApiKey -> HmacFirma::verifica). Antes de este test tenian 0% de
 * cobertura pese a ser el unico punto de entrada por el que tenri.cl crea y
 * sincroniza usuarios/planes en el ERP.
 */
class WebProvisioningTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    private const SECRETO = 'secreto-provisioning-test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        config(['services.tenri_web.web_integration_key' => self::SECRETO]);
    }

    /** Construye headers HMAC validos para un payload dado. */
    private function firmar(array $payload, ?int $timestamp = null): array
    {
        $cuerpo = json_encode($payload);

        if ($timestamp === null) {
            return HmacFirma::headers(self::SECRETO, $cuerpo);
        }

        $nonce = bin2hex(random_bytes(8));
        $firma = hash_hmac('sha256', $timestamp . '.' . $nonce . '.' . hash('sha256', $cuerpo), self::SECRETO);

        return ['X-Timestamp' => (string) $timestamp, 'X-Nonce' => $nonce, 'X-Signature' => $firma];
    }

    private function payloadProvisioning(array $overrides = []): array
    {
        return array_merge([
            'tenri_user_id' => 100001,
            'email' => 'nuevo.cliente@test.cl',
            'name' => 'Nuevo Cliente',
            'rut' => '11.111.111-1',
            'password_hash' => bcrypt('clave-tenri'),
            'plan_slug' => 'erp-pyme-esencial',
            'module_keys' => ['clientes', 'cotizaciones'],
            'rol_erp' => 'Administrador',
        ], $overrides);
    }

    // -------------------------------------------------------------------
    // Sin firma HMAC valida -> rechazado
    // -------------------------------------------------------------------

    public function test_provision_user_sin_firma_hmac_es_rechazado(): void
    {
        $payload = $this->payloadProvisioning();

        $response = $this->postJson('/api/internal/web/provision-user', $payload);

        $response->assertStatus(401);
        $this->assertDatabaseMissing('usuarios', ['email' => 'nuevo.cliente@test.cl']);
    }

    public function test_provision_user_con_firma_invalida_es_rechazado(): void
    {
        $payload = $this->payloadProvisioning();
        $headers = $this->firmar($payload);
        $headers['X-Signature'] = str_repeat('0', 64);

        $response = $this->json('POST', '/api/internal/web/provision-user', $payload, $headers);

        $response->assertStatus(401);
        $this->assertDatabaseMissing('usuarios', ['email' => 'nuevo.cliente@test.cl']);
    }

    public function test_sync_plan_sin_firma_hmac_es_rechazado(): void
    {
        $response = $this->postJson('/api/internal/web/sync-plan', [
            'tenri_user_id' => 999,
            'plan_slug' => 'erp-pyme-esencial',
            'module_keys' => ['clientes'],
        ]);

        $response->assertStatus(401);
    }

    public function test_online_users_sin_firma_hmac_es_rechazado(): void
    {
        $response = $this->getJson('/api/internal/web/online-users');

        $response->assertStatus(401);
    }

    // -------------------------------------------------------------------
    // Con firma valida -> provisiona correctamente, aislado por empresa_id
    // -------------------------------------------------------------------

    public function test_provision_user_con_firma_valida_crea_usuario_correctamente(): void
    {
        $payload = $this->payloadProvisioning();
        $headers = $this->firmar($payload);

        $response = $this->json('POST', '/api/internal/web/provision-user', $payload, $headers);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'user' => [
                    'email' => 'nuevo.cliente@test.cl',
                    'tenri_user_id' => 100001,
                    'plan_slug' => 'erp-pyme-esencial',
                ],
            ]);

        $usuario = User::where('email', 'nuevo.cliente@test.cl')->first();
        $this->assertNotNull($usuario);
        $this->assertSame(100001, $usuario->tenri_user_id);
        // Usuario recien provisionado aun no tiene empresa asignada: el
        // onboarding posterior (Route /empresas/onboarding) es quien la crea.
        $this->assertNull($usuario->empresa_id);
        $this->assertSame($this->rolAdministrador->id, $usuario->rol_id);
    }

    public function test_provision_user_aisla_por_empresa_no_mezcla_usuarios_de_distintas_empresas(): void
    {
        [$empresaA, $adminA] = $this->crearEmpresaConAdmin();
        $adminA->forceFill(['tenri_user_id' => 555])->save();

        // Se reprovisiona (actualiza) al usuario de la empresa A por su
        // tenri_user_id; no debe tocar ni crear usuarios de otra empresa.
        $payload = $this->payloadProvisioning([
            'tenri_user_id' => 555,
            'email' => $adminA->email,
            'name' => 'Admin A Actualizado',
        ]);
        $headers = $this->firmar($payload);

        $response = $this->json('POST', '/api/internal/web/provision-user', $payload, $headers);
        $response->assertStatus(201);

        $adminA->refresh();
        $this->assertSame('Admin A Actualizado', $adminA->nombre);
        // La empresa a la que ya pertenecia no debe alterarse por el reprovisioning.
        $this->assertSame($empresaA->id, $adminA->empresa_id);

        // Ningun otro usuario fue creado o modificado por esta llamada.
        $this->assertSame(1, User::where('tenri_user_id', 555)->count());
    }

    public function test_sync_plan_con_firma_valida_actualiza_solo_el_usuario_indicado(): void
    {
        [$empresaA, $adminA] = $this->crearEmpresaConAdmin();
        $adminA->forceFill(['tenri_user_id' => 321, 'plan_slug' => 'erp-starter'])->save();

        [$empresaB, $adminB] = $this->crearEmpresaConAdmin();
        $adminB->forceFill(['tenri_user_id' => 322, 'plan_slug' => 'erp-starter'])->save();

        $payload = [
            'tenri_user_id' => 321,
            'plan_slug' => 'erp-pyme-esencial',
            'module_keys' => ['clientes', 'cotizaciones'],
        ];
        $headers = $this->firmar($payload);

        $response = $this->json('POST', '/api/internal/web/sync-plan', $payload, $headers);
        $response->assertOk()->assertJson(['success' => true, 'usuarios_updated' => 1]);

        $this->assertSame('erp-pyme-esencial', $adminA->fresh()->plan_slug);
        // El usuario de la empresa B (mismo plan anterior) no debe verse afectado:
        // el sync es por tenri_user_id, no por plan_slug compartido.
        $this->assertSame('erp-starter', $adminB->fresh()->plan_slug);
    }

    // -------------------------------------------------------------------
    // Replay de la misma firma fuera de la ventana de 300s -> rechazado
    // -------------------------------------------------------------------

    public function test_provision_user_con_timestamp_expirado_es_rechazado(): void
    {
        $payload = $this->payloadProvisioning();
        $headers = $this->firmar($payload, time() - (HmacFirma::VENTANA_SEGUNDOS + 60));

        $response = $this->json('POST', '/api/internal/web/provision-user', $payload, $headers);

        $response->assertStatus(401);
        $this->assertDatabaseMissing('usuarios', ['email' => 'nuevo.cliente@test.cl']);
    }

    public function test_replay_de_la_misma_firma_es_rechazado(): void
    {
        $payload = $this->payloadProvisioning();
        $headers = $this->firmar($payload);

        // Primera vez: firma fresca y valida -> se acepta y provisiona.
        $this->json('POST', '/api/internal/web/provision-user', $payload, $headers)
            ->assertStatus(201);

        // Replay exacto (mismos headers, mismo payload): el nonce ya fue
        // consumido dentro de la ventana de 300s -> debe rechazarse.
        $this->json('POST', '/api/internal/web/provision-user', $payload, $headers)
            ->assertStatus(401);

        $this->assertSame(1, User::where('email', 'nuevo.cliente@test.cl')->count());
    }
}
