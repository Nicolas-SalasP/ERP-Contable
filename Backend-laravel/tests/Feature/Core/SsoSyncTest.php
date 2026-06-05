<?php

namespace Tests\Feature\Core;

use App\Domains\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class SsoSyncTest extends TestCase
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

    private function crearUsuarioLocal(?\DateTimeInterface $syncedAt): User
    {
        return User::create([
            'nombre'                => 'Local',
            'email'                 => 'local@tenri.cl',
            'password'              => 'localpass',
            'empresa_id'            => null,
            'rol_id'                => $this->rolAdministrador->id,
            'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id,
            'tenri_user_id'         => 777,
            'plan_slug'             => 'erp-pyme-esencial',
            'module_keys'           => ['clientes'],
            'subscription_status'   => 'active',
            'tenri_synced_at'       => $syncedAt,
        ]);
    }

    private function fakeWebReportaReadOnly(): void
    {
        Http::fake([
            '*/api/internal/erp/validate-login' => Http::response([
                'success' => true,
                'user'    => [
                    'tenri_user_id' => 777,
                    'name'          => 'Local',
                    'email'         => 'local@tenri.cl',
                    'password_hash' => bcrypt('localpass'),
                ],
                'plan'    => ['plan_slug' => 'erp-pyme-esencial', 'module_keys' => ['clientes'], 'rol_erp' => 'Administrador'],
                'subscription' => ['status' => 'read_only', 'ends_at' => now()->subDays(20)->toIso8601String()],
            ], 200),
        ]);
    }

    public function test_resincroniza_cuando_el_cache_es_viejo(): void
    {
        $user = $this->crearUsuarioLocal(now()->subHours(2));
        $this->fakeWebReportaReadOnly();

        $this->postJson('/api/auth/login', ['email' => 'local@tenri.cl', 'password' => 'localpass'])
            ->assertStatus(200);

        Http::assertSent(fn ($req) => str_contains($req->url(), '/internal/erp/validate-login'));
        $this->assertSame('read_only', $user->fresh()->subscription_status);
    }

    public function test_no_resincroniza_cuando_el_cache_es_reciente(): void
    {
        $user = $this->crearUsuarioLocal(now());
        $this->fakeWebReportaReadOnly();

        $this->postJson('/api/auth/login', ['email' => 'local@tenri.cl', 'password' => 'localpass'])
            ->assertStatus(200);

        Http::assertNothingSent();
        $this->assertSame('active', $user->fresh()->subscription_status);
    }
}
