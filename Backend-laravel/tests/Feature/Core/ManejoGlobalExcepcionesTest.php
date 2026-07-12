<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Regresión: bootstrap/app.php agrega un catch-all de \Throwable que devuelve un mensaje
 * genérico para cualquier excepción inesperada (ej. error de config/librería de terceros que
 * antes filtraba su mensaje crudo al cliente, encontrado en QA Playwright 2026-07-12).
 *
 * Riesgo real de ese catch-all: por ser \Throwable, también matchea ValidationException y
 * excepciones HTTP normales (404/403/405) si no se excluyen explícitamente — este test verifica
 * que esas exclusiones funcionan y no quedaron convertidas en 500 genérico.
 */
class ManejoGlobalExcepcionesTest extends TestCase
{
    use RefreshDatabase;

    public function test_excepcion_inesperada_no_expone_mensaje_crudo(): void
    {
        // El .env local de desarrollo tiene APP_DEBUG=true (intencional, para depurar), y como no
        // existe .env.testing, phpunit hereda ese valor. El catch-all solo actua con debug=false
        // (el valor real en produccion, ver .env.example) — se fuerza explicitamente aqui para no
        // depender del .env ambiente de quien corra el test.
        config(['app.debug' => false]);

        Route::get('/api/_test/excepcion-inesperada', function () {
            throw new \RuntimeException('CipherSweet\\KeyProvider secret leaked internals xyz');
        });

        $response = $this->getJson('/api/_test/excepcion-inesperada');

        $response->assertStatus(500);
        $response->assertJson(['success' => false]);
        $this->assertStringNotContainsString('CipherSweet', (string) $response->getContent());
        $this->assertStringContainsString('Ocurri', (string) $response->getContent());
    }

    public function test_validation_exception_sigue_devolviendo_422_no_500_generico(): void
    {
        Route::get('/api/_test/validacion', function () {
            throw ValidationException::withMessages(['campo' => ['El campo es obligatorio.']]);
        });

        $response = $this->getJson('/api/_test/validacion');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['campo']);
    }

    public function test_ruta_inexistente_sigue_devolviendo_404_no_500_generico(): void
    {
        $response = $this->getJson('/api/_test/no-existe-esta-ruta');

        $response->assertStatus(404);
    }
}
