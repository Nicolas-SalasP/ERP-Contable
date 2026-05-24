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

class DteXmlBuilderTipo61Test extends TestCase
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

    private function notaCredito(int $codigoRef = 1): SiiDteEmitido
    {
        $dte = SiiDteEmitido::factory()->notaCredito()->create([
            'emisor_acteco'    => 471910,
            'emisor_giro'      => 'Comercio',
            'emisor_direccion' => 'Calle 1',
            'emisor_comuna'    => 'Santiago',
        ]);
        SiiDteEmitidoDetalle::factory()->create([
            'dte_emitido_id' => $dte->id,
            'numero_linea'   => 1,
            'nombre_item'    => 'Anulacion item',
        ]);
        SiiDteEmitidoReferencia::factory()->create([
            'dte_emitido_id'            => $dte->id,
            'numero_linea'              => 1,
            'tipo_documento_referencia' => '33',
            'folio_referencia'          => '500',
            'codigo_referencia'         => $codigoRef,
            'razon_referencia'          => 'Anula factura 33-500',
        ]);

        return $dte->fresh(['detalles', 'referencias', 'traslado.madera']);
    }

    public function test_nc_sin_referencia_lanza_excepcion(): void
    {
        $dte = SiiDteEmitido::factory()->notaCredito()->create([
            'emisor_acteco' => 471910, 'emisor_giro' => 'X', 'emisor_direccion' => 'X', 'emisor_comuna' => 'X',
        ]);
        SiiDteEmitidoDetalle::factory()->create(['dte_emitido_id' => $dte->id, 'numero_linea' => 1]);
        $dte = $dte->fresh(['detalles', 'referencias']);

        $this->expectException(DteIncompletoException::class);
        $this->builder->build($dte);
    }

    public function test_nc_codigo_anula_genera_CodRef_1(): void
    {
        $xml = $this->builder->build($this->notaCredito(1));
        $this->assertStringContainsString('<CodRef>1</CodRef>', $xml);
    }

    public function test_nc_codigo_corrige_texto_genera_CodRef_2(): void
    {
        $xml = $this->builder->build($this->notaCredito(2));
        $this->assertStringContainsString('<CodRef>2</CodRef>', $xml);
    }

    public function test_nc_codigo_corrige_monto_genera_CodRef_3(): void
    {
        $xml = $this->builder->build($this->notaCredito(3));
        $this->assertStringContainsString('<CodRef>3</CodRef>', $xml);
    }
}
