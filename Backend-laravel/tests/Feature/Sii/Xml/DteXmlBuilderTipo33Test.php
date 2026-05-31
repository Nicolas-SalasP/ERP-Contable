<?php

namespace Tests\Feature\Sii\Xml;

use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoDetalle;
use App\Domains\Sii\Services\Xml\DteXmlBuilder;
use App\Domains\Sii\Services\Xml\DteXsdValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class DteXmlBuilderTipo33Test extends TestCase
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

    private function dteMinimo(array $overrides = [], int $cantidadLineas = 1): SiiDteEmitido
    {
        $dte = SiiDteEmitido::factory()->factura()->create(array_merge([
            'emisor_acteco'    => 471910,
            'emisor_giro'      => 'Comercio General',
            'emisor_direccion' => 'Calle Test 100',
            'emisor_comuna'    => 'Santiago',
        ], $overrides));

        for ($i = 1; $i <= $cantidadLineas; $i++) {
            SiiDteEmitidoDetalle::factory()->create([
                'dte_emitido_id'  => $dte->id,
                'numero_linea'    => $i,
                'nombre_item'     => "Producto $i",
                'cantidad'        => 1,
                'precio_unitario' => 1000,
                'monto_item'      => 1000,
            ]);
        }

        return $dte->fresh(['detalles', 'referencias', 'traslado.madera']);
    }

    public function test_build_factura_minima_produce_xml_iso8859_1(): void
    {
        $xml = $this->builder->build($this->dteMinimo());

        $this->assertStringStartsWith('<?xml version="1.0" encoding="ISO-8859-1"', $xml);
        $this->assertGreaterThan(1000, strlen($xml));
    }

    public function test_xml_pasa_validacion_xsd(): void
    {
        // build() ya valida internamente; si no lanza, paso.
        $xml = $this->builder->build($this->dteMinimo());
        $this->assertNotEmpty($xml);
    }

    public function test_xml_contiene_nodos_Encabezado_Detalle_TED(): void
    {
        $xml = $this->builder->build($this->dteMinimo());

        $this->assertStringContainsString('<Encabezado>', $xml);
        $this->assertStringContainsString('<Detalle>', $xml);
        $this->assertStringContainsString('<TED version="1.0">', $xml);
        $this->assertStringContainsString('<TmstFirma>', $xml);
    }

    public function test_encabezado_contiene_RUTEmisor_correcto(): void
    {
        $dte = $this->dteMinimo(['emisor_rut' => '76123456-7']);
        $xml = $this->builder->build($dte);

        $this->assertStringContainsString('<RUTEmisor>76123456-7</RUTEmisor>', $xml);
    }

    public function test_detalle_acumula_lineas_correctamente(): void
    {
        $dte = $this->dteMinimo([], 3);
        $xml = $this->builder->build($dte);

        $this->assertSame(3, substr_count($xml, '<Detalle>'));
        $this->assertStringContainsString('<NroLinDet>1</NroLinDet>', $xml);
        $this->assertStringContainsString('<NroLinDet>2</NroLinDet>', $xml);
        $this->assertStringContainsString('<NroLinDet>3</NroLinDet>', $xml);
    }

    public function test_totales_neto_iva_total_se_serializan(): void
    {
        $dte = $this->dteMinimo([
            'monto_neto'  => 10000,
            'iva'         => 1900,
            'monto_total' => 11900,
        ]);
        $xml = $this->builder->build($dte);

        $this->assertStringContainsString('<MntNeto>10000</MntNeto>', $xml);
        $this->assertStringContainsString('<IVA>1900</IVA>', $xml);
        $this->assertStringContainsString('<MntTotal>11900</MntTotal>', $xml);
        $this->assertStringContainsString('<TasaIVA>19.00</TasaIVA>', $xml);
    }

    public function test_ted_tiene_estructura_completa_aunque_firmas_sean_placeholder(): void
    {
        $xml = $this->builder->build($this->dteMinimo());

        $this->assertStringContainsString('<DD>', $xml);
        $this->assertStringContainsString('<CAF version="1.0">', $xml);
        $this->assertStringContainsString('<FRMA algoritmo="SHA1withRSA">', $xml);
        $this->assertStringContainsString('<FRMT algoritmo="SHA1withRSA">', $xml);
    }

    public function test_ds_signature_placeholder_existe_con_algoritmos_correctos(): void
    {
        $xml = $this->builder->build($this->dteMinimo());

        $this->assertStringContainsString('<ds:Signature', $xml);
        $this->assertStringContainsString('xmldsig#rsa-sha1', $xml);
        $this->assertStringContainsString('xml-c14n-20010315', $xml);
        $this->assertStringContainsString('xmldsig#sha1', $xml);
    }
}
