<?php

namespace Tests\Feature\Contabilidad;

use App\Domains\Contabilidad\Contracts\DeclaracionJuradaContract;
use App\Domains\Contabilidad\DataTransfer\DjData;
use App\Domains\Contabilidad\DataTransfer\DjLineaData;
use App\Domains\Contabilidad\Models\DjEnvio;
use App\Domains\Contabilidad\Services\Dj\DjEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

// ---------------------------------------------------------------------------
// Contrato falso — prueba el engine, no una implementacion real
// ---------------------------------------------------------------------------

class FakeDj implements DeclaracionJuradaContract
{
    public int $construirLlamado = 0;
    public bool $debeRetornarErrores = false;

    public function codigo(): string
    {
        return '9999';
    }

    public function construir(int $empresaId, int $anio): DjData
    {
        $this->construirLlamado++;

        return new DjData(
            codigoDj:  '9999',
            empresaId: $empresaId,
            anio:      $anio,
            cabecera:  ['rut' => '12345678-9'],
            lineas:    [
                new DjLineaData(['rut' => '11111111-1', 'monto' => 1000]),
            ],
        );
    }

    public function validar(DjData $data): array
    {
        return $this->debeRetornarErrores ? ['Error de prueba'] : [];
    }

    public function formatearArchivo(DjData $data): string
    {
        return "FAKE;{$data->codigoDj};{$data->anio}";
    }
}

// ---------------------------------------------------------------------------
// Tests del DjEngine
// ---------------------------------------------------------------------------

class DjEngineTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    private DjEngine $engine;
    private FakeDj   $fakeDj;
    private int      $empresaId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        Storage::fake('sii_xml');

        $this->engine    = app(DjEngine::class);
        $this->fakeDj    = new FakeDj();

        [$empresa] = $this->crearEmpresaConAdmin();
        $this->empresaId = $empresa->id;
    }

    public function test_engine_genera_envio_desde_contrato_fake(): void
    {
        $envio = $this->engine->generar($this->fakeDj, $this->empresaId, 2025);

        $this->assertInstanceOf(DjEnvio::class, $envio);
        $this->assertSame(DjEnvio::ESTADO_GENERADO, $envio->estado);
        $this->assertSame(1, $envio->cantidad_registros);
        $this->assertSame($this->empresaId, $envio->empresa_id);
        $this->assertSame('9999', $envio->codigo_dj);
        $this->assertSame(2025, (int) $envio->anio);

        $rutaEsperada = "dj/{$this->empresaId}/9999/2025.txt";
        $this->assertSame($rutaEsperada, $envio->archivo_path);
        Storage::disk('sii_xml')->assertExists($rutaEsperada);
    }

    public function test_engine_marca_validado_cuando_contrato_no_retorna_errores(): void
    {
        $this->fakeDj->debeRetornarErrores = false;

        $envio   = $this->engine->generar($this->fakeDj, $this->empresaId, 2025);
        $errores = $this->engine->validar($this->fakeDj, $envio);

        $envio->refresh();

        $this->assertEmpty($errores);
        $this->assertSame(DjEnvio::ESTADO_VALIDADO, $envio->estado);
        $this->assertNull($envio->errores_validacion);
    }

    public function test_engine_marca_con_errores_y_guarda_errores(): void
    {
        $this->fakeDj->debeRetornarErrores = true;

        $envio   = $this->engine->generar($this->fakeDj, $this->empresaId, 2025);
        $errores = $this->engine->validar($this->fakeDj, $envio);

        $envio->refresh();

        $this->assertNotEmpty($errores);
        $this->assertSame(DjEnvio::ESTADO_CON_ERRORES, $envio->estado);
        $this->assertSame(['Error de prueba'], $envio->errores_validacion);
    }

    public function test_unique_impide_duplicar_dj_del_mismo_periodo(): void
    {
        $this->engine->generar($this->fakeDj, $this->empresaId, 2025);
        $this->engine->generar($this->fakeDj, $this->empresaId, 2025);

        $cantidad = DjEnvio::withoutGlobalScopes()
            ->where('empresa_id', $this->empresaId)
            ->where('codigo_dj', '9999')
            ->where('anio', 2025)
            ->count();

        $this->assertSame(1, $cantidad);
    }
}
