<?php

namespace Tests\Feature\Contabilidad;

use App\Domains\Contabilidad\Services\ImpuestosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;

class F29PpmTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    public function test_simulacion_f29_usa_ppm_pct_de_la_empresa(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin(['ppm_pct' => 0.50]);

        $service = app(ImpuestosService::class);

        $resultado = $service->simularF29($empresa->id, 1, 2026);

        $this->assertSame(0.50, $resultado['ppm']['tasa']);
    }

    public function test_simulacion_f29_usa_tasa_por_defecto_cuando_ppm_pct_es_nulo(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $service = app(ImpuestosService::class);

        $resultado = $service->simularF29($empresa->id, 1, 2026);

        $this->assertSame(1.00, $resultado['ppm']['tasa']);
    }

    public function test_monto_ppm_refleja_tasa_configurada(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin(['ppm_pct' => 2.00]);

        $service = app(ImpuestosService::class);

        $resultado = $service->simularF29($empresa->id, 1, 2026);

        $this->assertSame(2.00, $resultado['ppm']['tasa']);
        $montoPpmEsperado = round($resultado['ventas']['neto'] * (2.00 / 100), 2);
        $this->assertEquals($montoPpmEsperado, $resultado['ppm']['monto']);
    }
}
