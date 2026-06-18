<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // Limpiar rate limiter para no contaminar otros tests
        RateLimiter::clear('health_check');
        parent::tearDown();
    }

    public function test_health_es_publico_y_reporta_ok(): void
    {
        $this->getJson('/api/health')
            ->assertStatus(200)
            ->assertJsonPath('status', 'ok');
    }

    /**
     * En entorno local la respuesta incluye el detalle completo de cada check
     * para facilitar el diagnóstico.
     */
    public function test_health_expone_detalle_en_entorno_local(): void
    {
        // app()->environment() lee $app['env']. Lo sobreescribimos directamente.
        $this->app['env'] = 'local';

        $this->getJson('/api/health')
            ->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database.ok', true)
            ->assertJsonPath('checks.cache.ok', true)
            ->assertJsonPath('checks.queue.ok', true)
            ->assertJsonPath('checks.storage.ok', true);
    }

    /**
     * En producción/staging la respuesta NO debe incluir detalle de checks:
     * sin nombres de BD, versiones, IPs ni mensajes de error internos.
     */
    public function test_health_no_expone_detalles_en_entorno_no_local(): void
    {
        $this->app['env'] = 'production';

        $respuesta = $this->getJson('/api/health')
            ->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->json();

        // En producción solo debe haber 'status', sin 'checks' ni 'time'
        $this->assertArrayNotHasKey('checks', $respuesta, 'En produccion no se deben exponer detalles de checks');
        $this->assertArrayNotHasKey('time', $respuesta, 'En produccion no se debe exponer el timestamp del servidor');
    }

    /**
     * Verifica que el throttle de 30 req/min está activo en /health.
     * No ejecutamos 30 peticiones reales; comprobamos que la ruta lleva
     * el middleware throttle inspeccionando la respuesta (cabecera X-RateLimit-Limit).
     */
    public function test_health_incluye_cabeceras_de_rate_limit(): void
    {
        $respuesta = $this->getJson('/api/health');

        $respuesta->assertStatus(200);

        // Laravel añade X-RateLimit-Limit cuando throttle: está activo.
        $this->assertNotNull(
            $respuesta->headers->get('X-RateLimit-Limit'),
            'La ruta /health debe llevar middleware throttle (cabecera X-RateLimit-Limit ausente)'
        );

        $this->assertSame(
            '30',
            $respuesta->headers->get('X-RateLimit-Limit'),
            'El limite de throttle de /health debe ser 30 req/min'
        );
    }
}
