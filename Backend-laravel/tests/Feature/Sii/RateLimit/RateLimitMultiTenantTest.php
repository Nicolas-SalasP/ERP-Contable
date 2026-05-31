<?php

namespace Tests\Feature\Sii\RateLimit;

use App\Domains\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class RateLimitMultiTenantTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        // Limpiamos los buckets del RateLimiter entre tests para que un test
        // no contamine el bucket de otro (en testing el cache es persistente
        // en la tabla 'cache' aunque RefreshDatabase resetee schema).
        Cache::flush();
        RateLimiter::clear('sii-empresa');
    }

    public function test_empresa_A_no_excede_limite_al_hacer_60_requests_en_un_minuto(): void
    {
        [, $usuarioA] = $this->crearEmpresaConAdmin();
        Sanctum::actingAs($usuarioA);

        // Hacemos 60 requests al endpoint mas barato (ping) y todas deben pasar.
        for ($i = 1; $i <= 60; $i++) {
            $response = $this->getJson('/api/sii/ping');
            $this->assertSame(200, $response->status(), "Request {$i} debio retornar 200, retorno {$response->status()}");
        }
    }

    public function test_empresa_A_excede_limite_recibe_429_al_request_61(): void
    {
        [, $usuarioA] = $this->crearEmpresaConAdmin();
        Sanctum::actingAs($usuarioA);

        for ($i = 1; $i <= 60; $i++) {
            $this->getJson('/api/sii/ping');
        }

        $response = $this->getJson('/api/sii/ping');
        $this->assertSame(429, $response->status(), 'Request 61 debio ser rate-limited (429).');
    }

    public function test_empresa_A_excediendo_limite_NO_afecta_empresa_B(): void
    {
        // Empresa A consume todo su bucket.
        [, $usuarioA] = $this->crearEmpresaConAdmin();
        Sanctum::actingAs($usuarioA);
        for ($i = 1; $i <= 60; $i++) {
            $this->getJson('/api/sii/ping');
        }
        $this->assertSame(429, $this->getJson('/api/sii/ping')->status(), 'A debe estar en 429.');

        // Re-autenticamos como empresa B (distinta) y verificamos que NO esta throttled.
        // Sanctum reset: nuevo user de otra empresa.
        $this->app['auth']->forgetGuards();
        [, $usuarioB] = $this->crearEmpresaConAdmin();
        Sanctum::actingAs($usuarioB);

        $response = $this->getJson('/api/sii/ping');
        $this->assertSame(
            200,
            $response->status(),
            'Empresa B no debe verse afectada por el throttle de empresa A; recibio: ' . $response->status()
        );
    }

    public function test_upload_de_caf_tiene_throttle_mas_estricto_de_10_por_hora(): void
    {
        // Limpiamos especificamente el bucket de uploads pesados.
        Cache::flush();

        [, $usuarioA] = $this->crearEmpresaConAdmin();
        Sanctum::actingAs($usuarioA);

        // Hacemos 10 POST a /caf con archivo invalido (esperamos 422, no 429).
        // El point: el throttle se cuenta independiente del exit code.
        for ($i = 1; $i <= 10; $i++) {
            $resp = $this->post('/api/sii/caf', [
                'archivo' => \Illuminate\Http\UploadedFile::fake()->createWithContent('caf.xml', 'not-xml'),
            ], ['Accept' => 'application/json']);
            $this->assertNotSame(429, $resp->status(), "Request {$i} no debio estar throttled todavia.");
        }

        // El 11vo debe ser throttled.
        $resp = $this->post('/api/sii/caf', [
            'archivo' => \Illuminate\Http\UploadedFile::fake()->createWithContent('caf.xml', 'not-xml'),
        ], ['Accept' => 'application/json']);
        $this->assertSame(429, $resp->status(), 'Request 11 a /caf debe estar throttled.');
    }
}
