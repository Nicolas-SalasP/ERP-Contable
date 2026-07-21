<?php

namespace Tests\Feature\Sii\Xml\SetDte;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoDetalle;
use App\Domains\Sii\Services\Xml\DteSigner;
use App\Domains\Sii\Services\Xml\DteXmlBuilder;
use App\Domains\Sii\Services\Xml\EnvioDteXsdValidator;
use App\Domains\Sii\Services\Xml\SetDte\SetDteBuilder;
use App\Domains\Sii\Services\Xml\SetDte\SetDteSigner;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Concerns\GeneraCertificadoParaTests;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class SetDteBuilderBoletaTest extends TestCase
{
    use GeneraCertificadoParaTests;
    use PreparaEntornoBase;
    use RefreshDatabase;

    private SetDteBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        if ($this->localizarOpensslCnf() === null) {
            $this->markTestSkipped('openssl.cnf no encontrado.');
        }

        $this->builder = app(SetDteBuilder::class);
    }

    /**
     * @return array{0: Empresa, 1: SiiDteEmitido, 2: string} [empresa, dte, xmlFirmado]
     */
    private function boletaFirmada(string $rut = '76555444-3', int $folio = 10): array
    {
        $empresa = Empresa::create([
            'rut' => $rut,
            'razon_social' => 'EMPRESA BOLETA',
            'resolucion_sii_numero' => 80,
            'resolucion_sii_fecha' => '2024-08-22',
            'ambiente_sii' => 'certificacion',
        ]);
        $this->crearCertActivoParaEmpresa($empresa, 'OPERADOR '.$rut);
        [$caf] = $this->crearCafActivoParaEmpresa($empresa, 39, 1, 50);

        $dte = SiiDteEmitido::factory()->boleta()->create([
            'empresa_id' => $empresa->id,
            'emisor_rut' => $rut,
            'emisor_giro' => 'X',
            'emisor_direccion' => 'X',
            'emisor_comuna' => 'X',
            'folio' => $folio,
            'indicador_servicio' => 3,
            'monto_neto' => 1000,
            'iva' => 190,
            'monto_total' => 1190,
        ]);
        SiiDteEmitidoDetalle::factory()->create([
            'dte_emitido_id' => $dte->id,
            'numero_linea' => 1,
            'nombre_item' => 'X',
            'cantidad' => 1,
            'precio_unitario' => 1000,
            'monto_item' => 1000,
        ]);
        $dte = $dte->fresh(['detalles', 'referencias', 'traslado.madera', 'impuestosAdicionales']);

        $xmlConTed = app(DteXmlBuilder::class)->build($dte, $caf);
        $xmlFirmado = app(DteSigner::class)->firmar($xmlConTed, $empresa);

        return [$empresa, $dte, $xmlFirmado];
    }

    public function test_envuelve_una_boleta_en_envio_bolet_a_no_envio_dte(): void
    {
        [$empresa, $dte, $xml] = $this->boletaFirmada();

        $envio = $this->builder->build($empresa, [['dte' => $dte, 'xml' => $xml]]);

        $this->assertStringContainsString('<EnvioBOLETA', $envio);
        $this->assertStringNotContainsString('<EnvioDTE', $envio);
        $this->assertStringContainsString('xsi:schemaLocation="http://www.sii.cl/SiiDte EnvioBOLETA_v11.xsd"', $envio);
    }

    public function test_lanza_logic_exception_si_mezcla_boleta_con_factura(): void
    {
        [$empresa, $dteBoleta, $xmlBoleta] = $this->boletaFirmada('76222333-4', 20);

        [$cafFactura] = $this->crearCafActivoParaEmpresa($empresa, 33, 1, 50);
        $dteFactura = SiiDteEmitido::factory()->factura()->create([
            'empresa_id' => $empresa->id,
            'emisor_rut' => $empresa->rut,
            'emisor_acteco' => 471910,
            'emisor_giro' => 'X',
            'emisor_direccion' => 'X',
            'emisor_comuna' => 'X',
            'folio' => 30,
            'monto_neto' => 2000,
            'iva' => 380,
            'monto_total' => 2380,
        ]);
        SiiDteEmitidoDetalle::factory()->create([
            'dte_emitido_id' => $dteFactura->id,
            'numero_linea' => 1,
            'nombre_item' => 'Y',
            'cantidad' => 1,
            'precio_unitario' => 2000,
            'monto_item' => 2000,
        ]);
        $dteFactura = $dteFactura->fresh(['detalles', 'referencias', 'traslado.madera', 'impuestosAdicionales']);
        $xmlFactura = app(DteSigner::class)->firmar(
            app(DteXmlBuilder::class)->build($dteFactura, $cafFactura),
            $empresa
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('mezclar boletas');

        $this->builder->build($empresa, [
            ['dte' => $dteBoleta, 'xml' => $xmlBoleta],
            ['dte' => $dteFactura, 'xml' => $xmlFactura],
        ]);
    }

    public function test_set_dte_signer_firma_el_sobre_envio_bolet_a_sin_romper(): void
    {
        [$empresa, $dte, $xml] = $this->boletaFirmada('76333444-5', 40);

        $sinFirma = $this->builder->build($empresa, [['dte' => $dte, 'xml' => $xml]]);
        $firmado = app(SetDteSigner::class)->firmar($sinFirma, $empresa);

        $dom = new DOMDocument;
        $dom->loadXML($firmado);
        $x = new DOMXPath($dom);
        $x->registerNamespace('sii', 'http://www.sii.cl/SiiDte');
        $x->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        $this->assertSame('EnvioBOLETA', $dom->documentElement?->localName);
        $this->assertSame(1, $x->query('//sii:EnvioBOLETA/ds:Signature')->length);
    }

    public function test_envio_dte_xsd_validator_valida_sobre_envio_bolet_a_contra_su_propio_xsd(): void
    {
        [$empresa, $dte, $xml] = $this->boletaFirmada('76444555-6', 50);

        $sinFirma = $this->builder->build($empresa, [['dte' => $dte, 'xml' => $xml]]);
        $firmado = app(SetDteSigner::class)->firmar($sinFirma, $empresa);

        // No debe lanzar: EnvioDteXsdValidator debe elegir EnvioBOLETA_v11.xsd al ver la raiz EnvioBOLETA.
        app(EnvioDteXsdValidator::class)->validar($firmado);
        $this->assertTrue(true);
    }
}
