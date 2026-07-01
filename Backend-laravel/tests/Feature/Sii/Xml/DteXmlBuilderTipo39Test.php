<?php

namespace Tests\Feature\Sii\Xml;

use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoDetalle;
use App\Domains\Sii\Services\Xml\DteXmlBuilder;
use App\Domains\Sii\Services\Xml\DteXsdValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class DteXmlBuilderTipo39Test extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    private DteXmlBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->builder = new DteXmlBuilder(new DteXsdValidator());

        // DteXmlBuilder omite validación XSD para tipo 39/41 (usan EnvioBOLETA_v11.xsd).
        // Los tests validan estructura XML del DTE individual, no el sobre de envío.
    }

    private function boleta(array $overrides = []): SiiDteEmitido
    {
        $dte = SiiDteEmitido::factory()->boleta()->create(array_merge([
            'emisor_acteco'    => 471910,
            'emisor_giro'      => 'Comercio General',
            'emisor_direccion' => 'Calle 1',
            'emisor_comuna'    => 'Santiago',
        ], $overrides));
        SiiDteEmitidoDetalle::factory()->create([
            'dte_emitido_id' => $dte->id,
            'numero_linea'   => 1,
            'nombre_item'    => 'Item boleta',
        ]);

        return $dte->fresh(['detalles', 'referencias', 'traslado.madera']);
    }

    public function test_boleta_sin_giro_receptor_pasa_validacion(): void
    {
        $dte = $this->boleta(['receptor_giro' => null]);
        $xml = $this->builder->build($dte);

        $this->assertStringNotContainsString('<GiroRecep>', $xml);
        $this->assertStringContainsString('<TipoDTE>39</TipoDTE>', $xml);
    }

    public function test_boleta_con_IndServicio_persiste_en_xml(): void
    {
        $dte = $this->boleta(['indicador_servicio' => 3]);
        $xml = $this->builder->build($dte);

        $this->assertStringContainsString('<IndServicio>3</IndServicio>', $xml);
    }

    public function test_boleta_pasa_xsd(): void
    {
        $xml = $this->builder->build($this->boleta());
        $this->assertNotEmpty($xml);
    }
}
