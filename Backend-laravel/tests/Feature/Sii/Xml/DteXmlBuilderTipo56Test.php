<?php

namespace Tests\Feature\Sii\Xml;

use App\Domains\Sii\Exceptions\DteIncompletoException;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoDetalle;
use App\Domains\Sii\Models\SiiDteEmitidoReferencia;
use App\Domains\Sii\Services\Xml\DteXmlBuilder;
use App\Domains\Sii\Services\Xml\DteXsdValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class DteXmlBuilderTipo56Test extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    private DteXmlBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->builder = new DteXmlBuilder(new DteXsdValidator());
    }

    private function notaDebito(bool $conRef = true): SiiDteEmitido
    {
        $dte = SiiDteEmitido::factory()->create([
            'tipo_dte'         => SiiDteEmitido::TIPO_NOTA_DEBITO,
            'emisor_acteco'    => 471910,
            'emisor_giro'      => 'Comercio',
            'emisor_direccion' => 'Calle 1',
            'emisor_comuna'    => 'Santiago',
        ]);
        SiiDteEmitidoDetalle::factory()->create([
            'dte_emitido_id' => $dte->id,
            'numero_linea'   => 1,
            'nombre_item'    => 'Cargo adicional',
        ]);

        if ($conRef) {
            SiiDteEmitidoReferencia::factory()->create([
                'dte_emitido_id'            => $dte->id,
                'numero_linea'              => 1,
                'tipo_documento_referencia' => '33',
                'folio_referencia'          => '1000',
                'codigo_referencia'         => 2,
                'razon_referencia'          => 'Cargo adicional por intereses',
            ]);
        }

        return $dte->fresh(['detalles', 'referencias', 'traslado.madera']);
    }

    public function test_nd_sin_referencia_lanza_excepcion(): void
    {
        $this->expectException(DteIncompletoException::class);
        $this->builder->build($this->notaDebito(false));
    }

    public function test_nd_con_referencia_incluye_bloque_referencia(): void
    {
        $xml = $this->builder->build($this->notaDebito(true));

        $this->assertStringContainsString('<Referencia>', $xml);
        $this->assertStringContainsString('<TpoDocRef>33</TpoDocRef>', $xml);
        $this->assertStringContainsString('<FolioRef>1000</FolioRef>', $xml);
        $this->assertStringContainsString('<CodRef>2</CodRef>', $xml);
    }
}
