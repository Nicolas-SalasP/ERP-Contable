<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class TokenLoginSsoTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        config([
            'services.tenri_web.base_url' => 'https://web.test',
            'services.tenri_web.api_key'  => 'testkey',
        ]);
    }

    private function fakeWebSso(array $subscription, bool $ok = true): void
    {
        Http::fake([
            '*/api/internal/erp/validate-sso-token' => Http::response(
                $ok ? [
                    'success' => true,
                    'user'    => [
                        'tenri_user_id' => 888,
                        'name'          => 'Usuario SSO',
                        'email'         => 'sso@tenri.cl',
                        'password_hash' => bcrypt('ssopass'),
                    ],
                    'plan'    => [
                        'plan_slug'   => 'erp-pyme-esencial',
                        'module_keys' => ['clientes', 'sii'],
                        'rol_erp'     => 'Administrador',
                    ],
                    'subscription' => $subscription,
                ] : ['success' => false],
                $ok ? 200 : 401
            ),
        ]);
    }

    public function test_token_login_valido_provisiona_usuario_y_devuelve_token(): void
    {
        $this->fakeWebSso(['status' => 'active', 'ends_at' => now()->addDays(30)->toIso8601String()]);

        $res = $this->postJson('/api/auth/token-login', ['sso_token' => 'sso-valido-abc123'])
            ->assertStatus(200);

        $this->assertTrue($res->json('success'));
        $this->assertNotEmpty($res->json('token'));
        $this->assertDatabaseHas('usuarios', [
            'email'               => 'sso@tenri.cl',
            'tenri_user_id'       => 888,
            'subscription_status' => 'active',
        ]);
    }

    public function test_token_login_suscripcion_vencida_devuelve_403(): void
    {
        $this->fakeWebSso(['status' => 'expired', 'ends_at' => now()->subDays(15)->toIso8601String()]);

        $res = $this->postJson('/api/auth/token-login', ['sso_token' => 'sso-expirado-xyz'])
            ->assertStatus(403);

        $this->assertFalse($res->json('success'));
        $this->assertStringContainsString('venció', $res->json('message'));
        $this->assertDatabaseHas('usuarios', ['email' => 'sso@tenri.cl', 'subscription_status' => 'expired']);
    }

    public function test_token_login_sso_invalido_devuelve_401(): void
    {
        $this->fakeWebSso([], ok: false);

        $this->postJson('/api/auth/token-login', ['sso_token' => 'token-invalido'])
            ->assertStatus(401);
    }

    public function test_token_login_sin_sso_token_devuelve_422(): void
    {
        $this->postJson('/api/auth/token-login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sso_token']);
    }

    public function test_token_login_suscripcion_vencida_no_genera_token_de_sesion(): void
    {
        $this->fakeWebSso(['status' => 'expired', 'ends_at' => now()->subDays(5)->toIso8601String()]);

        $res = $this->postJson('/api/auth/token-login', ['sso_token' => 'sso-vencido-456'])
            ->assertStatus(403);

        $this->assertNull($res->json('token'));
    }
}
