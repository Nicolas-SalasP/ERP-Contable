<?php

namespace Tests\Feature\Sii\Xml;

use App\Domains\Sii\Models\SiiCaf;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoDetalle;
use App\Domains\Sii\Services\Caf\CafSerializerService;
use App\Domains\Sii\Services\Caf\CafService;
use App\Domains\Sii\Services\Caf\CafXmlParser;
use App\Domains\Sii\Services\Xml\DteXmlBuilder;
use App\Domains\Sii\Services\Xml\DteXsdValidator;
use App\Domains\Sii\Services\Xml\Ted\TedBuilder;
use App\Domains\Sii\Services\Xml\Ted\TedSignerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use LogicException;
use Tests\Concerns\GeneraParRsaParaTests;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class DteXmlBuilderConCafTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;
    use GeneraParRsaParaTests;

    private DteXmlBuilder $builder;
    private TedSignerService $signer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        $cafService    = new CafService(new CafXmlParser());
        $this->signer  = new TedSignerService($cafService);
        $tedBuilder    = new TedBuilder(new CafSerializerService(), $this->signer);
        $this->builder = new DteXmlBuilder(new DteXsdValidator(), $tedBuilder);
    }

    private function xmlCafReal(int $tipoDte, int $desde, int $hasta): string
    {
        return <<<XML
<?xml version="1.0"?>
<AUTORIZACION>
  <CAF version="1.0">
    <DA>
      <RE>76123456-7</RE>
      <RS>EMPRESA INTEGRACION</RS>
      <TD>{$tipoDte}</TD>
      <RNG><D>{$desde}</D><H>{$hasta}</H></RNG>
      <FA>2026-01-15</FA>
      <RSAPK><M>MMMM</M><E>Aw==</E></RSAPK>
      <IDK>456</IDK>
    </DA>
    <FRMA algoritmo="SHA1withRSA">RklSTUFfREVMX1NJSV9CQVNFNjQ=</FRMA>
  </CAF>
</AUTORIZACION>
XML;
    }

    private function cafConPar(string $sk, string $pk, int $tipo = 33, int $desde = 1, int $hasta = 50): SiiCaf
    {
        return SiiCaf::factory()->create([
            'tipo_dte'             => $tipo,
            'folio_desde'          => $desde,
            'folio_hasta'          => $hasta,
            'folio_actual'         => $desde,
            'rsa_sk_cifrada'       => Crypt::encryptString($sk),
            'rsa_pubk'             => $pk,
            'xml_completo_cifrado' => Crypt::encryptString($this->xmlCafReal($tipo, $desde, $hasta)),
        ]);
    }

    private function dteValido(int $tipo = 33, int $folio = 10): SiiDteEmitido
    {
        $dte = SiiDteEmitido::factory()->create([
            'tipo_dte'         => $tipo,
            'folio'            => $folio,
            'emisor_acteco'    => 471910,
            'emisor_giro'      => 'Comercio',
            'emisor_direccion' => 'Calle 1',
            'emisor_comuna'    => 'Santiago',
            'monto_neto'       => 1000,
            'iva'              => 190,
            'monto_total'      => 1190,
        ]);
        SiiDteEmitidoDetalle::factory()->create([
            'dte_emitido_id' => $dte->id,
            'numero_linea'   => 1,
            'nombre_item'    => 'Producto',
            'cantidad'       => 1,
            'precio_unitario'=> 1000,
            'monto_item'     => 1000,
        ]);

        return $dte->fresh(['detalles', 'referencias', 'traslado.madera', 'impuestosAdicionales']);
    }

    public function test_build_con_caf_inyecta_ted_firmado_y_xml_es_valido(): void
    {
        [$sk, $pk] = $this->generarParRsa();
        $caf = $this->cafConPar($sk, $pk);
        $dte = $this->dteValido();

        $xml = $this->builder->build($dte, $caf);

        $this->assertStringContainsString('<TED version="1.0">', $xml);
        $this->assertStringContainsString('<FRMT algoritmo="SHA1withRSA">', $xml);
        $this->assertStringContainsString('encoding="ISO-8859-1"', $xml);
    }

    public function test_dd_que_aparece_en_xml_es_bit_exact_al_dd_firmado(): void
    {
        [$sk, $pk] = $this->generarParRsa();
        $caf = $this->cafConPar($sk, $pk);
        $dte = $this->dteValido();

        $xml = $this->builder->build($dte, $caf);

        $okDd = preg_match('#(<DD>.*</DD>)#s', $xml, $mDd);
        $okFr = preg_match('#<FRMT algoritmo="SHA1withRSA">([A-Za-z0-9+/=]+)</FRMT>#', $xml, $mFr);

        $this->assertSame(1, $okDd, 'DD presente en XML final.');
        $this->assertSame(1, $okFr, 'FRMT presente en XML final.');

        $this->assertTrue(
            $this->signer->verificarFirma($mDd[1], $mFr[1], $caf),
            'La firma del FRMT debe verificar contra el DD tal como aparece en el XML final.'
        );
    }

    public function test_build_sin_caf_mantiene_comportamiento_f41_placeholders(): void
    {
        // Re-instanciamos sin TedBuilder para asegurar que el fallback funciona.
        $builderF41 = new DteXmlBuilder(new DteXsdValidator());

        $dte = $this->dteValido();
        $xml = $builderF41->build($dte);

        $this->assertStringContainsString('<TED version="1.0">', $xml);
        // F4.1 inyecta placeholder base64 "PLACEHOLDER_F4_2_FIRMA" en FRMT.
        $this->assertStringContainsString('UExBQ0VIT0xERVJfRjRfMl9GSVJNQQ==', $xml);
    }

    public function test_falla_si_se_provee_caf_pero_builder_no_tiene_tedbuilder(): void
    {
        $builderSinTed = new DteXmlBuilder(new DteXsdValidator());
        [$sk, $pk] = $this->generarParRsa();
        $caf = $this->cafConPar($sk, $pk);
        $dte = $this->dteValido();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('TedBuilder');

        $builderSinTed->build($dte, $caf);
    }

    public function test_xml_completo_valida_contra_xsd_oficial(): void
    {
        [$sk, $pk] = $this->generarParRsa();
        $caf = $this->cafConPar($sk, $pk);
        $dte = $this->dteValido();

        // build() valida internamente contra XSD. Si llega aqui sin excepcion, paso.
        $xml = $this->builder->build($dte, $caf);

        $this->assertNotEmpty($xml);
        $this->assertStringContainsString('<DTE', $xml);
    }
}
