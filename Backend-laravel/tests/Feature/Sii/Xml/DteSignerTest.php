<?php

namespace Tests\Feature\Sii\Xml;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoDetalle;
use App\Domains\Sii\Services\Xml\DteSigner;
use App\Domains\Sii\Services\Xml\DteXmlBuilder;
use App\Domains\Sii\Support\RutHelper;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use Tests\Concerns\GeneraCertificadoParaTests;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class DteSignerTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;
    use GeneraCertificadoParaTests;

    private const NS_DSIG = 'http://www.w3.org/2000/09/xmldsig#';

    private DteSigner $signer;
    private DteXmlBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        if ($this->localizarOpensslCnf() === null) {
            $this->markTestSkipped('openssl.cnf no encontrado.');
        }

        $this->signer  = app(DteSigner::class);
        $this->builder = app(DteXmlBuilder::class);
    }

    /**
     * @return array{0: Empresa, 1: SiiDteEmitido, 2: string} [empresa, dte, certPem]
     */
    private function escenarioFirmable(): array
    {
        $num = 76123456;
        $rut = $num . '-' . RutHelper::calcularDv($num);
        $empresa = Empresa::create([
            'rut'                   => $rut,
            'razon_social'          => 'EMPRESA FIRMA TEST',
            'resolucion_sii_numero' => 80,
            'resolucion_sii_fecha'  => '2024-08-22',
            'ambiente_sii'          => 'certificacion',
        ]);

        [, $certPem] = $this->crearCertActivoParaEmpresa($empresa, 'TEST FIRMA ' . $rut);
        [$caf]        = $this->crearCafActivoParaEmpresa($empresa, 33, 1, 50);

        $dte = SiiDteEmitido::factory()->factura()->create([
            'empresa_id'        => $empresa->id,
            'emisor_rut'        => $rut,
            'emisor_acteco'     => 471910,
            'emisor_giro'       => 'Comercio',
            'emisor_direccion'  => 'Calle 1',
            'emisor_comuna'     => 'Santiago',
            'folio'             => 10,
            'monto_neto'        => 1000,
            'iva'               => 190,
            'monto_total'       => 1190,
        ]);
        SiiDteEmitidoDetalle::factory()->create([
            'dte_emitido_id'  => $dte->id,
            'numero_linea'    => 1,
            'nombre_item'     => 'Producto X',
            'cantidad'        => 1,
            'precio_unitario' => 1000,
            'monto_item'      => 1000,
        ]);
        $dte = $dte->fresh(['detalles', 'referencias', 'traslado.madera', 'impuestosAdicionales']);

        $xmlConTed = $this->builder->build($dte, $caf);

        return [$empresa, $dte, $certPem, $xmlConTed];
    }

    private function xpath(string $xml): DOMXPath
    {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->loadXML($xml);
        $x = new DOMXPath($dom);
        $x->registerNamespace('sii', 'http://www.sii.cl/SiiDte');
        $x->registerNamespace('ds', self::NS_DSIG);
        return $x;
    }

    public function test_elimina_placeholder_ds_signature_de_F4_1_antes_de_firmar(): void
    {
        [$empresa, , , $xmlConTed] = $this->escenarioFirmable();

        // Confirmo que F4.1 dejo un ds:Signature placeholder en /DTE.
        $xpathPre = $this->xpath($xmlConTed);
        $this->assertSame(
            1,
            $xpathPre->query('/sii:DTE/ds:Signature')->length,
            'F4.1 debe haber dejado UN ds:Signature placeholder en /DTE.'
        );
        $digestPlaceholder = $xpathPre->evaluate(
            'string(/sii:DTE/ds:Signature/ds:SignedInfo/ds:Reference/ds:DigestValue)'
        );

        $xmlFirmado = $this->signer->firmar($xmlConTed, $empresa);

        $xpathPost = $this->xpath($xmlFirmado);
        $this->assertSame(
            1,
            $xpathPost->query('/sii:DTE/ds:Signature')->length,
            'Tras firmar debe seguir habiendo UNA sola ds:Signature (la real).'
        );

        $digestReal = $xpathPost->evaluate(
            'string(/sii:DTE/ds:Signature/ds:SignedInfo/ds:Reference/ds:DigestValue)'
        );
        $this->assertNotSame(
            $digestPlaceholder,
            $digestReal,
            'DigestValue debe haber cambiado (placeholder -> firma real).'
        );
    }

    public function test_ds_signature_real_es_hermano_de_Documento_dentro_de_DTE(): void
    {
        [$empresa, , , $xmlConTed] = $this->escenarioFirmable();
        $xml = $this->signer->firmar($xmlConTed, $empresa);

        $x = $this->xpath($xml);
        $this->assertSame(1, $x->query('/sii:DTE/sii:Documento')->length);
        $this->assertSame(1, $x->query('/sii:DTE/ds:Signature')->length);
        // Posicion: Signature DESPUES de Documento.
        $nodos = $x->query('/sii:DTE/*');
        $tags  = [];
        foreach ($nodos as $n) {
            $tags[] = $n->localName;
        }
        $this->assertSame('Documento', $tags[0]);
        $this->assertSame('Signature', end($tags));
    }

    public function test_reference_uri_apunta_a_D_folio_del_atributo_ID(): void
    {
        [$empresa, $dte, , $xmlConTed] = $this->escenarioFirmable();
        $xml = $this->signer->firmar($xmlConTed, $empresa);

        $x = $this->xpath($xml);
        $uri = $x->evaluate(
            'string(/sii:DTE/ds:Signature/ds:SignedInfo/ds:Reference/@URI)'
        );
        $this->assertSame('#D' . $dte->folio, $uri);
    }

    public function test_DigestValue_no_es_placeholder(): void
    {
        [$empresa, , , $xmlConTed] = $this->escenarioFirmable();
        $xml = $this->signer->firmar($xmlConTed, $empresa);

        $x = $this->xpath($xml);
        $digest = $x->evaluate('string(/sii:DTE/ds:Signature/ds:SignedInfo/ds:Reference/ds:DigestValue)');

        $this->assertNotEmpty($digest);
        $this->assertNotSame('UExBQ0VIT0xERVJfRjRfMl9GSVJNQQ==', $digest);
        // Digest SHA1 base64 son 28 caracteres (incluyendo padding).
        $this->assertSame(28, strlen($digest));
    }

    public function test_firma_verifica_correctamente_con_cert_publico(): void
    {
        [$empresa, , $certPem, $xmlConTed] = $this->escenarioFirmable();
        $xml = $this->signer->firmar($xmlConTed, $empresa);

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->loadXML($xml);

        // Marcar atributo ID en Documento para resolver Reference URI=#D{folio}.
        $doc = $dom->getElementsByTagName('Documento')->item(0);
        $doc->setIdAttribute('ID', true);

        $dsig = new XMLSecurityDSig();
        $dsig->idKeys = ['ID'];
        $dsig->locateSignature($dom);
        $dsig->canonicalizeSignedInfo();

        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, ['type' => 'public']);
        $key->loadKey($certPem, false, true);

        $this->assertSame(1, $dsig->verify($key));
        $this->assertTrue($dsig->validateReference());
    }

    public function test_alterar_un_byte_del_Documento_invalida_la_firma(): void
    {
        [$empresa, , $certPem, $xmlConTed] = $this->escenarioFirmable();
        $xml = $this->signer->firmar($xmlConTed, $empresa);

        // Tampering: cambiar el monto total dentro de <MntTotal>.
        $xmlAlterado = preg_replace('#<MntTotal>1190</MntTotal>#', '<MntTotal>9999</MntTotal>', $xml);
        $this->assertNotSame($xml, $xmlAlterado, 'Tampering debe haber modificado el XML.');

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->loadXML($xmlAlterado);
        $dom->getElementsByTagName('Documento')->item(0)->setIdAttribute('ID', true);

        $dsig = new XMLSecurityDSig();
        $dsig->idKeys = ['ID'];
        $dsig->locateSignature($dom);
        $dsig->canonicalizeSignedInfo();

        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, ['type' => 'public']);
        $key->loadKey($certPem, false, true);

        $signedInfoVerify = $dsig->verify($key);
        $referenceOk = false;
        try {
            $referenceOk = $dsig->validateReference();
        } catch (\Throwable) {
            // validateReference lanza al fallar; lo tratamos como invalido.
            $referenceOk = false;
        }

        $this->assertTrue(
            $signedInfoVerify !== 1 || ! $referenceOk,
            'Tampering al Documento debe invalidar la firma (signedInfo verify o validateReference debe fallar).'
        );
    }
}
