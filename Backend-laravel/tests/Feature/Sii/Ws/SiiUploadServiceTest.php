<?php

namespace Tests\Feature\Sii\Ws;

use App\Domains\Sii\Services\Ws\SiiUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class SiiUploadServiceTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    private SiiUploadService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        // En tests aceleramos los retries para no bloquear el suite minutos.
        config([
            'sii.upload.timeout_seconds' => 5,
            'sii.upload.retries'         => 3,
            'sii.upload.retry_delay_ms'  => 1,
        ]);

        $this->service = new SiiUploadService();
    }

    private function respuestaSii(string $body, int $status = 200)
    {
        return Http::response($body, $status, ['Content-Type' => 'text/html']);
    }

    public function test_subir_con_respuesta_exitosa_retorna_track_id(): void
    {
        Http::fake([
            'maullin.sii.cl/cgi_dte/UPL/DTEUpload*' => $this->respuestaSii(
                "RECIBIDO\nTRACKID: 99887766\nERROR: 0\nGLOSA: OK"
            ),
        ]);

        $r = $this->service->subir('<EnvioDTE/>', '76123456', '7', '99999999', '0', 'TOKABC', 'certificacion');

        $this->assertSame('99887766', $r['track_id']);
        $this->assertSame(0, $r['error_code']);
        $this->assertSame('OK', $r['glosa']);
        $this->assertSame(200, $r['http_status']);
        $this->assertFalse($r['transport_failed']);
    }

    public function test_subir_con_ERROR_99_retorna_codigo_99_para_reintentar(): void
    {
        Http::fake([
            '*DTEUpload*' => $this->respuestaSii("TRACKID:\nERROR: 99\nGLOSA: Token expirado"),
        ]);

        $r = $this->service->subir('<x/>', '11', '1', '22', '2', 'TOKVIEJO', 'certificacion');

        $this->assertSame(99, $r['error_code']);
        $this->assertSame('Token expirado', $r['glosa']);
    }

    public function test_subir_con_ERROR_distinto_de_0_o_99_retorna_error_permanente(): void
    {
        Http::fake([
            '*DTEUpload*' => $this->respuestaSii("TRACKID:\nERROR: 7\nGLOSA: Schema invalido"),
        ]);

        $r = $this->service->subir('<x/>', '11', '1', '22', '2', 'TOK', 'certificacion');

        $this->assertSame(7, $r['error_code']);
        $this->assertNull($r['track_id']);
    }

    public function test_subir_con_HTTP_500_marca_transport_failed(): void
    {
        Http::fake([
            '*DTEUpload*' => Http::response('Internal Server Error', 500),
        ]);

        $r = $this->service->subir('<x/>', '11', '1', '22', '2', 'TOK', 'certificacion');

        $this->assertTrue($r['transport_failed']);
        $this->assertSame(500, $r['http_status']);
    }

    public function test_request_headers_incluyen_Cookie_TOKEN_y_User_Agent(): void
    {
        Http::fake([
            '*DTEUpload*' => $this->respuestaSii("TRACKID: 1\nERROR: 0"),
        ]);

        $this->service->subir('<x/>', '11', '1', '22', '2', 'MI-TOKEN-XYZ', 'certificacion');

        Http::assertSent(function (Request $r) {
            $cookieHdr = $r->header('Cookie')[0] ?? '';
            $ua        = $r->header('User-Agent')[0] ?? '';
            return $cookieHdr === 'TOKEN=MI-TOKEN-XYZ'
                && $ua !== ''
                && str_contains($ua, 'Mozilla');
        });
    }

    public function test_parseo_extrae_TRACKID_y_GLOSA_de_respuesta_HTML(): void
    {
        $html = '<html><body><pre>'
              . "TRACKID: 1234567890\nERROR: 0\nGLOSA: Recibido OK"
              . '</pre></body></html>';

        $r = $this->service->parsearRespuesta($html);

        $this->assertSame('1234567890', $r['track_id']);
        $this->assertSame(0, $r['error_code']);
        $this->assertSame('Recibido OK', $r['glosa']);
    }

    public function test_request_body_para_auditoria_redacta_token_y_no_incluye_xml(): void
    {
        Http::fake(['*DTEUpload*' => $this->respuestaSii("TRACKID: 1\nERROR: 0")]);

        $xml = str_repeat('<X>contenido grande</X>', 1000);
        $r = $this->service->subir($xml, '11', '1', '22', '2', 'TOKEN_SECRETO', 'certificacion');

        $this->assertStringNotContainsString('TOKEN_SECRETO', $r['request_body']);
        $this->assertStringContainsString('[REDACTED]', $r['request_body']);
        $this->assertStringNotContainsString('contenido grande', $r['request_body']);
        $this->assertStringContainsString('persistido en sii_dte_emitido', $r['request_body']);
    }
}
