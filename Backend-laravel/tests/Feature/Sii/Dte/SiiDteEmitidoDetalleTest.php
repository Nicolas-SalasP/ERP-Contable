<?php

namespace Tests\Feature\Sii\Dte;

use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoDetalle;
use App\Domains\Sii\Models\SiiDteEmitidoImpuestoAdicional;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiiDteEmitidoDetalleTest extends TestCase
{
    use RefreshDatabase;

    public function test_crear_detalle_valido_se_persiste(): void
    {
        $detalle = SiiDteEmitidoDetalle::factory()->create([
            'numero_linea'    => 1,
            'nombre_item'     => 'Producto Linea 1',
            'cantidad'        => 2.5,
            'precio_unitario' => 1000.0,
            'monto_item'      => 2500.0,
        ]);

        $this->assertNotNull($detalle->id);
        $this->assertSame('Producto Linea 1', $detalle->nombre_item);
    }

    public function test_unique_compuesto_dte_numero_linea_bloquea_duplicados(): void
    {
        $dte = SiiDteEmitido::factory()->create();

        SiiDteEmitidoDetalle::factory()->create([
            'dte_emitido_id' => $dte->id,
            'numero_linea'   => 1,
        ]);

        $this->expectException(QueryException::class);

        SiiDteEmitidoDetalle::factory()->create([
            'dte_emitido_id' => $dte->id,
            'numero_linea'   => 1,
        ]);
    }

    public function test_cascade_on_delete_desde_dte_emitido(): void
    {
        $dte = SiiDteEmitido::factory()->create();

        SiiDteEmitidoDetalle::factory()->create([
            'dte_emitido_id' => $dte->id,
            'numero_linea'   => 1,
        ]);
        SiiDteEmitidoDetalle::factory()->create([
            'dte_emitido_id' => $dte->id,
            'numero_linea'   => 2,
        ]);

        $this->assertSame(2, SiiDteEmitidoDetalle::where('dte_emitido_id', $dte->id)->count());

        $dte->delete();

        $this->assertSame(0, SiiDteEmitidoDetalle::where('dte_emitido_id', $dte->id)->count());
    }

    public function test_belongs_to_factura_detalle_nullable_funciona(): void
    {
        $detalle = SiiDteEmitidoDetalle::factory()->create(['factura_detalle_id' => null]);

        $this->assertNull($detalle->facturaDetalle);
        $this->assertNull($detalle->factura_detalle_id);
    }

    public function test_has_many_impuestos_adicionales_funciona(): void
    {
        $detalle = SiiDteEmitidoDetalle::factory()->create();

        SiiDteEmitidoImpuestoAdicional::factory()->create([
            'dte_emitido_id'         => $detalle->dte_emitido_id,
            'dte_emitido_detalle_id' => $detalle->id,
            'codigo_impuesto'        => 23,
            'tasa'                   => 20.50,
            'monto'                  => 500,
        ]);

        $this->assertCount(1, $detalle->fresh()->impuestosAdicionales);
        $this->assertSame(23, $detalle->fresh()->impuestosAdicionales->first()->codigo_impuesto);
    }
}
