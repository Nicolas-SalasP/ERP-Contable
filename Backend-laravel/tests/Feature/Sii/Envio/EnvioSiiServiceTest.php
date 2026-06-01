<?php

namespace Tests\Feature\Sii\Envio;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Exceptions\EnvioSiiException;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoDetalle;
use App\Domains\Sii\Models\SiiDteEmitidoEvento;
use App\Domains\Sii\Models\SiiEnvioDte;
use App\Domains\Sii\Services\Emision\EmitirDteService;
use App\Domains\Sii\Services\Envio\EnvioSiiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\GeneraCertificadoParaTests;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class EnvioSiiServiceTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;
    use GeneraCertificadoParaTests;

    private EnvioSiiService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        if ($this->localizarOpensslCnf() === null) {
            $this->markTestSkipped('openssl.cnf no encontrado.');
        }

        config([
            'sii.upload.timeout_seconds' => 5,
            'sii.upload.retries'         => 3,
            'sii.upload.retry_delay_ms'  => 1,
        ]);

        Storage::fake(config('sii.storage.disk', 'local'));
        $this->service = app(EnvioSiiService::class);
    }

    /**
     * Crea empresa + cert + caf + DTE FIRMADO listo para enviar.
     */
    private function dteFirmado(string $rut = '76555444-3'): SiiDteEmitido
    {
        $empresa = Empresa::create([
            'rut'                   => $rut,
            'razon_social'          => 'EMPRESA ENVIO',
            'giro_emisor'           => 'Servicios',
            'codigo_actividad_sii'  => 471910,
            'direccion'             => 'X 1', 'comuna' => 'Stgo', 'ciudad' => 'Stgo',
            'resolucion_sii_numero' => 80,
            'resolucion_sii_fecha'  => '2024-08-22',
            'ambiente_sii'          => 'certificacion',
        ]);
        $this->crearCertActivoParaEmpresa($empresa, 'TEST ' . $rut);
        $this->crearCafActivoParaEmpresa($empresa, 33, 1, 50);

        $dte = SiiDteEmitido::create([
            'empresa_id'           => $empresa->id,
            'tipo_dte'             => 33,
            'folio'                => random_int(900_000, 999_999),
            'fecha_emision'        => now()->toDateString(),
            'emisor_rut'           => $rut,
            'emisor_razon_social'  => $empresa->razon_social,
            'emisor_giro'          => 'Servicios',
            'emisor_acteco'        => 471910,
            'emisor_direccion'     => 'X 1',
            'emisor_comuna'        => 'Santiago',
            'receptor_rut'         => '66666666-6',
            'receptor_razon_social' => 'CLIENTE PRUEBA',
            'moneda'               => 'CLP',
            'monto_neto'           => 1000,
            'monto_exento'         => 0,
            'tasa_iva'             => 19.00,
            'iva'                  => 190,
            'monto_total'          => 1190,
            'estado'               => SiiDteEmitido::ESTADO_BORRADOR,
            'es_cedible'           => true,
        ]);
        SiiDteEmitidoDetalle::create([
            'dte_emitido_id'  => $dte->id,
            'numero_linea'    => 1,
            'nombre_item'     => 'Producto',
            'cantidad'        => 1,
            'precio_unitario' => 1000,
            'monto_item'      => 1000,
        ]);

        return app(EmitirDteService::class)->emitir($dte->id);
    }

    private function envelopeSeed(string $semilla = 'SEED-X'): string
    {
        $cdata = "<SII:RESPUESTA xmlns:SII=\"http://www.sii.cl/XMLSchema\"><SII:RESP_HDR><ESTADO>00</ESTADO></SII:RESP_HDR><SII:RESP_BODY><SEMILLA>{$semilla}</SEMILLA></SII:RESP_BODY></SII:RESPUESTA>";
        return '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body><getSeedResponse>'
            . "<getSeedReturn><![CDATA[{$cdata}]]></getSeedReturn>"
            . '</getSeedResponse></soapenv:Body></soapenv:Envelope>';
    }

    private function envelopeToken(string $token = 'TOKEN-X'): string
    {
        $cdata = "<SII:RESPUESTA xmlns:SII=\"http://www.sii.cl/XMLSchema\"><SII:RESP_HDR><ESTADO>00</ESTADO></SII:RESP_HDR><SII:RESP_BODY><TOKEN>{$token}</TOKEN></SII:RESP_BODY></SII:RESPUESTA>";
        return '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body><getTokenResponse>'
            . "<getTokenReturn><![CDATA[{$cdata}]]></getTokenReturn>"
            . '</getTokenResponse></soapenv:Body></soapenv:Envelope>';
    }

    private function fakeSiiOk(string $trackId = '99887766'): void
    {
        Http::fake([
            '*/CrSeed*'           => Http::response($this->envelopeSeed(), 200),
            '*/GetTokenFromSeed*' => Http::response($this->envelopeToken(), 200),
            '*/DTEUpload*'        => Http::response("RECIBIDO\nTRACKID: {$trackId}\nERROR: 0\nGLOSA: OK", 200),
        ]);
    }

    public function test_envia_dte_FIRMADO_transiciona_a_ENVIADO_SII_con_track_id(): void
    {
        $dte = $this->dteFirmado();
        $this->fakeSiiOk('TRK_001');

        $envio = $this->service->enviar($dte->id);

        $this->assertSame(SiiEnvioDte::ESTADO_ENVIADO, $envio->estado_envio);
        $this->assertSame('TRK_001', $envio->track_id);
        $this->assertSame(200, $envio->http_status_ultimo_envio);
        $this->assertSame(SiiDteEmitido::ESTADO_ENVIADO_SII, $dte->fresh()->estado);
        $this->assertSame('TRK_001', $dte->fresh()->track_id);
    }

    public function test_persiste_bodies_cifrados_request_y_respuesta(): void
    {
        $dte = $this->dteFirmado();
        $this->fakeSiiOk('TRK_002');

        $envio = $this->service->enviar($dte->id);

        $this->assertNotNull($envio->request_body_completo_cifrado);
        $this->assertNotNull($envio->respuesta_body_completo_cifrado);

        $reqPlain = Crypt::decryptString($envio->request_body_completo_cifrado);
        $respPlain = Crypt::decryptString($envio->respuesta_body_completo_cifrado);

        $this->assertStringContainsString('rutSender', $reqPlain);
        $this->assertStringContainsString('TRK_002', $respPlain);
    }

    public function test_crea_evento_en_sii_dte_emitido_evento_con_track_id_en_payload(): void
    {
        $dte = $this->dteFirmado();
        $this->fakeSiiOk('TRK_EVT');

        $this->service->enviar($dte->id);

        // El DTE debe tener 2 eventos ahora: registrarFirma (F4.4) + registrarEnvio (F5.2).
        $eventos = SiiDteEmitidoEvento::where('dte_emitido_id', $dte->id)->orderBy('id')->get();
        $this->assertCount(2, $eventos);

        $envioEvt = $eventos->last();
        $this->assertSame(SiiDteEmitido::ESTADO_FIRMADO, $envioEvt->estado_anterior);
        $this->assertSame(SiiDteEmitido::ESTADO_ENVIADO_SII, $envioEvt->estado_nuevo);
        $this->assertSame('TRK_EVT', $envioEvt->payload['track_id']);
        $this->assertArrayHasKey('envio_id', $envioEvt->payload);
        $this->assertArrayHasKey('sesion_id', $envioEvt->payload);
        $this->assertArrayHasKey('intentos_envio', $envioEvt->payload);
    }

    public function test_aislamiento_multitenant_envio_de_empresa_A_no_visible_a_empresa_B(): void
    {
        $dteA = $this->dteFirmado('76111111-1');
        $this->fakeSiiOk('TRK_A');
        $envioA = $this->service->enviar($dteA->id);

        $dteB = $this->dteFirmado('77222222-2');
        $this->fakeSiiOk('TRK_B');
        $envioB = $this->service->enviar($dteB->id);

        $enviosVisiblesParaA = SiiEnvioDte::porEmpresa($dteA->empresa_id)->get();
        $this->assertCount(1, $enviosVisiblesParaA);
        $this->assertSame($envioA->id, $enviosVisiblesParaA->first()->id);

        $enviosVisiblesParaB = SiiEnvioDte::porEmpresa($dteB->empresa_id)->get();
        $this->assertCount(1, $enviosVisiblesParaB);
        $this->assertSame($envioB->id, $enviosVisiblesParaB->first()->id);
    }

    public function test_lanza_EnvioSiiException_yaEnviado_si_DTE_ya_tiene_envio_exitoso(): void
    {
        $dte = $this->dteFirmado();
        $this->fakeSiiOk('TRK_PRIMERO');
        $primero = $this->service->enviar($dte->id);
        $this->assertSame('TRK_PRIMERO', $primero->track_id);

        // Segundo intento: DTE ya esta en ENVIADO_SII y tiene un envio exitoso.
        try {
            $this->service->enviar($dte->id);
            $this->fail('Debio lanzar EnvioSiiException::yaEnviado');
        } catch (EnvioSiiException $e) {
            $this->assertSame(EnvioSiiException::MOTIVO_YA_ENVIADO, $e->motivo);
            $this->assertSame('TRK_PRIMERO', $e->contexto['track_id_previo']);
        }
    }

    public function test_lanza_EnvioSiiException_dteNoFirmado_si_estado_es_BORRADOR(): void
    {
        $dte = SiiDteEmitido::factory()->create([
            'estado' => SiiDteEmitido::ESTADO_BORRADOR,
        ]);

        try {
            $this->service->enviar($dte->id);
            $this->fail('Debio lanzar EnvioSiiException::dteNoFirmado');
        } catch (EnvioSiiException $e) {
            $this->assertSame(EnvioSiiException::MOTIVO_DTE_NO_FIRMADO, $e->motivo);
        }
    }

    public function test_reintento_automatico_si_SII_responde_ERROR_99_token_expirado(): void
    {
        $dte = $this->dteFirmado();

        Http::fake([
            '*/CrSeed*'           => Http::response($this->envelopeSeed('SEED-1'), 200),
            '*/GetTokenFromSeed*' => Http::sequence()
                ->push($this->envelopeToken('TOK_VIEJO'), 200)
                ->push($this->envelopeToken('TOK_NUEVO'), 200),
            '*/DTEUpload*' => Http::sequence()
                ->push("TRACKID:\nERROR: 99\nGLOSA: Token expirado", 200)
                ->push("RECIBIDO\nTRACKID: TRK_RETRY\nERROR: 0\nGLOSA: OK", 200),
        ]);

        $envio = $this->service->enviar($dte->id);

        $this->assertSame(SiiEnvioDte::ESTADO_ENVIADO, $envio->estado_envio);
        $this->assertSame('TRK_RETRY', $envio->track_id);
        $this->assertSame(2, $envio->intentos_envio, 'Debe haber 2 intentos: el de 99 + el retry exitoso.');
    }

    public function test_marca_ERROR_PERMANENTE_si_SII_responde_ERROR_distinto_de_0_y_99(): void
    {
        $dte = $this->dteFirmado();
        Http::fake([
            '*/CrSeed*'           => Http::response($this->envelopeSeed(), 200),
            '*/GetTokenFromSeed*' => Http::response($this->envelopeToken(), 200),
            '*/DTEUpload*'        => Http::response("TRACKID:\nERROR: 7\nGLOSA: Schema invalido", 200),
        ]);

        $envio = $this->service->enviar($dte->id);

        $this->assertSame(SiiEnvioDte::ESTADO_ERROR_PERMANENTE, $envio->estado_envio);
        $this->assertNull($envio->track_id);
        $this->assertSame('Schema invalido', $envio->glosa_sii);
        // El DTE NO debe haber transicionado.
        $this->assertSame(SiiDteEmitido::ESTADO_FIRMADO, $dte->fresh()->estado);
    }

    public function test_marca_ERROR_TRANSPORTE_si_3_intentos_fallan_con_5xx(): void
    {
        $dte = $this->dteFirmado();
        Http::fake([
            '*/CrSeed*'           => Http::response($this->envelopeSeed(), 200),
            '*/GetTokenFromSeed*' => Http::response($this->envelopeToken(), 200),
            '*/DTEUpload*'        => Http::response('Internal Server Error', 500),
        ]);

        $envio = $this->service->enviar($dte->id);

        $this->assertSame(SiiEnvioDte::ESTADO_ERROR_TRANSPORTE, $envio->estado_envio);
        $this->assertNull($envio->track_id);
        $this->assertSame(500, $envio->http_status_ultimo_envio);
        $this->assertSame(SiiDteEmitido::ESTADO_FIRMADO, $dte->fresh()->estado);
    }

    public function test_DTE_permanece_en_FIRMADO_si_envio_falla(): void
    {
        $dte = $this->dteFirmado();
        Http::fake([
            '*/CrSeed*'           => Http::response($this->envelopeSeed(), 200),
            '*/GetTokenFromSeed*' => Http::response($this->envelopeToken(), 200),
            '*/DTEUpload*'        => Http::response('', 500),
        ]);

        $this->service->enviar($dte->id);

        $this->assertSame(SiiDteEmitido::ESTADO_FIRMADO, $dte->fresh()->estado);
        // No debe haberse creado evento registrarEnvio (solo el registrarFirma de F4.4).
        $eventos = SiiDteEmitidoEvento::where('dte_emitido_id', $dte->id)->get();
        $this->assertCount(1, $eventos);
        $this->assertSame(SiiDteEmitido::ESTADO_FIRMADO, $eventos->first()->estado_nuevo);
    }

    public function test_request_body_cifrado_no_incluye_xml_completo_solo_metadata(): void
    {
        $dte = $this->dteFirmado();
        $this->fakeSiiOk('TRK_R');

        $envio = $this->service->enviar($dte->id);
        $reqPlain = Crypt::decryptString($envio->request_body_completo_cifrado);

        // El XML del DTE incluye "Producto" (nombre del item). Si el reqBody
        // tuviera el XML completo, contendría ese string.
        $this->assertStringNotContainsString('Producto', $reqPlain);
        $this->assertStringContainsString('persistido en sii_dte_emitido', $reqPlain);
    }

    public function test_integridad_xml_verificada_antes_de_enviar_via_XmlDteIntegrityService(): void
    {
        $dte = $this->dteFirmado();
        $this->fakeSiiOk('TRK_INT');

        // Corrompemos el XML en disco simulando bit-flip. El service debe
        // usar el fallback de BD (HARDENING R2) y enviar exitoso.
        Storage::disk(config('sii.storage.disk'))->put($dte->xml_path, '<CORRUPTO/>');

        $envio = $this->service->enviar($dte->id);

        $this->assertSame(SiiEnvioDte::ESTADO_ENVIADO, $envio->estado_envio, 'Fallback BD debe haber salvado el envio.');

        // Verificamos que el body cifrado en respuesta contiene el track_id
        // (señal de que se envió y SII respondió OK).
        $respPlain = Crypt::decryptString($envio->respuesta_body_completo_cifrado);
        $this->assertStringContainsString('TRK_INT', $respPlain);
    }
}
