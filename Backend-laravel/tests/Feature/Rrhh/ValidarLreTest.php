<?php

namespace Tests\Feature\Rrhh;

use App\Domains\Rrhh\Exceptions\RrhhException;
use App\Domains\Rrhh\Models\LreEnvio;
use App\Domains\Rrhh\Services\Lre\ValidarLreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class ValidarLreTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    private ValidarLreService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->service = app(ValidarLreService::class);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Contenido mínimo válido con todos los marcadores y campos obligatorios.
     */
    private function contenidoLreValido(string $rutEmpresa = '12345678-9'): string
    {
        return implode("\n", [
            '## INICIO_LIBRO',
            "EMPRESA_RUT|{$rutEmpresa}",
            'EMPRESA_RAZON_SOCIAL|Empresa Test',
            'PERIODO_ANIO|2026',
            'PERIODO_MES|6',
            'CANTIDAD_TRABAJADORES|1',
            '## INICIO_TRABAJADOR',
            '1101|9876543-2',
            '1102|01/01/2024',
            '2101|800000',
            '5501|650000',
            '## FIN_TRABAJADOR',
            '## FIN_LIBRO',
        ]);
    }

    // ------------------------------------------------------------------
    // Tests
    // ------------------------------------------------------------------

    public function test_lre_valido_no_retorna_errores(): void
    {
        Storage::fake('sii_xml');

        $empresa = $this->crearEmpresa();

        $archivoPath = "lre/{$empresa->id}/2026-06.txt";
        Storage::disk('sii_xml')->put($archivoPath, $this->contenidoLreValido());

        $lre = LreEnvio::create([
            'empresa_id'           => $empresa->id,
            'anio'                 => 2026,
            'mes'                  => 6,
            'estado'               => LreEnvio::ESTADO_GENERADO,
            'cantidad_trabajadores' => 1,
            'archivo_path'         => $archivoPath,
        ]);

        $errores = $this->service->validar($lre);

        $this->assertIsArray($errores);
        $this->assertEmpty($errores, 'Se esperaba que el LRE válido no retornara errores.');
    }

    public function test_detecta_archivo_sin_marcador_inicio_libro(): void
    {
        Storage::fake('sii_xml');

        $empresa = $this->crearEmpresa();

        // Contenido que omite ## INICIO_LIBRO y comienza directo con ## INICIO_TRABAJADOR
        $contenidoSinInicio = implode("\n", [
            'EMPRESA_RUT|12345678-9',
            'EMPRESA_RAZON_SOCIAL|Empresa Test',
            'PERIODO_ANIO|2026',
            'PERIODO_MES|6',
            'CANTIDAD_TRABAJADORES|1',
            '## INICIO_TRABAJADOR',
            '1101|9876543-2',
            '1102|01/01/2024',
            '2101|800000',
            '5501|650000',
            '## FIN_TRABAJADOR',
            '## FIN_LIBRO',
        ]);

        $archivoPath = "lre/{$empresa->id}/2026-06.txt";
        Storage::disk('sii_xml')->put($archivoPath, $contenidoSinInicio);

        $lre = LreEnvio::create([
            'empresa_id'           => $empresa->id,
            'anio'                 => 2026,
            'mes'                  => 6,
            'estado'               => LreEnvio::ESTADO_GENERADO,
            'cantidad_trabajadores' => 1,
            'archivo_path'         => $archivoPath,
        ]);

        $errores = $this->service->validar($lre);

        $this->assertNotEmpty($errores, 'Se esperaba al menos un error por marcador faltante.');

        $hayErrorInicio = collect($errores)->contains(
            fn (string $e) => str_contains($e, 'INICIO_LIBRO')
        );

        $this->assertTrue($hayErrorInicio, 'Se esperaba un error que mencione INICIO_LIBRO.');
    }

    public function test_detecta_bloques_trabajador_desbalanceados(): void
    {
        Storage::fake('sii_xml');

        $empresa = $this->crearEmpresa();

        // 2 aperturas INICIO_TRABAJADOR pero solo 1 cierre FIN_TRABAJADOR
        $contenidoDesbalanceado = implode("\n", [
            '## INICIO_LIBRO',
            'EMPRESA_RUT|12345678-9',
            'EMPRESA_RAZON_SOCIAL|Empresa Test',
            'PERIODO_ANIO|2026',
            'PERIODO_MES|6',
            'CANTIDAD_TRABAJADORES|2',
            '## INICIO_TRABAJADOR',
            '1101|9876543-2',
            '1102|01/01/2024',
            '2101|800000',
            '5501|650000',
            '## FIN_TRABAJADOR',
            '## INICIO_TRABAJADOR',
            '1101|11111111-1',
            '1102|01/03/2024',
            '2101|900000',
            '5501|720000',
            // FIN_TRABAJADOR faltante deliberadamente
            '## FIN_LIBRO',
        ]);

        $archivoPath = "lre/{$empresa->id}/2026-06.txt";
        Storage::disk('sii_xml')->put($archivoPath, $contenidoDesbalanceado);

        $lre = LreEnvio::create([
            'empresa_id'           => $empresa->id,
            'anio'                 => 2026,
            'mes'                  => 6,
            'estado'               => LreEnvio::ESTADO_GENERADO,
            'cantidad_trabajadores' => 2,
            'archivo_path'         => $archivoPath,
        ]);

        $errores = $this->service->validar($lre);

        $this->assertNotEmpty($errores, 'Se esperaba al menos un error por bloques desbalanceados.');

        $hayErrorInconsistencia = collect($errores)->contains(
            fn (string $e) => str_contains($e, 'inconsistente')
        );

        $this->assertTrue($hayErrorInconsistencia, 'Se esperaba un error que mencione estructura inconsistente.');
    }

    public function test_detecta_campo_obligatorio_faltante_en_trabajador(): void
    {
        Storage::fake('sii_xml');

        $empresa = $this->crearEmpresa();

        // Bloque de trabajador con 1101 y 1102 pero SIN 2101 (sueldo base)
        $contenidoSin2101 = implode("\n", [
            '## INICIO_LIBRO',
            'EMPRESA_RUT|12345678-9',
            'EMPRESA_RAZON_SOCIAL|Empresa Test',
            'PERIODO_ANIO|2026',
            'PERIODO_MES|6',
            'CANTIDAD_TRABAJADORES|1',
            '## INICIO_TRABAJADOR',
            '1101|9876543-2',
            '1102|01/01/2024',
            // Campo 2101 faltante deliberadamente
            '5501|650000',
            '## FIN_TRABAJADOR',
            '## FIN_LIBRO',
        ]);

        $archivoPath = "lre/{$empresa->id}/2026-06.txt";
        Storage::disk('sii_xml')->put($archivoPath, $contenidoSin2101);

        $lre = LreEnvio::create([
            'empresa_id'           => $empresa->id,
            'anio'                 => 2026,
            'mes'                  => 6,
            'estado'               => LreEnvio::ESTADO_GENERADO,
            'cantidad_trabajadores' => 1,
            'archivo_path'         => $archivoPath,
        ]);

        $errores = $this->service->validar($lre);

        $this->assertNotEmpty($errores, 'Se esperaba al menos un error por campo 2101 faltante.');

        $hayError2101 = collect($errores)->contains(
            fn (string $e) => str_contains($e, '2101') || str_contains(strtolower($e), 'sueldo')
        );

        $this->assertTrue($hayError2101, 'Se esperaba un error que mencione el campo 2101 o sueldo.');
    }

    public function test_lanza_excepcion_si_no_hay_archivo_generado(): void
    {
        $empresa = $this->crearEmpresa();

        $lre = LreEnvio::create([
            'empresa_id'           => $empresa->id,
            'anio'                 => 2026,
            'mes'                  => 6,
            'estado'               => LreEnvio::ESTADO_GENERADO,
            'cantidad_trabajadores' => 0,
            'archivo_path'         => null,
        ]);

        $this->expectException(RrhhException::class);
        $this->expectExceptionMessage('El LRE no tiene archivo generado. Genera el LRE primero.');

        $this->service->validar($lre);
    }
}
