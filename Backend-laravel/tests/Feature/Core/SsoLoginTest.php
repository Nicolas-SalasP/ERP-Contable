<?php

namespace Tests\Feature\Core;

use App\Domains\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class SsoLoginTest extends TestCase
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

    private function fakeWeb(array $subscription, bool $ok = true): void
    {
        Http::fake([
            '*/api/internal/erp/validate-login' => Http::response(
                $ok ? [
                    'success' => true,
                    'user'    => [
                        'tenri_user_id' => 555,
                        'name'          => 'Cliente Web',
                        'email'         => 'web@tenri.cl',
                        'rut'           => '1-9',
                        'password_hash' => bcrypt('webpass'),
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

    public function test_login_web_valido_provisiona_usuario_y_devuelve_token(): void
    {
        $this->fakeWeb(['status' => 'active', 'ends_at' => now()->addDays(20)->toIso8601String()]);

        $res = $this->postJson('/api/auth/login', ['email' => 'web@tenri.cl', 'password' => 'webpass'])
            ->assertStatus(200);

        $this->assertNotEmpty($res->json('token'));
        $this->assertDatabaseHas('usuarios', [
            'email'               => 'web@tenri.cl',
            'tenri_user_id'       => 555,
            'plan_slug'           => 'erp-pyme-esencial',
            'subscription_status' => 'active',
        ]);
    }

    public function test_credenciales_invalidas_en_ambos_lados_devuelve_401(): void
    {
        $this->fakeWeb([], ok: false);

        $this->postJson('/api/auth/login', ['email' => 'nadie@tenri.cl', 'password' => 'x'])
            ->assertStatus(401);
    }

    public function test_suscripcion_vencida_devuelve_403(): void
    {
        $this->fakeWeb(['status' => 'expired', 'ends_at' => now()->subDays(40)->toIso8601String()]);

        $this->postJson('/api/auth/login', ['email' => 'web@tenri.cl', 'password' => 'webpass'])
            ->assertStatus(403);

        $this->assertDatabaseHas('usuarios', ['email' => 'web@tenri.cl', 'subscription_status' => 'expired']);
    }
}
