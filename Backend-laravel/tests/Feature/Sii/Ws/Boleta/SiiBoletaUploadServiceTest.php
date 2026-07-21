<?php

namespace Tests\Feature\Sii\Ws\Boleta;

use App\Domains\Sii\Services\Ws\Boleta\SiiBoletaUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class SiiBoletaUploadServiceTest extends TestCase
{
    use PreparaEntornoBase;
    use RefreshDatabase;

    private SiiBoletaUploadService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        config([
            'sii.upload.timeout_seconds' => 5,
            'sii.upload.retries' => 3,
            'sii.upload.retry_delay_ms' => 1,
        ]);

        $this->service = new SiiBoletaUploadService;
    }

    public function test_subir_con_respuesta_re_c_retorna_error_code_cero(): void
    {
        Http::fake([
            'pangal.sii.cl/recursos/v1/boleta.electronica.envio*' => Http::response(json_encode([
                'rut_emisor' => '76123456-7',
                'rut_envia' => '76123456-7',
                'trackid' => 1014,
                'fecha_recepcion' => '2026-07-15 10:00:00',
                'estado' => 'REC',
                'file' => 'boleta-2026-07-15-001.xml',
            ]), 200),
        ]);

        $r = $this->service->subir('<EnvioBOLETA/>', '76123456', '7', '76123456', '7', 'TOKABC', 'certificacion');

        $this->assertSame('1014', $r['track_id']);
        $this->assertSame(0, $r['error_code']);
        $this->assertNull($r['glosa']);
        $this->assertSame(200, $r['http_status']);
        $this->assertFalse($r['transport_failed']);
    }

    public function test_subir_con_htt_p_401_retorna_codigo_99_para_reintentar(): void
    {
        Http::fake([
            '*boleta.electronica.envio*' => Http::response(json_encode(['message' => 'Token invalido']), 401),
        ]);

        $r = $this->service->subir('<x/>', '11', '1', '22', '2', 'TOKVIEJO', 'certificacion');

        $this->assertSame(99, $r['error_code']);
        $this->assertStringContainsString('Token invalido', $r['glosa']);
        $this->assertFalse($r['transport_failed']);
    }

    public function test_subir_con_htt_p_400_retorna_error_permanente(): void
    {
        Http::fake([
            '*boleta.electronica.envio*' => Http::response(json_encode(['message' => 'XML invalido']), 400),
        ]);

        $r = $this->service->subir('<x/>', '11', '1', '22', '2', 'TOK', 'certificacion');

        $this->assertSame(-2, $r['error_code']);
        $this->assertNull($r['track_id']);
        $this->assertFalse($r['transport_failed']);
    }

    public function test_subir_con_htt_p_500_marca_transport_failed(): void
    {
        Http::fake([
            '*boleta.electronica.envio*' => Http::response('Internal Server Error', 500),
        ]);

        $r = $this->service->subir('<x/>', '11', '1', '22', '2', 'TOK', 'certificacion');

        $this->assertTrue($r['transport_failed']);
        $this->assertSame(500, $r['http_status']);
    }

    public function test_request_usa_servidor_de_boleta_no_el_de_factura(): void
    {
        Http::fake([
            '*' => Http::response(json_encode(['trackid' => 1, 'estado' => 'REC']), 200),
        ]);

        $this->service->subir('<x/>', '11', '1', '22', '2', 'TOK', 'certificacion');

        Http::assertSent(function (Request $r) {
            return str_contains($r->url(), 'pangal.sii.cl')
                && ! str_contains($r->url(), 'maullin.sii.cl');
        });
    }

    public function test_request_headers_incluyen_cookie_token(): void
    {
        Http::fake([
            '*boleta.electronica.envio*' => Http::response(json_encode(['trackid' => 1, 'estado' => 'REC']), 200),
        ]);

        $this->service->subir('<x/>', '11', '1', '22', '2', 'MI-TOKEN-XYZ', 'certificacion');

        Http::assertSent(function (Request $r) {
            $cookieHdr = $r->header('Cookie')[0] ?? '';

            return $cookieHdr === 'TOKEN=MI-TOKEN-XYZ';
        });
    }

    public function test_parseo_directo_de_respuesta_json(): void
    {
        $body = json_encode([
            'rut_emisor' => '76123456-7',
            'trackid' => 555,
            'estado' => 'REC',
        ]);

        $r = $this->service->parsearRespuesta($body, 200);

        $this->assertSame('555', $r['track_id']);
        $this->assertSame(0, $r['error_code']);
    }

    public function test_request_body_para_auditoria_redacta_token_y_no_incluye_xml(): void
    {
        Http::fake(['*boleta.electronica.envio*' => Http::response(json_encode(['trackid' => 1, 'estado' => 'REC']), 200)]);

        $xml = str_repeat('<X>contenido grande</X>', 1000);
        $r = $this->service->subir($xml, '11', '1', '22', '2', 'TOKEN_SECRETO', 'certificacion');

        $this->assertStringNotContainsString('TOKEN_SECRETO', $r['request_body']);
        $this->assertStringContainsString('[REDACTED]', $r['request_body']);
        $this->assertStringNotContainsString('contenido grande', $r['request_body']);
    }
}
