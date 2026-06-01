<?php

namespace Tests\Feature\Sii\Ws;

use App\Domains\Sii\Services\Ws\SiiEstadoUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class SiiEstadoUpServiceTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    private SiiEstadoUpService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        config([
            'sii.upload.timeout_seconds' => 5,
            'sii.upload.retries'         => 3,
            'sii.upload.retry_delay_ms'  => 1,
        ]);
        $this->service = new SiiEstadoUpService();
    }

    private function envelope(string $estadoHdr, ?string $estadoBody = null, ?string $glosa = null): string
    {
        $glosaTag = $glosa ? "<GLOSA>{$glosa}</GLOSA>" : '';
        $bodyEstado = $estadoBody !== null ? "<ESTADO>{$estadoBody}</ESTADO>" : '';
        $cdata = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/XMLSchema">'
              . "<SII:RESP_HDR><ESTADO>{$estadoHdr}</ESTADO>{$glosaTag}</SII:RESP_HDR>"
              . "<SII:RESP_BODY>{$bodyEstado}</SII:RESP_BODY>"
              . '</SII:RESPUESTA>';

        return '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">'
             . '<soapenv:Body><getEstUpResponse>'
             . "<getEstUpReturn><![CDATA[{$cdata}]]></getEstUpReturn>"
             . '</getEstUpResponse></soapenv:Body></soapenv:Envelope>';
    }

    public function test_consulta_estado_devuelve_codigo_sii_EOK(): void
    {
        Http::fake([
            'maullin.sii.cl/DTEWS/QueryEstUp*' => Http::response($this->envelope('00', 'EOK', 'Envio Aceptado'), 200),
        ]);

        $r = $this->service->consultar('76123456', '7', '999', 'TOK', 'certificacion');

        $this->assertSame('00', $r['estado_hdr']);
        $this->assertSame('EOK', $r['estado_sii']);
        $this->assertSame('Envio Aceptado', $r['glosa']);
        $this->assertSame(200, $r['http_status']);
        $this->assertFalse($r['transport_failed']);
    }

    public function test_consulta_estado_devuelve_codigo_RPR_rechazado(): void
    {
        Http::fake([
            '*/QueryEstUp*' => Http::response($this->envelope('00', 'RPR', 'Envio rechazado'), 200),
        ]);

        $r = $this->service->consultar('76123456', '7', '999', 'TOK', 'certificacion');

        $this->assertSame('RPR', $r['estado_sii']);
        $this->assertSame('Envio rechazado', $r['glosa']);
    }

    public function test_estado_hdr_99_indica_token_expirado(): void
    {
        Http::fake([
            '*/QueryEstUp*' => Http::response($this->envelope('99', null, 'Token expirado'), 200),
        ]);

        $r = $this->service->consultar('76123456', '7', '999', 'TOK_VIEJO', 'certificacion');

        $this->assertSame('99', $r['estado_hdr']);
        $this->assertNull($r['estado_sii']);
    }

    public function test_marca_transport_failed_si_3_intentos_fallan_con_5xx(): void
    {
        Http::fake([
            '*/QueryEstUp*' => Http::response('Internal Error', 500),
        ]);

        $r = $this->service->consultar('76123456', '7', '999', 'TOK', 'certificacion');

        $this->assertTrue($r['transport_failed']);
        $this->assertSame(500, $r['http_status']);
    }

    public function test_http_request_incluye_Cookie_TOKEN_y_track_id(): void
    {
        Http::fake([
            '*/QueryEstUp*' => Http::response($this->envelope('00', 'EPR'), 200),
        ]);

        $this->service->consultar('76123456', '7', 'TRACK_42', 'MI-TOK', 'certificacion');

        Http::assertSent(function (Request $r) {
            $cookie = $r->header('Cookie')[0] ?? '';
            $body   = $r->body();
            return $cookie === 'TOKEN=MI-TOK'
                && str_contains($body, '<TrackId>TRACK_42</TrackId>')
                && str_contains($body, '<Token>MI-TOK</Token>');
        });
    }
}
