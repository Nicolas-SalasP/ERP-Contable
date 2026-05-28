<?php

namespace Tests\Feature\Sii\Xml\SetDte;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoDetalle;
use App\Domains\Sii\Services\Xml\DteSigner;
use App\Domains\Sii\Services\Xml\DteXmlBuilder;
use App\Domains\Sii\Services\Xml\SetDte\SetDteBuilder;
use App\Domains\Sii\Services\Xml\SetDte\SetDteSigner;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use Tests\Concerns\GeneraCertificadoParaTests;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class SetDteSignerTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;
    use GeneraCertificadoParaTests;

    private const NS_SII  = 'http://www.sii.cl/SiiDte';
    private const NS_DSIG = 'http://www.w3.org/2000/09/xmldsig#';

    private SetDteSigner $signer;
    private SetDteBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        if ($this->localizarOpensslCnf() === null) {
            $this->markTestSkipped('openssl.cnf no encontrado.');
        }

        $this->signer  = app(SetDteSigner::class);
        $this->builder = app(SetDteBuilder::class);
    }

    /**
     * @return array{0: Empresa, 1: string, 2: string} [empresa, certPem, xmlSetSinFirmar]
     */
    private function escenarioSetDte(): array
    {
        $empresa = Empresa::create([
            'rut'                   => '76321321-K',
            'razon_social'          => 'EMPRESA SET SIGNER',
            'resolucion_sii_numero' => 80,
            'resolucion_sii_fecha'  => '2024-08-22',
            'ambiente_sii'          => 'certificacion',
        ]);
        [, $certPem] = $this->crearCertActivoParaEmpresa($empresa, 'OPERADOR ' . $empresa->rut);
        [$caf]       = $this->crearCafActivoParaEmpresa($empresa, 33, 1, 50);

        $dte = SiiDteEmitido::factory()->factura()->create([
            'empresa_id'        => $empresa->id,
            'emisor_rut'        => $empresa->rut,
            'emisor_acteco'     => 471910,
            'emisor_giro'       => 'X',
            'emisor_direccion'  => 'X',
            'emisor_comuna'     => 'X',
            'folio'             => 10,
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

        $xmlFirmado = app(DteSigner::class)->firmar(
            app(DteXmlBuilder::class)->build($dte, $caf),
            $empresa
        );

        $setSinFirma = $this->builder->build($empresa, [['dte' => $dte, 'xml' => $xmlFirmado]]);

        return [$empresa, $certPem, $setSinFirma];
    }

    private function xpath(string $xml): DOMXPath
    {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->loadXML($xml);
        $x = new DOMXPath($dom);
        $x->registerNamespace('sii', self::NS_SII);
        $x->registerNamespace('ds',  self::NS_DSIG);
        return $x;
    }

    public function test_inserta_ds_signature_como_hermano_de_SetDTE_dentro_de_EnvioDTE(): void
    {
        [$empresa, , $setSinFirma] = $this->escenarioSetDte();
        $firmado = $this->signer->firmar($setSinFirma, $empresa);

        $x = $this->xpath($firmado);
        $this->assertSame(1, $x->query('/sii:EnvioDTE/sii:SetDTE')->length);
        $this->assertSame(1, $x->query('/sii:EnvioDTE/ds:Signature')->length);
    }

    public function test_Reference_URI_apunta_a_SetDocDTE(): void
    {
        [$empresa, , $setSinFirma] = $this->escenarioSetDte();
        $firmado = $this->signer->firmar($setSinFirma, $empresa);

        $x = $this->xpath($firmado);
        $uri = $x->evaluate('string(/sii:EnvioDTE/ds:Signature/ds:SignedInfo/ds:Reference/@URI)');
        $this->assertSame('#SetDocDTE', $uri);
    }

    public function test_firma_se_verifica_round_trip(): void
    {
        [$empresa, $certPem, $setSinFirma] = $this->escenarioSetDte();
        $firmado = $this->signer->firmar($setSinFirma, $empresa);

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->loadXML($firmado);
        $setNode = $dom->getElementsByTagName('SetDTE')->item(0);
        $setNode->setIdAttribute('ID', true);

        $dsig = new XMLSecurityDSig();
        $dsig->idKeys = ['ID'];
        // Hay 2 Signatures: la del Documento y la del SetDTE. La del SetDTE
        // es la ultima (orden documental).
        $count = $dom->getElementsByTagNameNS(self::NS_DSIG, 'Signature')->length;
        $dsig->locateSignature($dom, $count - 1);
        $dsig->canonicalizeSignedInfo();

        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, ['type' => 'public']);
        $key->loadKey($certPem, false, true);

        $this->assertSame(1, $dsig->verify($key));
        $this->assertTrue($dsig->validateReference());
    }

    public function test_alterar_un_DTE_del_SetDTE_invalida_la_firma(): void
    {
        [$empresa, $certPem, $setSinFirma] = $this->escenarioSetDte();
        $firmado = $this->signer->firmar($setSinFirma, $empresa);

        $tampered = preg_replace('#<MntTotal>1190</MntTotal>#', '<MntTotal>9999</MntTotal>', $firmado);
        $this->assertNotSame($firmado, $tampered, 'Tampering debe haber modificado el XML.');

        $this->assertFalse(
            $this->verificarFirmaSetDte($tampered, $certPem),
            'Tampering al DTE interno debe invalidar la firma del SetDTE.'
        );
    }

    public function test_alterar_la_Caratula_invalida_la_firma(): void
    {
        [$empresa, $certPem, $setSinFirma] = $this->escenarioSetDte();
        $firmado = $this->signer->firmar($setSinFirma, $empresa);

        $tampered = preg_replace(
            '#<RutReceptor>60803000-K</RutReceptor>#',
            '<RutReceptor>11111111-1</RutReceptor>',
            $firmado
        );
        $this->assertNotSame($firmado, $tampered, 'Tampering debe haber modificado el XML.');

        $this->assertFalse(
            $this->verificarFirmaSetDte($tampered, $certPem),
            'Tampering a la Caratula debe invalidar la firma del SetDTE.'
        );
    }

    private function verificarFirmaSetDte(string $xml, string $certPem): bool
    {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = true;
        if (! @$dom->loadXML($xml)) {
            return false;
        }
        $setNode = $dom->getElementsByTagName('SetDTE')->item(0);
        if ($setNode === null) {
            return false;
        }
        $setNode->setIdAttribute('ID', true);

        $dsig = new XMLSecurityDSig();
        $dsig->idKeys = ['ID'];
        $count = $dom->getElementsByTagNameNS(self::NS_DSIG, 'Signature')->length;
        $dsig->locateSignature($dom, $count - 1);
        $dsig->canonicalizeSignedInfo();

        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, ['type' => 'public']);
        $key->loadKey($certPem, false, true);

        try {
            if ($dsig->verify($key) !== 1) {
                return false;
            }
            return $dsig->validateReference();
        } catch (\Throwable) {
            return false;
        }
    }
}
