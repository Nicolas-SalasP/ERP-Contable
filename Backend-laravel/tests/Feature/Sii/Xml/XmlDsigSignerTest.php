<?php

namespace Tests\Feature\Sii\Xml;

use App\Domains\Sii\Exceptions\DteXmlInvalidException;
use App\Domains\Sii\Services\Xml\XmlDsigSigner;
use DOMDocument;
use DOMXPath;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use Tests\Concerns\GeneraParRsaParaTests;
use Tests\TestCase;

class XmlDsigSignerTest extends TestCase
{
    use GeneraParRsaParaTests;

    private const NS_DSIG = 'http://www.w3.org/2000/09/xmldsig#';

    private XmlDsigSigner $signer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signer = new XmlDsigSigner();
    }

    /**
     * Crea un par cert+key self-signed in-memory para los tests.
     *
     * @return array{0: string, 1: string} [certPem, privKeyPem]
     */
    private function generarCertSelfSigned(string $cn = 'TEST CN'): array
    {
        $cfgPath = $this->localizarOpensslCnf();
        $config = ['digest_alg' => 'sha256', 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        if ($cfgPath !== null) {
            $config['config'] = $cfgPath;
        }

        $pkey = openssl_pkey_new($config);
        $csr  = openssl_csr_new(['commonName' => $cn], $pkey, $config);
        $cert = openssl_csr_sign($csr, null, $pkey, 365, $config);

        openssl_x509_export($cert, $certPem);
        openssl_pkey_export($pkey, $skPem, null, $cfgPath !== null ? ['config' => $cfgPath] : null);

        return [$certPem, $skPem];
    }

    private function domConNodoFirmable(string $idValue = 'TARGET1'): DOMDocument
    {
        $dom = new DOMDocument('1.0', 'ISO-8859-1');
        $dom->preserveWhiteSpace = true;
        $xml = '<?xml version="1.0" encoding="ISO-8859-1"?>'
             . "<Root>"
             . "<Target ID=\"{$idValue}\"><Payload>hello</Payload></Target>"
             . "</Root>";
        $dom->loadXML($xml);

        return $dom;
    }

    public function test_firma_nodo_inserta_Signature_en_parent(): void
    {
        [$cert, $sk] = $this->generarCertSelfSigned();
        $dom = $this->domConNodoFirmable();

        $this->signer->firmarNodo($dom, 'TARGET1', 'Target', $cert, $sk, $dom->documentElement);

        $sigs = $dom->getElementsByTagNameNS(self::NS_DSIG, 'Signature');
        $this->assertSame(1, $sigs->length);
        $this->assertSame($dom->documentElement, $sigs->item(0)->parentNode);
    }

    public function test_Signature_contiene_DigestValue_SignatureValue_X509Certificate(): void
    {
        [$cert, $sk] = $this->generarCertSelfSigned();
        $dom = $this->domConNodoFirmable();

        $this->signer->firmarNodo($dom, 'TARGET1', 'Target', $cert, $sk, $dom->documentElement);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ds', self::NS_DSIG);
        $this->assertSame(1, $xpath->query('//ds:Signature/ds:SignedInfo/ds:Reference/ds:DigestValue')->length);
        $this->assertSame(1, $xpath->query('//ds:Signature/ds:SignatureValue')->length);
        $this->assertSame(1, $xpath->query('//ds:Signature/ds:KeyInfo/ds:X509Data/ds:X509Certificate')->length);
    }

    public function test_algoritmo_canonicalization_es_C14N_1_0_inclusiva_no_exclusiva(): void
    {
        [$cert, $sk] = $this->generarCertSelfSigned();
        $dom = $this->domConNodoFirmable();
        $this->signer->firmarNodo($dom, 'TARGET1', 'Target', $cert, $sk, $dom->documentElement);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ds', self::NS_DSIG);
        $algo = $xpath->evaluate('string(//ds:Signature/ds:SignedInfo/ds:CanonicalizationMethod/@Algorithm)');

        $this->assertSame('http://www.w3.org/TR/2001/REC-xml-c14n-20010315', $algo);
        $this->assertStringNotContainsString('exc-c14n', $algo);
    }

    public function test_algoritmos_signature_rsa_sha1_y_digest_sha1(): void
    {
        [$cert, $sk] = $this->generarCertSelfSigned();
        $dom = $this->domConNodoFirmable();
        $this->signer->firmarNodo($dom, 'TARGET1', 'Target', $cert, $sk, $dom->documentElement);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ds', self::NS_DSIG);

        $this->assertSame(
            'http://www.w3.org/2000/09/xmldsig#rsa-sha1',
            $xpath->evaluate('string(//ds:Signature/ds:SignedInfo/ds:SignatureMethod/@Algorithm)')
        );
        $this->assertSame(
            'http://www.w3.org/2000/09/xmldsig#sha1',
            $xpath->evaluate('string(//ds:Signature/ds:SignedInfo/ds:Reference/ds:DigestMethod/@Algorithm)')
        );
    }

    public function test_transform_es_enveloped_unico(): void
    {
        [$cert, $sk] = $this->generarCertSelfSigned();
        $dom = $this->domConNodoFirmable();
        $this->signer->firmarNodo($dom, 'TARGET1', 'Target', $cert, $sk, $dom->documentElement);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ds', self::NS_DSIG);

        $algos = [];
        foreach ($xpath->query('//ds:Signature//ds:Transform/@Algorithm') as $attr) {
            $algos[] = $attr->value;
        }

        // El XSD del SII restringe Transforms.Transform a 1 ocurrencia (no n).
        // La canonicalizacion se aplica via CanonicalizationMethod del SignedInfo,
        // no como transform adicional de la Reference.
        $this->assertCount(1, $algos);
        $this->assertSame('http://www.w3.org/2000/09/xmldsig#enveloped-signature', $algos[0]);
    }

    public function test_signature_verifica_round_trip(): void
    {
        [$cert, $sk] = $this->generarCertSelfSigned();
        $dom = $this->domConNodoFirmable();
        $this->signer->firmarNodo($dom, 'TARGET1', 'Target', $cert, $sk, $dom->documentElement);

        // Re-parsear independientemente y verificar manualmente.
        $xml = $dom->saveXML();
        $domVerify = new DOMDocument();
        $domVerify->preserveWhiteSpace = true;
        $domVerify->loadXML($xml);

        // Marcar el atributo ID
        $target = $domVerify->getElementsByTagName('Target')->item(0);
        $target->setIdAttribute('ID', true);

        $dsig = new XMLSecurityDSig();
        $dsig->idKeys = ['ID'];
        $sig  = $dsig->locateSignature($domVerify);
        $this->assertNotNull($sig);
        $dsig->canonicalizeSignedInfo();

        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, ['type' => 'public']);
        $key->loadKey($cert, false, true);

        $this->assertSame(1, $dsig->verify($key));
        $this->assertTrue($dsig->validateReference());
    }

    public function test_lanza_si_nodo_objetivo_no_existe(): void
    {
        [$cert, $sk] = $this->generarCertSelfSigned();
        $dom = $this->domConNodoFirmable();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No se encontro nodo <NoExiste');

        $this->signer->firmarNodo($dom, 'X', 'NoExiste', $cert, $sk, $dom->documentElement);
    }

    public function test_lanza_si_priv_key_pem_invalida(): void
    {
        [$cert] = $this->generarCertSelfSigned();
        $dom = $this->domConNodoFirmable();

        // loadKey con PEM invalido lanza un \Exception generica de xmlseclibs.
        $this->expectException(\Throwable::class);

        $this->signer->firmarNodo($dom, 'TARGET1', 'Target', $cert, 'NO_ES_PEM', $dom->documentElement);
    }
}
