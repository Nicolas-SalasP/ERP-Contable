<?php

namespace Tests\Feature\Sii\Dte;

use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoReferencia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiiDteEmitidoReferenciaTest extends TestCase
{
    use RefreshDatabase;

    public function test_crear_referencia_a_factura_original_para_nota_credito(): void
    {
        $nc = SiiDteEmitido::factory()->notaCredito()->create();

        $referencia = SiiDteEmitidoReferencia::factory()->create([
            'dte_emitido_id'            => $nc->id,
            'numero_linea'              => 1,
            'tipo_documento_referencia' => '33',
            'folio_referencia'          => '100',
            'codigo_referencia'         => SiiDteEmitidoReferencia::CODIGO_ANULA,
            'razon_referencia'          => 'Anula factura 33-100',
        ]);

        $this->assertNotNull($referencia->id);
        $this->assertSame($nc->id, $referencia->dte_emitido_id);
        $this->assertSame('33', $referencia->tipo_documento_referencia);
        $this->assertSame(SiiDteEmitidoReferencia::CODIGO_ANULA, $referencia->codigo_referencia);
    }

    public function test_codigo_referencia_acepta_anula_corrige_texto_o_monto(): void
    {
        $this->assertSame(1, SiiDteEmitidoReferencia::CODIGO_ANULA);
        $this->assertSame(2, SiiDteEmitidoReferencia::CODIGO_CORRIGE_TEXTO);
        $this->assertSame(3, SiiDteEmitidoReferencia::CODIGO_CORRIGE_MONTO);

        $nc = SiiDteEmitido::factory()->notaCredito()->create();

        foreach ([1, 2, 3] as $i => $codigo) {
            $ref = SiiDteEmitidoReferencia::factory()->create([
                'dte_emitido_id'    => $nc->id,
                'numero_linea'      => $i + 1,
                'codigo_referencia' => $codigo,
            ]);

            $this->assertSame($codigo, $ref->codigo_referencia);
        }
    }

    public function test_cascade_on_delete(): void
    {
        $dte = SiiDteEmitido::factory()->notaCredito()->create();

        SiiDteEmitidoReferencia::factory()->create([
            'dte_emitido_id' => $dte->id,
            'numero_linea'   => 1,
        ]);

        $this->assertSame(1, SiiDteEmitidoReferencia::where('dte_emitido_id', $dte->id)->count());

        $dte->delete();

        $this->assertSame(0, SiiDteEmitidoReferencia::where('dte_emitido_id', $dte->id)->count());
    }
}
