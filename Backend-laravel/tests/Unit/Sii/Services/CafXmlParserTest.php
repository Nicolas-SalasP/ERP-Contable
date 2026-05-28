<?php

namespace Tests\Unit\Sii\Services;

use App\Domains\Sii\Exceptions\CafInvalidoException;
use App\Domains\Sii\Services\Caf\CafXmlParser;
use Tests\TestCase;

class CafXmlParserTest extends TestCase
{
    private CafXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new CafXmlParser();
    }

    private function xmlValido(array $overrides = []): string
    {
        $defaults = [
            'RE'  => '76.123.456-7',
            'RS'  => 'EMPRESA EJEMPLO LTDA',
            'TD'  => '33',
            'D'   => '1',
            'H'   => '50',
            'FA'  => '2026-01-15',
            'IDK' => '300',
            'FRMA' => 'FIRMA_BASE64_DUMMY',
            'RSASK' => "-----BEGIN RSA PRIVATE KEY-----\nDUMMY\n-----END RSA PRIVATE KEY-----",
            'RSAPUBK' => "-----BEGIN PUBLIC KEY-----\nDUMMY\n-----END PUBLIC KEY-----",
        ];
        $v = array_merge($defaults, $overrides);

        return <<<XML
<?xml version="1.0"?>
<AUTORIZACION>
  <CAF version="1.0">
    <DA>
      <RE>{$v['RE']}</RE>
      <RS>{$v['RS']}</RS>
      <TD>{$v['TD']}</TD>
      <RNG>
        <D>{$v['D']}</D>
        <H>{$v['H']}</H>
      </RNG>
      <FA>{$v['FA']}</FA>
      <RSAPK>
        <M>MODULUS_DUMMY</M>
        <E>EXPONENT_DUMMY</E>
      </RSAPK>
      <IDK>{$v['IDK']}</IDK>
    </DA>
    <FRMA algoritmo="SHA1withRSA">{$v['FRMA']}</FRMA>
  </CAF>
  <RSASK>{$v['RSASK']}</RSASK>
  <RSAPUBK>{$v['RSAPUBK']}</RSAPUBK>
</AUTORIZACION>
XML;
    }

    public function test_parsea_caf_valido_extrae_todos_los_campos(): void
    {
        $r = $this->parser->parse($this->xmlValido());

        $this->assertSame(33, $r['tipo_dte']);
        $this->assertSame(1, $r['folio_desde']);
        $this->assertSame(50, $r['folio_hasta']);
        $this->assertSame('76123456-7', $r['rut_empresa']);
        $this->assertSame('EMPRESA EJEMPLO LTDA', $r['razon_social']);
        $this->assertSame('300', $r['sii_idk']);
        $this->assertStringContainsString('BEGIN RSA PRIVATE KEY', $r['rsa_sk']);
        $this->assertStringContainsString('BEGIN PUBLIC KEY', $r['rsa_pubk']);
        $this->assertSame('FIRMA_BASE64_DUMMY', $r['firma_caf']);
        $this->assertSame('2026-01-15', $r['fecha_autorizacion']->toDateString());
    }

    public function test_falla_con_xml_malformado(): void
    {
        $this->expectException(CafInvalidoException::class);
        $this->parser->parse('<AUTORIZACION><CAF version="1.0"><DA>NO CIERRA');
    }

    public function test_falla_si_nodo_raiz_no_es_AUTORIZACION(): void
    {
        $this->expectException(CafInvalidoException::class);
        $this->parser->parse('<?xml version="1.0"?><otro><x/></otro>');
    }

    public function test_falla_si_falta_nodo_RE(): void
    {
        $xml = str_replace('<RE>76.123.456-7</RE>', '', $this->xmlValido());

        $this->expectException(CafInvalidoException::class);
        $this->parser->parse($xml);
    }

    public function test_falla_si_falta_nodo_TD(): void
    {
        $xml = str_replace('<TD>33</TD>', '', $this->xmlValido());

        $this->expectException(CafInvalidoException::class);
        $this->parser->parse($xml);
    }

    public function test_falla_si_falta_RNG_D(): void
    {
        $xml = str_replace('<D>1</D>', '', $this->xmlValido());

        $this->expectException(CafInvalidoException::class);
        $this->parser->parse($xml);
    }

    public function test_falla_si_falta_RSASK(): void
    {
        $xml = preg_replace('#<RSASK>.*?</RSASK>#s', '', $this->xmlValido()) ?? '';

        $this->expectException(CafInvalidoException::class);
        $this->parser->parse($xml);
    }

    public function test_falla_si_falta_RSAPUBK(): void
    {
        $xml = preg_replace('#<RSAPUBK>.*?</RSAPUBK>#s', '', $this->xmlValido()) ?? '';

        $this->expectException(CafInvalidoException::class);
        $this->parser->parse($xml);
    }

    public function test_normaliza_rut_de_emisor(): void
    {
        $r = $this->parser->parse($this->xmlValido(['RE' => '76.123.456-7']));
        $this->assertSame('76123456-7', $r['rut_empresa']);

        $r2 = $this->parser->parse($this->xmlValido(['RE' => '76123456-7']));
        $this->assertSame('76123456-7', $r2['rut_empresa']);
    }

    public function test_calcula_fecha_vencimiento_a_6_meses_si_no_viene_explicito(): void
    {
        $r = $this->parser->parse($this->xmlValido(['FA' => '2026-01-15']));

        $this->assertNotNull($r['fecha_vencimiento']);
        $this->assertSame('2026-07-15', $r['fecha_vencimiento']->toDateString());
    }
}
