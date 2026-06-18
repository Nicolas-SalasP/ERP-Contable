<?php

namespace Tests\Feature\Rrhh;

use App\Domains\Rrhh\Exceptions\RrhhException;
use App\Domains\Rrhh\Models\LreEnvio;
use App\Domains\Rrhh\Services\Lre\ConfirmarDtService;
use App\Domains\Rrhh\Services\Lre\PrepararDescargaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class DescargaConfirmacionLreTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    private PrepararDescargaService $prepararDescargaService;
    private ConfirmarDtService $confirmarDtService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->prepararDescargaService = app(PrepararDescargaService::class);
        $this->confirmarDtService      = app(ConfirmarDtService::class);
    }

    // ------------------------------------------------------------------
    // PrepararDescargaService
    // ------------------------------------------------------------------

    public function test_preparar_descarga_retorna_ruta_y_nombre_archivo(): void
    {
        $empresa = $this->crearEmpresa();

        $lre = LreEnvio::create([
            'empresa_id'           => $empresa->id,
            'anio'                 => 2026,
            'mes'                  => 6,
            'estado'               => LreEnvio::ESTADO_GENERADO,
            'cantidad_trabajadores' => 2,
            'archivo_path'         => 'lre/' . $empresa->id . '/2026-06.txt',
        ]);

        $resultado = $this->prepararDescargaService->prepararDescarga($lre);

        $this->assertSame('lre/' . $empresa->id . '/2026-06.txt', $resultado['ruta']);
        $this->assertSame('LRE_2026_06.txt', $resultado['nombre_archivo']);
    }

    public function test_preparar_descarga_lanza_excepcion_si_no_hay_archivo(): void
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

        $this->prepararDescargaService->prepararDescarga($lre);
    }

    // ------------------------------------------------------------------
    // ConfirmarDtService
    // ------------------------------------------------------------------

    public function test_confirmar_dt_transiciona_estado_a_confirmado(): void
    {
        $empresa = $this->crearEmpresa();

        $lre = LreEnvio::create([
            'empresa_id'           => $empresa->id,
            'anio'                 => 2026,
            'mes'                  => 6,
            'estado'               => LreEnvio::ESTADO_VALIDADO,
            'cantidad_trabajadores' => 2,
            'archivo_path'         => 'lre/' . $empresa->id . '/2026-06.txt',
        ]);

        $this->confirmarDtService->confirmar($lre, 'CONF-123');

        $lreActualizado = $lre->fresh();

        $this->assertSame(LreEnvio::ESTADO_CONFIRMADO_DT, $lreActualizado->estado);
        $this->assertSame('CONF-123', $lreActualizado->numero_confirmacion_dt);
        $this->assertNotNull($lreActualizado->confirmado_at);
    }

    public function test_confirmar_dt_acepta_numero_confirmacion_null(): void
    {
        $empresa = $this->crearEmpresa();

        $lre = LreEnvio::create([
            'empresa_id'           => $empresa->id,
            'anio'                 => 2026,
            'mes'                  => 6,
            'estado'               => LreEnvio::ESTADO_VALIDADO,
            'cantidad_trabajadores' => 1,
            'archivo_path'         => 'lre/' . $empresa->id . '/2026-06.txt',
        ]);

        $this->confirmarDtService->confirmar($lre, null);

        $lreActualizado = $lre->fresh();

        $this->assertSame(LreEnvio::ESTADO_CONFIRMADO_DT, $lreActualizado->estado);
        $this->assertNull($lreActualizado->numero_confirmacion_dt);
    }

    public function test_confirmar_dt_lanza_excepcion_si_no_esta_validado(): void
    {
        $empresa = $this->crearEmpresa();

        $lre = LreEnvio::create([
            'empresa_id'           => $empresa->id,
            'anio'                 => 2026,
            'mes'                  => 6,
            'estado'               => LreEnvio::ESTADO_GENERADO,
            'cantidad_trabajadores' => 2,
            'archivo_path'         => 'lre/' . $empresa->id . '/2026-06.txt',
        ]);

        $this->expectException(RrhhException::class);
        $this->expectExceptionMessageMatches('/VALIDADO/');

        $this->confirmarDtService->confirmar($lre, 'CONF-999');
    }
}
