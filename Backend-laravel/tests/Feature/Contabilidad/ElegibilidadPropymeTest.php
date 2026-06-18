<?php

namespace Tests\Feature\Contabilidad;

use App\Domains\Contabilidad\Services\Propyme\ElegibilidadPropymeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class ElegibilidadPropymeTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected ElegibilidadPropymeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->service = new ElegibilidadPropymeService();
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

    public function test_detecta_empresa_que_supera_75000uf_promedio(): void
    {
        $empresa = $this->crearEmpresa();

        // Insertar ventas para los 3 años anteriores a 2026 (2023, 2024, 2025)
        // con promedio > 2.850.000.000
        // Usamos 1.200.000.000 por año × 3 = promedio 3.600.000.000 > límite
        $folio = 1;
        foreach ([2023, 2024, 2025] as $anio) {
            $this->insertarDte($empresa->id, 33, $folio++, "$anio-06-01", 1_200_000_000.0);
            $this->insertarDte($empresa->id, 33, $folio++, "$anio-09-01", 1_200_000_000.0);
            $this->insertarDte($empresa->id, 33, $folio++, "$anio-12-01", 1_200_000_000.0);
        }

        $resultado = $this->service->verificar($empresa->id, 2026);

        $this->assertFalse($resultado['elegible']);
        $this->assertGreaterThan(2_850_000_000, $resultado['promedio_pesos']);
    }

    public function test_empresa_dentro_del_limite_es_elegible(): void
    {
        $empresa = $this->crearEmpresa();

        // Ventas de 500.000.000 por año × 3 = promedio 500.000.000 < 2.850.000.000
        $folio = 1;
        foreach ([2023, 2024, 2025] as $anio) {
            $this->insertarDte($empresa->id, 33, $folio++, "$anio-06-01", 500_000_000.0);
        }

        $resultado = $this->service->verificar($empresa->id, 2026);

        $this->assertTrue($resultado['elegible']);
        $this->assertLessThanOrEqual(2_850_000_000, $resultado['promedio_pesos']);
        $this->assertArrayHasKey('ingresos_por_anio', $resultado);
        $this->assertCount(3, $resultado['ingresos_por_anio']);
    }
}
