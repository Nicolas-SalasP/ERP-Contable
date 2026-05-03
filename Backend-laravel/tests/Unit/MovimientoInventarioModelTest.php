<?php

namespace Tests\Unit;

use App\Domains\Inventario\Models\MovimientoInventario;
use PHPUnit\Framework\TestCase;

class MovimientoInventarioModelTest extends TestCase
{
    public function test_tipos_permitidos_de_movimiento_son_los_esperados(): void
    {
        $this->assertSame([
            'entrada',
            'salida',
            'traspaso',
            'ajuste_positivo',
            'ajuste_negativo',
        ], MovimientoInventario::tiposPermitidos());
    }

    public function test_motivos_permitidos_incluyen_merma_y_correccion_stock(): void
    {
        $motivos = MovimientoInventario::motivosPermitidos();

        $this->assertContains('merma', $motivos);
        $this->assertContains('correccion_stock', $motivos);
        $this->assertContains('traspaso_bodega', $motivos);
    }

    public function test_helpers_de_tipo_funcionan_correctamente(): void
    {
        $entrada = new MovimientoInventario([
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
        ]);

        $salida = new MovimientoInventario([
            'tipo' => MovimientoInventario::TIPO_SALIDA,
        ]);

        $traspaso = new MovimientoInventario([
            'tipo' => MovimientoInventario::TIPO_TRASPASO,
        ]);

        $merma = new MovimientoInventario([
            'tipo' => MovimientoInventario::TIPO_AJUSTE_NEGATIVO,
            'motivo' => MovimientoInventario::MOTIVO_MERMA,
        ]);

        $this->assertTrue($entrada->esEntrada());
        $this->assertTrue($entrada->aumentaStock());

        $this->assertTrue($salida->esSalida());
        $this->assertTrue($salida->disminuyeStock());

        $this->assertTrue($traspaso->esTraspaso());
        $this->assertTrue($traspaso->mueveEntreBodegas());

        $this->assertTrue($merma->esAjusteNegativo());
        $this->assertTrue($merma->esMerma());
    }

    public function test_helpers_de_kardex_reconocen_stock_principal(): void
    {
        $movimientoSalida = new MovimientoInventario([
            'tipo' => MovimientoInventario::TIPO_SALIDA,
            'bodega_origen_id' => 10,
            'stock_origen_antes' => 8,
            'stock_origen_despues' => 5,
        ]);

        $movimientoEntrada = new MovimientoInventario([
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'bodega_destino_id' => 20,
            'stock_destino_antes' => 0,
            'stock_destino_despues' => 3,
        ]);

        $this->assertSame(10, $movimientoSalida->bodegaPrincipalId());
        $this->assertEquals(8.0, (float) $movimientoSalida->stockAntesPrincipal());
        $this->assertEquals(5.0, (float) $movimientoSalida->stockDespuesPrincipal());

        $this->assertSame(20, $movimientoEntrada->bodegaPrincipalId());
        $this->assertEquals(0.0, (float) $movimientoEntrada->stockAntesPrincipal());
        $this->assertEquals(3.0, (float) $movimientoEntrada->stockDespuesPrincipal());
    }
}