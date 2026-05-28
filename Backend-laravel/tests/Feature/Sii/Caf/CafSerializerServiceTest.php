<?php

namespace Tests\Feature\Sii\Caf;

use App\Domains\Sii\Exceptions\CafInvalidoException;
use App\Domains\Sii\Models\SiiCaf;
use App\Domains\Sii\Services\Caf\CafSerializerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class CafSerializerServiceTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    private CafSerializerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->service = new CafSerializerService();
    }

    private function xmlCafValido(): string
    {
        return <<<XML
<?xml version="1.0"?>
<AUTORIZACION>
  <CAF version="1.0">
    <DA>
      <RE>76123456-7</RE>
      <RS>EMPRESA DEMO</RS>
      <TD>33</TD>
      <RNG><D>1</D><H>50</H></RNG>
      <FA>2026-01-15</FA>
      <RSAPK><M>MMMM</M><E>Aw==</E></RSAPK>
      <IDK>100</IDK>
    </DA>
    <FRMA algoritmo="SHA1withRSA">RklSTUFfREVMX1NJSV9CQVNFNjQ=</FRMA>
  </CAF>
  <RSASK>-----BEGIN RSA PRIVATE KEY-----
DUMMY
-----END RSA PRIVATE KEY-----</RSASK>
  <RSAPUBK>-----BEGIN PUBLIC KEY-----
DUMMY
-----END PUBLIC KEY-----</RSAPUBK>
</AUTORIZACION>
XML;
    }

    private function cafConXmlCifrado(string $xmlCompleto): SiiCaf
    {
        return SiiCaf::factory()->create([
            'xml_completo_cifrado' => Crypt::encryptString($xmlCompleto),
        ]);
    }

    public function test_extrae_bloque_caf_completo(): void
    {
        $caf = $this->cafConXmlCifrado($this->xmlCafValido());

        $bloque = $this->service->extraerBloqueCaf($caf);

        $this->assertStringStartsWith('<CAF version="1.0">', $bloque);
        $this->assertStringEndsWith('</CAF>', $bloque);
    }

    public function test_bloque_extraido_no_contiene_envoltorio_autorizacion(): void
    {
        $caf = $this->cafConXmlCifrado($this->xmlCafValido());

        $bloque = $this->service->extraerBloqueCaf($caf);

        $this->assertStringNotContainsString('<AUTORIZACION>', $bloque);
        $this->assertStringNotContainsString('</AUTORIZACION>', $bloque);
    }

    public function test_bloque_extraido_no_contiene_rsa_sk_ni_rsa_pubk(): void
    {
        $caf = $this->cafConXmlCifrado($this->xmlCafValido());

        $bloque = $this->service->extraerBloqueCaf($caf);

        $this->assertStringNotContainsString('<RSASK>', $bloque);
        $this->assertStringNotContainsString('<RSAPUBK>', $bloque);
        $this->assertStringNotContainsString('BEGIN RSA PRIVATE KEY', $bloque);
        $this->assertStringNotContainsString('BEGIN PUBLIC KEY', $bloque);
    }

    public function test_bloque_extraido_contiene_da_y_frma(): void
    {
        $caf = $this->cafConXmlCifrado($this->xmlCafValido());

        $bloque = $this->service->extraerBloqueCaf($caf);

        $this->assertStringContainsString('<DA>', $bloque);
        $this->assertStringContainsString('</DA>', $bloque);
        $this->assertStringContainsString('<FRMA algoritmo="SHA1withRSA">', $bloque);
        $this->assertStringContainsString('RklSTUFfREVMX1NJSV9CQVNFNjQ=', $bloque);
        $this->assertStringContainsString('<IDK>100</IDK>', $bloque);
    }

    public function test_falla_si_xml_descifrado_no_contiene_caf(): void
    {
        $xmlSinCaf = '<?xml version="1.0"?><AUTORIZACION><otro/></AUTORIZACION>';
        $caf = $this->cafConXmlCifrado($xmlSinCaf);

        $this->expectException(CafInvalidoException::class);
        $this->expectExceptionMessage('bloque <CAF>');

        try {
            $this->service->extraerBloqueCaf($caf);
        } catch (CafInvalidoException $e) {
            $this->assertSame(CafInvalidoException::MOTIVO_BLOQUE_CAF_AUSENTE, $e->motivo);
            throw $e;
        }
    }
}
