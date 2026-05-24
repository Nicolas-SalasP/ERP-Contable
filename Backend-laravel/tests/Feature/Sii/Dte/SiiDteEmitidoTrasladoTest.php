<?php

namespace Tests\Feature\Sii\Dte;

use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoTraslado;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiiDteEmitidoTrasladoTest extends TestCase
{
    use RefreshDatabase;

    public function test_crear_traslado_para_guia_despacho_52(): void
    {
        $guia = SiiDteEmitido::factory()->guiaDespacho()->create();

        $traslado = SiiDteEmitidoTraslado::factory()->create([
            'dte_emitido_id'     => $guia->id,
            'indicador_traslado' => SiiDteEmitidoTraslado::IND_OPERACION_CONSTITUYE_VENTA,
        ]);

        $this->assertNotNull($traslado->id);
        $this->assertSame(SiiDteEmitido::TIPO_GUIA_DESPACHO, $guia->fresh()->tipo_dte);
        $this->assertSame($guia->id, $traslado->dte_emitido_id);
    }

    public function test_unique_uno_a_uno_con_dte_emitido(): void
    {
        $guia = SiiDteEmitido::factory()->guiaDespacho()->create();

        SiiDteEmitidoTraslado::factory()->create(['dte_emitido_id' => $guia->id]);

        $this->expectException(QueryException::class);

        SiiDteEmitidoTraslado::factory()->create(['dte_emitido_id' => $guia->id]);
    }

    public function test_indicador_traslado_acepta_valores_1_a_8(): void
    {
        $constantes = [
            1 => SiiDteEmitidoTraslado::IND_OPERACION_CONSTITUYE_VENTA,
            2 => SiiDteEmitidoTraslado::IND_VENTA_POR_EFECTUAR,
            3 => SiiDteEmitidoTraslado::IND_CONSIGNACIONES,
            4 => SiiDteEmitidoTraslado::IND_ENTREGA_GRATUITA,
            5 => SiiDteEmitidoTraslado::IND_TRASLADO_INTERNO,
            6 => SiiDteEmitidoTraslado::IND_OTROS_TRASLADOS,
            7 => SiiDteEmitidoTraslado::IND_GUIA_DEVOLUCION,
            8 => SiiDteEmitidoTraslado::IND_TRASLADO_EXPORTACION,
        ];

        foreach ($constantes as $esperado => $constante) {
            $this->assertSame($esperado, $constante);
        }

        // Smoke: crear un traslado por cada indicador.
        foreach ($constantes as $valor) {
            $guia = SiiDteEmitido::factory()->guiaDespacho()->create();
            $traslado = SiiDteEmitidoTraslado::factory()->create([
                'dte_emitido_id'     => $guia->id,
                'indicador_traslado' => $valor,
            ]);

            $this->assertSame($valor, $traslado->fresh()->indicador_traslado);
        }
    }

    public function test_has_one_madera_es_opcional(): void
    {
        $guia     = SiiDteEmitido::factory()->guiaDespacho()->create();
        $traslado = SiiDteEmitidoTraslado::factory()->create(['dte_emitido_id' => $guia->id]);

        $this->assertNull($traslado->madera);
    }
}
