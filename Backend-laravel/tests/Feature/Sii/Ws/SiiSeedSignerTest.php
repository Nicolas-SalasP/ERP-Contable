<?php

namespace Tests\Feature\Sii\Ws;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Exceptions\CertificadoInvalidoException;
use App\Domains\Sii\Services\Ws\SiiSeedSigner;
use DOMDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use Tests\Concerns\GeneraCertificadoParaTests;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class SiiSeedSignerTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;
    use GeneraCertificadoParaTests;

    private const NS_DSIG = 'http://www.w3.org/2000/09/xmldsig#';

    private SiiSeedSigner $signer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        if ($this->localizarOpensslCnf() === null) {
            $this->markTestSkipped('openssl.cnf no encontrado.');
        }

        $this->signer = app(SiiSeedSigner::class);
    }

    /**
     * @return array{0: Empresa, 1: string} [empresa, certPem]
     */
    private function empresaConCert(string $rut = '76555444-3'): array
    {
        $empresa = Empresa::create([
            'rut'          => $rut,
            'razon_social' => 'EMPRESA SEED',
            'ambiente_sii' => 'certificacion',
        ]);
        [, $certPem] = $this->crearCertActivoParaEmpresa($empresa, 'TEST ' . $rut);

        return [$empresa, $certPem];
    }

    public function test_firma_semilla_produce_xml_con_Signature_ds(): void
    {
        [$empresa] = $this->empresaConCert();

        $xml = $this->signer->firmar('SEMILLA_42', $empresa);

        $this->assertStringContainsString('<getToken>', $xml);
        $this->assertStringContainsString('<item>', $xml);
        $this->assertStringContainsString('<Semilla>SEMILLA_42</Semilla>', $xml);
        $this->assertStringContainsString('Signature', $xml);
        $this->assertStringContainsString('SignatureValue', $xml);
    }

    public function test_Reference_URI_es_vacia_firma_del_documento_completo(): void
    {
        [$empresa] = $this->empresaConCert();
        $xml = $this->signer->firmar('S', $empresa);

        $dom = new DOMDocument();
        $dom->loadXML($xml);
        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('ds', self::NS_DSIG);

        // URI="" indica firma del documento completo (segun spec SII getToken).
        $uri = $xp->evaluate('string(//ds:Signature/ds:SignedInfo/ds:Reference/@URI)');
        $this->assertSame('', $uri);
    }

    public function test_Signature_se_verifica_con_cert_publico_de_empresa(): void
    {
        [$empresa, $certPem] = $this->empresaConCert();
        $xml = $this->signer->firmar('SEMILLA_PARA_VERIFY', $empresa);

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->loadXML($xml);

        $dsig = new XMLSecurityDSig();
        $sig  = $dsig->locateSignature($dom);
        $this->assertNotNull($sig, 'Debe haber una Signature locable.');

        $dsig->canonicalizeSignedInfo();

        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, ['type' => 'public']);
        $key->loadKey($certPem, false, true);

        $this->assertSame(1, $dsig->verify($key), 'SignatureValue debe verificar contra cert empresa.');
        $this->assertTrue($dsig->validateReference(), 'Reference (digest del doc) debe validar.');
    }

    public function test_alterar_semilla_post_firma_invalida_signature(): void
    {
        [$empresa, $certPem] = $this->empresaConCert();
        $xml = $this->signer->firmar('SEMILLA_ORIGINAL', $empresa);

        $xmlAlterado = str_replace('<Semilla>SEMILLA_ORIGINAL</Semilla>', '<Semilla>SEMILLA_TAMPERED</Semilla>', $xml);
        $this->assertNotSame($xml, $xmlAlterado);

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->loadXML($xmlAlterado);

        $dsig = new XMLSecurityDSig();
        $dsig->locateSignature($dom);
        $dsig->canonicalizeSignedInfo();

        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, ['type' => 'public']);
        $key->loadKey($certPem, false, true);

        $verify = $dsig->verify($key);
        $referenceOk = false;
        try {
            $referenceOk = $dsig->validateReference();
        } catch (\Throwable) {
            $referenceOk = false;
        }

        $this->assertTrue(
            $verify !== 1 || ! $referenceOk,
            'Tampering al payload debe invalidar la firma.'
        );
    }

    public function test_lanza_si_empresa_sin_cert_activo(): void
    {
        $empresa = Empresa::create([
            'rut'          => '77000000-0',
            'razon_social' => 'SIN CERT',
            'ambiente_sii' => 'certificacion',
        ]);

        $this->expectException(CertificadoInvalidoException::class);
        $this->signer->firmar('SEMILLA', $empresa);
    }
}
