<?php

namespace Tests\Feature\Sii\Xml\SetDte;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoDetalle;
use App\Domains\Sii\Services\Xml\DteSigner;
use App\Domains\Sii\Services\Xml\DteXmlBuilder;
use App\Domains\Sii\Services\Xml\SetDte\SetDteBuilder;
use App\Domains\Sii\Support\RutHelper;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Concerns\GeneraCertificadoParaTests;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class SetDteBuilderTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;
    use GeneraCertificadoParaTests;

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
     * Crea un escenario "empresa+cert+caf+dte firmado" listo para empaquetar.
     *
     * @return array{0: Empresa, 1: SiiDteEmitido, 2: string} [empresa, dte, xmlFirmado]
     */
    private function dteFirmado(string $rut = '76555444-3', int $folio = 10): array
    {
        $empresa = Empresa::create([
            'rut'                   => $rut,
            'razon_social'          => 'EMPRESA SET',
            'resolucion_sii_numero' => 80,
            'resolucion_sii_fecha'  => '2024-08-22',
            'ambiente_sii'          => 'certificacion',
        ]);
        $this->crearCertActivoParaEmpresa($empresa, 'OPERADOR ' . $rut);
        [$caf] = $this->crearCafActivoParaEmpresa($empresa, 33, 1, 50);

        $dte = SiiDteEmitido::factory()->factura()->create([
            'empresa_id'        => $empresa->id,
            'emisor_rut'        => $rut,
            'emisor_acteco'     => 471910,
            'emisor_giro'       => 'X',
            'emisor_direccion'  => 'X',
            'emisor_comuna'     => 'X',
            'folio'             => $folio,
            'monto_neto'        => 1000,
            'iva'               => 190,
            'monto_total'       => 1190,
        ]);
        SiiDteEmitidoDetalle::factory()->create([
            'dte_emitido_id'  => $dte->id,
            'numero_linea'    => 1,
            'nombre_item'     => 'X',
            'cantidad'        => 1,
            'precio_unitario' => 1000,
            'monto_item'      => 1000,
        ]);
        $dte = $dte->fresh(['detalles', 'referencias', 'traslado.madera', 'impuestosAdicionales']);

        $xmlConTed = app(DteXmlBuilder::class)->build($dte, $caf);
        $xmlFirmado = app(DteSigner::class)->firmar($xmlConTed, $empresa);

        return [$empresa, $dte, $xmlFirmado];
    }

    public function test_envuelve_un_DTE_en_EnvioDTE_SetDTE(): void
    {
        [$empresa, $dte, $xml] = $this->dteFirmado();

        $envio = $this->builder->build($empresa, [['dte' => $dte, 'xml' => $xml]]);

        $this->assertStringContainsString('<EnvioDTE', $envio);
        $this->assertStringContainsString('<SetDTE ID="SetDocDTE">', $envio);
        $this->assertStringContainsString('<Caratula version="1.0">', $envio);
    }

    public function test_envuelve_multiples_DTE_en_un_solo_SetDTE(): void
    {
        [$empresa, $dte1, $xml1] = $this->dteFirmado('76777777-7', 11);

        // Reutilizo la misma empresa para el segundo DTE
        [$caf]  = $this->crearCafActivoParaEmpresa($empresa, 33, 100, 200);
        $dte2 = SiiDteEmitido::factory()->factura()->create([
            'empresa_id'        => $empresa->id,
            'emisor_rut'        => $empresa->rut,
            'emisor_acteco'     => 471910,
            'emisor_giro'       => 'X',
            'emisor_direccion'  => 'X',
            'emisor_comuna'     => 'X',
            'folio'             => 100,
            'monto_neto'        => 2000,
            'iva'               => 380,
            'monto_total'       => 2380,
        ]);
        SiiDteEmitidoDetalle::factory()->create([
            'dte_emitido_id'  => $dte2->id,
            'numero_linea'    => 1,
            'nombre_item'     => 'Y',
            'cantidad'        => 1,
            'precio_unitario' => 2000,
            'monto_item'      => 2000,
        ]);
        $dte2 = $dte2->fresh(['detalles', 'referencias', 'traslado.madera', 'impuestosAdicionales']);
        $xml2 = app(DteSigner::class)->firmar(
            app(DteXmlBuilder::class)->build($dte2, $caf),
            $empresa
        );

        $envio = $this->builder->build($empresa, [
            ['dte' => $dte1, 'xml' => $xml1],
            ['dte' => $dte2, 'xml' => $xml2],
        ]);

        $dom = new DOMDocument();
        $dom->loadXML($envio);
        $x = new DOMXPath($dom);
        $x->registerNamespace('sii', 'http://www.sii.cl/SiiDte');

        $this->assertSame(2, $x->query('//sii:SetDTE/sii:DTE')->length);
    }

    public function test_lanza_LogicException_si_emisores_no_coinciden(): void
    {
        [$empresaA, $dteA, $xmlA] = $this->dteFirmado('76111111-1', 10);
        [, $dteB, $xmlB] = $this->dteFirmado('77777777-7', 20);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('emisores distintos');

        $this->builder->build($empresaA, [
            ['dte' => $dteA, 'xml' => $xmlA],
            ['dte' => $dteB, 'xml' => $xmlB],
        ]);
    }

    public function test_SetDTE_tiene_atributo_ID_SetDocDTE(): void
    {
        [$empresa, $dte, $xml] = $this->dteFirmado();
        $envio = $this->builder->build($empresa, [['dte' => $dte, 'xml' => $xml]]);

        $dom = new DOMDocument();
        $dom->loadXML($envio);
        $x = new DOMXPath($dom);
        $x->registerNamespace('sii', 'http://www.sii.cl/SiiDte');

        $this->assertSame('SetDocDTE', $x->evaluate('string(/sii:EnvioDTE/sii:SetDTE/@ID)'));
        $this->assertSame('SetDocDTE', SetDteBuilder::SET_DTE_ID);
    }

    public function test_namespaces_xmlns_xsi_ds_estan_declarados_en_root(): void
    {
        [$empresa, $dte, $xml] = $this->dteFirmado();
        $envio = $this->builder->build($empresa, [['dte' => $dte, 'xml' => $xml]]);

        $this->assertStringContainsString('xmlns="http://www.sii.cl/SiiDte"', $envio);
        $this->assertStringContainsString('xmlns:ds="http://www.w3.org/2000/09/xmldsig#"', $envio);
        $this->assertStringContainsString('xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"', $envio);
        $this->assertStringContainsString('xsi:schemaLocation="http://www.sii.cl/SiiDte EnvioDTE_v10.xsd"', $envio);
    }
}
