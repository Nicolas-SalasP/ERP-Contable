<?php

namespace Tests\Feature\Core;

use Sentry\State\HubInterface;
use Tests\TestCase;

/**
 * Smoke test del wiring de Sentry: el hub está registrado y la captura de
 * excepciones está activa. El envío real depende de SENTRY_LARAVEL_DSN (prod);
 * sin DSN, Sentry es no-op pero el binding debe existir.
 */
class SentryConfiguradoTest extends TestCase
{
    public function test_sentry_esta_registrado_en_el_contenedor(): void
    {
        $this->assertTrue($this->app->bound('sentry'));
        $this->assertInstanceOf(HubInterface::class, $this->app->make('sentry'));
    }

    public function test_capturar_excepcion_no_lanza_error(): void
    {
        // Con DSN vacío esto es no-op, pero confirma que la integración no rompe.
        \Sentry\captureException(new \RuntimeException('health-check de Sentry'));
        $this->assertTrue(true);
    }
}
