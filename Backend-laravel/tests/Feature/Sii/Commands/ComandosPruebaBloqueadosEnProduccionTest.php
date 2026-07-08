<?php

namespace Tests\Feature\Sii\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Regresión (auditoría 2026-07-08, crítico): los comandos "prueba" del dominio Sii consumen
 * folios CAF reales y pegan al WS real del SII, pero no tenían ningún guard de entorno —
 * correrlos por error en producción (deploy mal configurado, acceso a artisan) quema un folio
 * timbrado legal con datos ficticios.
 */
class ComandosPruebaBloqueadosEnProduccionTest extends TestCase
{
    use RefreshDatabase;

    public function test_emitir_dte_prueba_es_rechazado_en_produccion(): void
    {
        $this->app['env'] = 'production';

        $exitCode = Artisan::call('sii:emitir-dte-prueba', ['empresa_id' => 1]);

        $this->assertNotEquals(0, $exitCode);
        $this->assertStringContainsString('deshabilitado en producción', Artisan::output());
    }

    public function test_enviar_dte_prueba_es_rechazado_en_produccion(): void
    {
        $this->app['env'] = 'production';

        $exitCode = Artisan::call('sii:enviar-dte-prueba', ['dte_id' => 1]);

        $this->assertNotEquals(0, $exitCode);
        $this->assertStringContainsString('deshabilitado en producción', Artisan::output());
    }

    public function test_obtener_token_prueba_es_rechazado_en_produccion(): void
    {
        $this->app['env'] = 'production';

        $exitCode = Artisan::call('sii:obtener-token-prueba', ['empresa_id' => 1]);

        $this->assertNotEquals(0, $exitCode);
        $this->assertStringContainsString('deshabilitado en producción', Artisan::output());
    }

    public function test_generar_xml_prueba_es_rechazado_en_produccion(): void
    {
        $this->app['env'] = 'production';

        $exitCode = Artisan::call('sii:generar-xml-prueba', ['dte_id' => 1]);

        $this->assertNotEquals(0, $exitCode);
        $this->assertStringContainsString('deshabilitado en producción', Artisan::output());
    }

    public function test_flujo_completo_prueba_es_rechazado_en_produccion(): void
    {
        $this->app['env'] = 'production';

        $exitCode = Artisan::call('sii:flujo-completo-prueba', ['empresa_id' => 1]);

        $this->assertNotEquals(0, $exitCode);
        $this->assertStringContainsString('deshabilitado en producción', Artisan::output());
    }
}
