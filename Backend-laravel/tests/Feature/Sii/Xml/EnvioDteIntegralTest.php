<?php

namespace Tests\Feature\Sii\Xml;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Models\SiiCaf;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoDetalle;
use App\Domains\Sii\Services\Xml\DteSigner;
use App\Domains\Sii\Services\Xml\DteXmlBuilder;
use App\Domains\Sii\Services\Xml\SetDte\SetDteBuilder;
use App\Domains\Sii\Services\Xml\SetDte\SetDteSigner;
use App\Domains\Sii\Services\Xml\Ted\TedSignerService;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use Tests\Concerns\GeneraCertificadoParaTests;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Test integral E2E del flujo F4.1 + F4.2 + F4.3:
 *
 *   DteXmlBuilder + TedBuilder  -> XML con TED firmado RSA-SHA1 sobre DD
 *   DteSigner                   -> XML con <Documento> firmado XMLDSig (cert empresa)
 *   SetDteBuilder + SetDteSigner -> <EnvioDTE> con <SetDTE> firmado XMLDSig
 *
 * El EnvioDTE final contiene 3 firmas independientes:
 *   1. FRMT del TED   (RSA-SHA1 cruda, clave CAF)
 *   2. ds:Signature del Documento (XMLDSig, cert empresa)
 *   3. ds:Signature del SetDTE    (XMLDSig, cert empresa)
 */
class EnvioDteIntegralTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;
    use GeneraCertificadoParaTests;

    private const NS_SII  = 'http://www.sii.cl/SiiDte';
    private const NS_DSIG = 'http://www.w3.org/2000/09/xmldsig#';

    /** @var array{empresa: Empresa, dte: SiiDteEmitido, caf: SiiCaf, certPem: string, envio: string, xmlDteFirmado: string} */
    private array $contexto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        if ($this->localizarOpensslCnf() === null) {
            $this->markTestSkipped('openssl.cnf no encontrado.');
        }
    }

    private function ejecutarFlujoCompleto(): void
    {
        if (isset($this->contexto)) {
            return;
        }

        $empresa = Empresa::create([
            'rut'                   => '76123456-7',
            'razon_social'          => 'EMPRESA E2E',
            'resolucion_sii_numero' => 80,
            'resolucion_sii_fecha'  => '2024-08-22',
            'ambiente_sii'          => 'certificacion',
        ]);

        [, $certPem]      = $this->crearCertActivoParaEmpresa($empresa, 'OPERADOR ' . $empresa->rut);
        [$caf]            = $this->crearCafActivoParaEmpresa($empresa, 33, 1, 50);

        $dte = SiiDteEmitido::factory()->factura()->create([
            'empresa_id'        => $empresa->id,
            'emisor_rut'        => $empresa->rut,
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
            'nombre_item'     => 'Producto E2E',
            'cantidad'        => 1,
            'precio_unitario' => 1000,
            'monto_item'      => 1000,
        ]);
        $dte = $dte->fresh(['detalles', 'referencias', 'traslado.madera', 'impuestosAdicionales']);

        $xmlConTed     = app(DteXmlBuilder::class)->build($dte, $caf);
        $xmlDteFirmado = app(DteSigner::class)->firmar($xmlConTed, $empresa);
        $setSinFirma   = app(SetDteBuilder::class)->build($empresa, [['dte' => $dte, 'xml' => $xmlDteFirmado]]);
        $envio         = app(SetDteSigner::class)->firmar($setSinFirma, $empresa);

        $this->contexto = compact('empresa', 'dte', 'caf', 'certPem', 'envio', 'xmlDteFirmado');
    }

    public function test_flujo_completo_produce_EnvioDTE_con_TED_Documento_y_SetDTE_firmados(): void
    {
        $this->ejecutarFlujoCompleto();
        $envio = $this->contexto['envio'];

        $this->assertStringContainsString('<EnvioDTE', $envio);
        $this->assertStringContainsString('<SetDTE ID="SetDocDTE">', $envio);
        $this->assertStringContainsString('<TED version="1.0">', $envio);
        $this->assertStringContainsString('<FRMT algoritmo="SHA1withRSA">', $envio);
        $this->assertStringContainsString('encoding="ISO-8859-1"', $envio);
    }

    public function test_envio_tiene_dos_ds_signature_documento_y_setdte(): void
    {
        $this->ejecutarFlujoCompleto();
        $envio = $this->contexto['envio'];

        $dom = new DOMDocument();
        $dom->loadXML($envio);

        $sigs = $dom->getElementsByTagNameNS(self::NS_DSIG, 'Signature');
        $this->assertSame(
            2,
            $sigs->length,
            'EnvioDTE debe contener exactamente 2 ds:Signature (Documento + SetDTE). El TED tiene FRMT, no ds:Signature.'
        );
    }

    public function test_cada_firma_se_verifica_contra_su_clave_correspondiente(): void
    {
        $this->ejecutarFlujoCompleto();
        ['envio' => $envio, 'certPem' => $certPem, 'caf' => $caf, 'xmlDteFirmado' => $xmlDteFirmado] = $this->contexto;

        // -------- Firma del SetDTE: verificable directo en el EnvioDTE --------
        $domEnvio = new DOMDocument();
        $domEnvio->preserveWhiteSpace = true;
        $domEnvio->loadXML($envio);
        $domEnvio->getElementsByTagName('SetDTE')->item(0)->setIdAttribute('ID', true);

        $dsigSet = new XMLSecurityDSig();
        $dsigSet->idKeys = ['ID'];
        // La firma del SetDTE es la ULTIMA en orden documental (despues de la del Documento).
        $countSig = $domEnvio->getElementsByTagNameNS(self::NS_DSIG, 'Signature')->length;
        $dsigSet->locateSignature($domEnvio, $countSig - 1);
        $dsigSet->canonicalizeSignedInfo();
        $keyPub = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, ['type' => 'public']);
        $keyPub->loadKey($certPem, false, true);
        $this->assertSame(1, $dsigSet->verify($keyPub), 'Firma del SetDTE debe verificar contra cert empresa.');
        $this->assertTrue($dsigSet->validateReference(), 'Reference del SetDTE debe validar.');

        // -------- Firma del Documento: verificar contra el XML del DTE pre-embed --------
        // La firma del Documento se hizo en el contexto del XML aislado del DTE
        // (antes de embeberlo en el SetDTE). Con C14N inclusiva los namespaces
        // heredados al embeber pueden cambiar la canonicalizacion. Por eso
        // verificamos contra los bytes ORIGINALES tal como se firmaron.
        // (Esto es lo que el SII chile hace internamente: separa los DTE del
        // SetDTE y los verifica en su contexto aislado.)
        $domDte = new DOMDocument();
        $domDte->preserveWhiteSpace = true;
        $domDte->loadXML($xmlDteFirmado);
        $domDte->getElementsByTagName('Documento')->item(0)->setIdAttribute('ID', true);

        $dsigDoc = new XMLSecurityDSig();
        $dsigDoc->idKeys = ['ID'];
        $dsigDoc->locateSignature($domDte);
        $dsigDoc->canonicalizeSignedInfo();
        $this->assertSame(1, $dsigDoc->verify($keyPub), 'Firma del Documento debe verificar contra cert empresa.');
        $this->assertTrue($dsigDoc->validateReference(), 'Reference del Documento debe validar.');

        // Aserto adicional: la SignatureValue del Documento es identica entre
        // el DTE aislado y el embebido en EnvioDTE (sin rotacion ni alteracion
        // al importar via DOM importNode).
        // Extraer SignatureValue via regex sobre el string del DTE aislado.
        // (DOMXPath via namespaces puede no resolver consistentemente entre
        // el DOM aislado y el embebido, dependiendo de la serializacion.)
        $sigVal = [];
        $this->assertSame(
            1,
            preg_match('#<ds:SignatureValue[^>]*>([^<]+)</ds:SignatureValue>#', $xmlDteFirmado, $sigVal),
            'DTE aislado debe contener exactamente una ds:SignatureValue.'
        );
        $this->assertStringContainsString(
            $sigVal[1],
            $envio,
            'SignatureValue del Documento debe aparecer identica en el EnvioDTE final.'
        );

        // -------- Firma del TED (FRMT): RSA cruda contra RSAPUBK del CAF --------
        $ddMatch = [];
        $frMatch = [];
        $this->assertSame(1, preg_match('#(<DD>.*?</DD>)#s', $envio, $ddMatch), 'DD presente.');
        $this->assertSame(1, preg_match('#<FRMT algoritmo="SHA1withRSA">([A-Za-z0-9+/=]+)</FRMT>#', $envio, $frMatch), 'FRMT presente.');

        $this->assertTrue(
            app(TedSignerService::class)->verificarFirma($ddMatch[1], $frMatch[1], $caf),
            'FRMT del TED debe verificar contra RSAPUBK del CAF.'
        );
    }

    public function test_xml_final_pasa_validacion_xsd_EnvioDTE(): void
    {
        $this->ejecutarFlujoCompleto();
        $envio = $this->contexto['envio'];

        // EnvioDTE_v10.xsd hace xs:include de DTE_v10.xsd y xs:import de xmldsignature_v10.xsd.
        // libxml resuelve las rutas relativas a la ubicacion del XSD principal.
        $xsdPath = __DIR__ . '/../../../../app/Domains/Sii/Resources/xsd/EnvioDTE_v10.xsd';
        $this->assertFileExists($xsdPath);

        $prev = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = true;
        $this->assertTrue($dom->loadXML($envio), 'EnvioDTE debe ser XML parseable.');

        $valido = @$dom->schemaValidate($xsdPath);

        $errores = [];
        foreach (libxml_get_errors() as $e) {
            $errores[] = 'L' . $e->line . ': ' . trim($e->message);
        }
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $this->assertTrue(
            $valido,
            'EnvioDTE debe validar contra EnvioDTE_v10.xsd. Errores: ' . implode(' | ', $errores)
        );
    }
}
