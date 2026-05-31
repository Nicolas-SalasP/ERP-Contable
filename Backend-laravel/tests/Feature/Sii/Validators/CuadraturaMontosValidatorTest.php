<?php

namespace Tests\Feature\Sii\Validators;

use App\Domains\Sii\Exceptions\DteIncompletoException;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoDetalle;
use App\Domains\Sii\Services\Validators\CuadraturaMontosValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class CuadraturaMontosValidatorTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    private CuadraturaMontosValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->validator = new CuadraturaMontosValidator();
    }

    private function dteConDetalles(array $overrides, array $detalles): SiiDteEmitido
    {
        $dte = SiiDteEmitido::factory()->create(array_merge([
            'tipo_dte'     => 33,
            'monto_neto'   => 0,
            'monto_exento' => 0,
            'tasa_iva'     => 19.00,
            'iva'          => 0,
            'monto_total'  => 0,
        ], $overrides));

        foreach ($detalles as $i => $det) {
            SiiDteEmitidoDetalle::factory()->create(array_merge([
                'dte_emitido_id' => $dte->id,
                'numero_linea'   => $i + 1,
            ], $det));
        }

        return $dte->fresh(['detalles']);
    }

    public function test_factura_con_montos_cuadrados_no_lanza(): void
    {
        $dte = $this->dteConDetalles(
            ['tipo_dte' => 33, 'monto_neto' => 1000, 'iva' => 190, 'monto_total' => 1190],
            [['monto_item' => 1000, 'exento' => false]]
        );

        $this->validator->validar($dte);
        $this->assertTrue(true, 'No lanzo excepcion → OK.');
    }

    public function test_lanza_si_suma_detalles_no_es_MntNeto(): void
    {
        $dte = $this->dteConDetalles(
            ['tipo_dte' => 33, 'monto_neto' => 1000, 'iva' => 190, 'monto_total' => 1190],
            [
                ['monto_item' => 600, 'exento' => false],
                ['monto_item' => 300, 'exento' => false],  // Suma = 900, no 1000
            ]
        );

        try {
            $this->validator->validar($dte);
            $this->fail('Debio lanzar DteIncompletoException');
        } catch (DteIncompletoException $e) {
            $this->assertSame(DteIncompletoException::MOTIVO_MONTOS_NO_CUADRAN, $e->motivo);
            $this->assertArrayHasKey('monto_neto', $e->discrepancias);
            $this->assertSame(900, $e->discrepancias['monto_neto']['esperado']);
            $this->assertSame(1000, $e->discrepancias['monto_neto']['real']);
        }
    }

    public function test_lanza_si_MntNeto_x_TasaIVA_no_es_MntIVA(): void
    {
        $dte = $this->dteConDetalles(
            ['tipo_dte' => 33, 'monto_neto' => 1000, 'iva' => 500, 'monto_total' => 1500],
            [['monto_item' => 1000, 'exento' => false]]
        );

        try {
            $this->validator->validar($dte);
            $this->fail('Debio lanzar DteIncompletoException');
        } catch (DteIncompletoException $e) {
            $this->assertArrayHasKey('iva', $e->discrepancias);
            $this->assertSame(190, $e->discrepancias['iva']['esperado']);
            $this->assertSame(500, $e->discrepancias['iva']['real']);
        }
    }

    public function test_tolerancia_de_1_peso_en_IVA_no_lanza(): void
    {
        // 525 × 0.19 = 99.75 → entero 100; aceptamos 99 (±1).
        $dte = $this->dteConDetalles(
            ['tipo_dte' => 33, 'monto_neto' => 525, 'iva' => 99, 'monto_total' => 624],
            [['monto_item' => 525, 'exento' => false]]
        );

        $this->validator->validar($dte);
        $this->assertTrue(true);
    }

    public function test_tolerancia_excedida_lanza(): void
    {
        // 525 × 0.19 = 99.75 → esperado 100. Real = 97 → delta 3 > 1.
        $dte = $this->dteConDetalles(
            ['tipo_dte' => 33, 'monto_neto' => 525, 'iva' => 97, 'monto_total' => 622],
            [['monto_item' => 525, 'exento' => false]]
        );

        $this->expectException(DteIncompletoException::class);
        $this->validator->validar($dte);
    }

    public function test_factura_exenta_no_requiere_TasaIVA_ni_IVA(): void
    {
        $dte = $this->dteConDetalles(
            ['tipo_dte' => 34, 'monto_neto' => 0, 'iva' => 0, 'monto_exento' => 1000, 'monto_total' => 1000],
            [['monto_item' => 1000, 'exento' => true]]
        );

        $this->validator->validar($dte);
        $this->assertTrue(true);
    }

    public function test_lanza_si_MntTotal_no_suma_componentes(): void
    {
        $dte = $this->dteConDetalles(
            ['tipo_dte' => 33, 'monto_neto' => 1000, 'iva' => 190, 'monto_total' => 9999],
            [['monto_item' => 1000, 'exento' => false]]
        );

        try {
            $this->validator->validar($dte);
            $this->fail('Debio lanzar DteIncompletoException');
        } catch (DteIncompletoException $e) {
            $this->assertArrayHasKey('monto_total', $e->discrepancias);
            $this->assertSame(1190, $e->discrepancias['monto_total']['esperado']);
            $this->assertSame(9999, $e->discrepancias['monto_total']['real']);
        }
    }
}
