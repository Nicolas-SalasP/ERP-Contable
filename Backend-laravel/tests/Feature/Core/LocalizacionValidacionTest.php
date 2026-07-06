<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Regresion: el proyecto no tenia NINGUNA carpeta lang/ (ni en el repo ni en
 * el servidor), asi que trans('validation.*') devolvia la clave sin traducir
 * (ej. "validation.required" literal) en cualquier endpoint donde la
 * ValidationException no fuera capturada con un mensaje propio -- el usuario
 * veia "validation.required (and 2 more errors)" en vez de un mensaje legible.
 * Confirmado en produccion via SSH: `trans('validation.required')` devolvia
 * el string sin traducir porque no existia lang/es/validation.php ni
 * lang/en/validation.php en ningun lado.
 */
class LocalizacionValidacionTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    public function test_trans_validation_required_esta_traducido_en_espanol(): void
    {
        app()->setLocale('es');

        $mensaje = trans('validation.required', ['attribute' => 'archivo']);

        $this->assertNotSame('validation.required', $mensaje);
        $this->assertStringContainsString('obligatorio', $mensaje);
    }

    public function test_endpoint_con_validacion_no_capturada_no_expone_clave_sin_traducir(): void
    {
        app()->setLocale('es');

        $this->prepararEntornoBase();
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();

        // /api/banco/importar no captura ValidationException manualmente (solo
        // TesoreriaException y Exception generico), asi que el error 422 pasa
        // por el manejador de excepciones por defecto de Laravel -- el mismo
        // camino que reprodujo el bug en produccion.
        $response = $this->actingAs($usuario)->postJson('/api/banco/importar', []);

        $response->assertStatus(422);
        $errores = collect($response->json('errors'))->flatten();

        $this->assertTrue(
            $errores->every(fn ($m) => !str_starts_with((string) $m, 'validation.')),
            'Los mensajes de validacion no deben exponer la clave de traduccion sin resolver: ' . $errores->implode(' | ')
        );
    }
}
