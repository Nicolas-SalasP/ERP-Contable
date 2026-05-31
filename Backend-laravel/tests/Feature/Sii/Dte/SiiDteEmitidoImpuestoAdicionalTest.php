<?php

namespace Tests\Feature\Sii\Dte;

use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoDetalle;
use App\Domains\Sii\Models\SiiDteEmitidoImpuestoAdicional;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiiDteEmitidoImpuestoAdicionalTest extends TestCase
{
    use RefreshDatabase;

    public function test_crear_impuesto_a_nivel_dte_sin_detalle(): void
    {
        $dte = SiiDteEmitido::factory()->create();

        $impuesto = SiiDteEmitidoImpuestoAdicional::factory()->create([
            'dte_emitido_id'         => $dte->id,
            'dte_emitido_detalle_id' => null,
            'codigo_impuesto'        => 23,
            'tasa'                   => 20.50,
            'monto'                  => 1000,
        ]);

        $this->assertNull($impuesto->dte_emitido_detalle_id);
        $this->assertSame(23, $impuesto->codigo_impuesto);
        $this->assertSame('20.50', (string) $impuesto->tasa);
    }

    public function test_crear_impuesto_a_nivel_detalle_con_link(): void
    {
        $detalle = SiiDteEmitidoDetalle::factory()->create();

        $impuesto = SiiDteEmitidoImpuestoAdicional::factory()->create([
            'dte_emitido_id'         => $detalle->dte_emitido_id,
            'dte_emitido_detalle_id' => $detalle->id,
            'codigo_impuesto'        => 27,
            'tasa'                   => 18.00,
            'monto'                  => 500,
        ]);

        $this->assertSame($detalle->id, $impuesto->dte_emitido_detalle_id);
        $this->assertSame($detalle->dte_emitido_id, $impuesto->dte_emitido_id);
    }

    public function test_cascade_on_delete_desde_dte(): void
    {
        $dte      = SiiDteEmitido::factory()->create();
        $impuesto = SiiDteEmitidoImpuestoAdicional::factory()->create([
            'dte_emitido_id' => $dte->id,
        ]);

        $this->assertSame(1, SiiDteEmitidoImpuestoAdicional::where('id', $impuesto->id)->count());

        $dte->delete();

        $this->assertSame(0, SiiDteEmitidoImpuestoAdicional::where('id', $impuesto->id)->count());
    }

    public function test_cascade_on_delete_desde_detalle(): void
    {
        $detalle  = SiiDteEmitidoDetalle::factory()->create();
        $impuesto = SiiDteEmitidoImpuestoAdicional::factory()->create([
            'dte_emitido_id'         => $detalle->dte_emitido_id,
            'dte_emitido_detalle_id' => $detalle->id,
        ]);

        $this->assertSame(1, SiiDteEmitidoImpuestoAdicional::where('id', $impuesto->id)->count());

        $detalle->delete();

        $this->assertSame(0, SiiDteEmitidoImpuestoAdicional::where('id', $impuesto->id)->count());
    }

    public function test_monto_es_obligatorio_pero_tasa_puede_ser_null(): void
    {
        $dte = SiiDteEmitido::factory()->create();

        // tasa NULL valido (impuestos especificos por unidad).
        $impuesto = SiiDteEmitidoImpuestoAdicional::factory()->create([
            'dte_emitido_id'  => $dte->id,
            'codigo_impuesto' => 28, // gasolina, tasa por unidad
            'tasa'            => null,
            'monto'           => 5000,
        ]);

        $this->assertNull($impuesto->tasa);
        $this->assertSame('5000.00', (string) $impuesto->monto);
    }
}
