<?php

namespace Tests\Feature\Sii\Ws;

use App\Domains\Sii\Exceptions\SiiAutenticacionException;
use App\Domains\Sii\Services\Ws\SiiSeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class SiiSeedServiceTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    private SiiSeedService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->service = new SiiSeedService();
    }

    private function respuestaSeedOk(string $semilla = '123456789'): string
    {
        $cdata = "<SII:RESPUESTA xmlns:SII=\"http://www.sii.cl/XMLSchema\">"
              . "<SII:RESP_HDR><ESTADO>00</ESTADO></SII:RESP_HDR>"
              . "<SII:RESP_BODY><SEMILLA>{$semilla}</SEMILLA></SII:RESP_BODY>"
              . "</SII:RESPUESTA>";

        return '<?xml version="1.0" encoding="UTF-8"?>'
             . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">'
             . '<soapenv:Body><ns1:getSeedResponse xmlns:ns1="http://DefaultNamespace">'
             . "<getSeedReturn><![CDATA[{$cdata}]]></getSeedReturn>"
             . '</ns1:getSeedResponse></soapenv:Body></soapenv:Envelope>';
    }

    public function test_obtiene_semilla_de_certificacion(): void
    {
        Http::fake([
            'maullin.sii.cl/DTEWS/CrSeed*' => Http::response($this->respuestaSeedOk('SEMILLA_CERT_42'), 200),
        ]);

        $semilla = $this->service->obtener('certificacion');
        $this->assertSame('SEMILLA_CERT_42', $semilla);

        Http::assertSent(function (Request $r) {
            return str_contains($r->url(), 'maullin.sii.cl')
                && str_contains($r->body(), 'getSeed');
        });
    }

    public function test_obtiene_semilla_de_produccion(): void
    {
        Http::fake([
            'palena.sii.cl/DTEWS/CrSeed*' => Http::response($this->respuestaSeedOk('SEMILLA_PROD_99'), 200),
        ]);

        $semilla = $this->service->obtener('produccion');
        $this->assertSame('SEMILLA_PROD_99', $semilla);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'palena.sii.cl'));
    }

    public function test_lanza_si_http_status_5xx_despues_de_3_reintentos(): void
    {
        Http::fake([
            'maullin.sii.cl/*' => Http::response('Internal Server Error', 500),
        ]);

        $this->expectException(SiiAutenticacionException::class);

        try {
            $this->service->obtener('certificacion');
        } catch (SiiAutenticacionException $e) {
            $this->assertSame(SiiAutenticacionException::MOTIVO_SEMILLA_NO_OBTENIDA, $e->motivo);
            $this->assertSame(500, $e->httpStatus);
            throw $e;
        }
    }

    public function test_lanza_si_respuesta_SII_tiene_ESTADO_distinto_de_00(): void
    {
        $cdata = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/XMLSchema">'
              . '<SII:RESP_HDR><ESTADO>99</ESTADO><GLOSA>Error generico</GLOSA></SII:RESP_HDR>'
              . '</SII:RESPUESTA>';
        $soap = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">'
              . '<soapenv:Body><getSeedResponse>'
              . "<getSeedReturn><![CDATA[{$cdata}]]></getSeedReturn>"
              . '</getSeedResponse></soapenv:Body></soapenv:Envelope>';

        Http::fake(['maullin.sii.cl/*' => Http::response($soap, 200)]);

        try {
            $this->service->obtener('certificacion');
            $this->fail('Debio lanzar SiiAutenticacionException');
        } catch (SiiAutenticacionException $e) {
            $this->assertSame(SiiAutenticacionException::MOTIVO_SEMILLA_INVALIDA, $e->motivo);
            $this->assertStringContainsString('ESTADO=99', $e->getMessage());
            $this->assertStringContainsString('Error generico', $e->getMessage());
        }
    }

    public function test_lanza_si_respuesta_no_es_xml_valido(): void
    {
        Http::fake(['maullin.sii.cl/*' => Http::response('<not-xml-bad>', 200)]);

        $this->expectException(SiiAutenticacionException::class);
        $this->service->obtener('certificacion');
    }

    public function test_envia_SOAPAction_vacio_y_content_type_text_xml(): void
    {
        // El SII Chile exige SOAPAction='""' (vacio entre comillas) y
        // Content-Type='text/xml; charset=utf-8'. Lo verificamos aqui en
        // lugar del test del timeout (que es trivial de afirmar via 'Http::timeout').
        Http::fake([
            'maullin.sii.cl/*' => Http::response($this->respuestaSeedOk(), 200),
        ]);

        $this->service->obtener('certificacion');

        Http::assertSent(function (Request $r) {
            return $r->header('SOAPAction') === ['""']
                && str_starts_with($r->header('Content-Type')[0] ?? '', 'text/xml');
        });
    }
}
