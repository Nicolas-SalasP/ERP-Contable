<?php

namespace Tests\Feature\Sii\Dte;

use App\Domains\Sii\Models\SiiDteEmitidoMadera;
use App\Domains\Sii\Models\SiiDteEmitidoTraslado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiiDteEmitidoMaderaTest extends TestCase
{
    use RefreshDatabase;

    public function test_madera_se_asocia_a_traslado_uno_a_uno(): void
    {
        $traslado = SiiDteEmitidoTraslado::factory()->create();

        $madera = SiiDteEmitidoMadera::factory()->create([
            'dte_emitido_traslado_id' => $traslado->id,
            'rol_predio_origen'       => '111-222',
            'codigo_plan_conaf'       => 'PLAN-2026-100',
        ]);

        $this->assertNotNull($madera->id);
        $this->assertSame($traslado->id, $madera->dte_emitido_traslado_id);
        $this->assertInstanceOf(SiiDteEmitidoMadera::class, $traslado->fresh()->madera);
    }

    public function test_georeferencias_castean_a_decimal_7(): void
    {
        $madera = SiiDteEmitidoMadera::factory()->create([
            'georef_origen_lat'  => -33.4489000,
            'georef_origen_lng'  => -70.6693000,
            'georef_destino_lat' => -33.5000000,
            'georef_destino_lng' => -70.7000000,
        ]);

        $persistido = $madera->fresh();

        $this->assertSame('-33.4489000', (string) $persistido->georef_origen_lat);
        $this->assertSame('-70.6693000', (string) $persistido->georef_origen_lng);
    }

    public function test_cascade_on_delete_desde_traslado(): void
    {
        $traslado = SiiDteEmitidoTraslado::factory()->create();
        $madera   = SiiDteEmitidoMadera::factory()->create([
            'dte_emitido_traslado_id' => $traslado->id,
        ]);

        $this->assertSame(1, SiiDteEmitidoMadera::where('id', $madera->id)->count());

        $traslado->delete();

        $this->assertSame(0, SiiDteEmitidoMadera::where('id', $madera->id)->count());
    }
}
