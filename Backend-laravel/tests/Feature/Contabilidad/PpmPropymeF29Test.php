<?php

namespace Tests\Feature\Contabilidad;

use App\Domains\Contabilidad\Models\TasaPpmPropyme;
use App\Domains\Contabilidad\Services\ImpuestosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class PpmPropymeF29Test extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    private function insertarDte(int $empresaId, int $tipoDte, int $folio, string $fechaEmision, float $montoNeto, string $estado = 'ACEPTADO'): void
    {
        DB::table('sii_dte_emitido')->insert([
            'empresa_id'            => $empresaId,
            'tipo_dte'              => $tipoDte,
            'folio'                 => $folio,
            'fecha_emision'         => $fechaEmision,
            'estado'                => $estado,
            'monto_neto'            => $montoNeto,
            'monto_exento'          => 0,
            'monto_total'           => $montoNeto * 1.19,
            'emisor_rut'            => '76000001-K',
            'emisor_razon_social'   => 'Empresa Test SA',
            'receptor_rut'          => '11111111-1',
            'receptor_razon_social' => 'Cliente Test',
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);
    }

    public function test_f29_empresa_14d8_usa_tasa_ppm_propyme_rebajada(): void
    {
        $empresa = $this->crearEmpresa(['regimen_tributario' => '14_D8']);

        TasaPpmPropyme::create([
            'anio'                   => 2026,
            'tasa_base_pct'          => 0.125,
            'tasa_sobre_50000uf_pct' => 0.250,
        ]);

        // Venta en enero 2026 para que simularF29 tenga base sobre la que calcular PPM
        $this->insertarDte($empresa->id, 33, 1, '2026-01-10', 500_000.0);

        $service = app(ImpuestosService::class);
        $resultado = $service->simularF29($empresa->id, 1, 2026);

        // Sin DTEs en 2025, los ingresos año anterior son 0 < umbral → usa tasa_base
        $this->assertEquals(0.125, $resultado['ppm']['tasa']);
    }

    public function test_f29_empresa_14d8_sobre_50000uf_usa_tasa_mayor(): void
    {
        $empresa = $this->crearEmpresa(['regimen_tributario' => '14_D8']);

        TasaPpmPropyme::create([
            'anio'                   => 2026,
            'tasa_base_pct'          => 0.125,
            'tasa_sobre_50000uf_pct' => 0.250,
        ]);

        // Ingresos 2025 > 1.900.000.000 → supera umbral 50.000 UF
        $this->insertarDte($empresa->id, 33, 1, '2025-06-01', 1_000_000_000.0);
        $this->insertarDte($empresa->id, 33, 2, '2025-06-15', 1_000_000_000.0);

        // Venta en enero 2026 para que simularF29 tenga base
        $this->insertarDte($empresa->id, 33, 3, '2026-01-10', 500_000.0);

        $service = app(ImpuestosService::class);
        $resultado = $service->simularF29($empresa->id, 1, 2026);

        $this->assertEquals(0.25, $resultado['ppm']['tasa']);
    }

    public function test_f29_empresa_no_propyme_mantiene_ppm_normal(): void
    {
        $empresa = $this->crearEmpresa([
            'regimen_tributario' => '14_D3',
            'ppm_pct'            => 1.0,
        ]);

        // Venta en enero 2026
        $this->insertarDte($empresa->id, 33, 1, '2026-01-10', 500_000.0);

        $service = app(ImpuestosService::class);
        $resultado = $service->simularF29($empresa->id, 1, 2026);

        // No es 14_D8, debe usar ppm_pct de la empresa sin branching Propyme
        $this->assertEquals(1.0, $resultado['ppm']['tasa']);
    }
}
