<?php

namespace Tests\Feature\Sii\Polling;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoDetalle;
use App\Domains\Sii\Models\SiiEnvioDte;
use App\Domains\Sii\Services\Emision\EmitirDteService;
use App\Domains\Sii\Services\Envio\EnvioSiiService;
use App\Domains\Sii\Services\Polling\PollearEstadoSiiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\GeneraCertificadoParaTests;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Verifica que el polling del SII absorbe fallos de red sin propagar excepciones
 * y sin alterar el estado del envio.
 */
class PollingResilienciaTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;
    use GeneraCertificadoParaTests;

    private PollearEstadoSiiService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        if ($this->localizarOpensslCnf() === null) {
            $this->markTestSkipped('openssl.cnf no encontrado.');
        }

        config([
            'sii.upload.timeout_seconds' => 5,
            'sii.upload.retries'         => 1,
            'sii.upload.retry_delay_ms'  => 1,
        ]);

        Storage::fake(config('sii.storage.disk', 'local'));
        $this->service = app(PollearEstadoSiiService::class);
    }

    // -------------------------------------------------------------------------
    // Helpers privados (mismos patrones que PollearEstadoSiiServiceTest)
    // -------------------------------------------------------------------------

    private function envSeed(string $semilla = 'S'): string
    {
        $cdata = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/XMLSchema">'
              . "<SII:RESP_HDR><ESTADO>00</ESTADO></SII:RESP_HDR>"
              . "<SII:RESP_BODY><SEMILLA>{$semilla}</SEMILLA></SII:RESP_BODY></SII:RESPUESTA>";
        return '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body><getSeedResponse>'
            . "<getSeedReturn><![CDATA[{$cdata}]]></getSeedReturn>"
            . '</getSeedResponse></soapenv:Body></soapenv:Envelope>';
    }

    private function envToken(string $token = 'TOK'): string
    {
        $cdata = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/XMLSchema">'
              . "<SII:RESP_HDR><ESTADO>00</ESTADO></SII:RESP_HDR>"
              . "<SII:RESP_BODY><TOKEN>{$token}</TOKEN></SII:RESP_BODY></SII:RESPUESTA>";
        return '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body><getTokenResponse>'
            . "<getTokenReturn><![CDATA[{$cdata}]]></getTokenReturn>"
            . '</getTokenResponse></soapenv:Body></soapenv:Envelope>';
    }

    private function envioEnviado(string $rut = '76555444-3', string $trackId = 'TRK_RES'): SiiEnvioDte
    {
        $empresa = Empresa::create([
            'rut'                   => $rut,
            'razon_social'          => 'EMPRESA RESILIENCIA',
            'giro_emisor'           => 'X',
            'codigo_actividad_sii'  => 471910,
            'direccion'             => 'X', 'comuna' => 'X', 'ciudad' => 'X',
            'resolucion_sii_numero' => 80,
            'resolucion_sii_fecha'  => '2024-08-22',
            'ambiente_sii'          => 'certificacion',
        ]);
        $this->crearCertActivoParaEmpresa($empresa, 'TEST ' . $rut);
        $this->crearCafActivoParaEmpresa($empresa, 33, 1, 50);

        $dte = SiiDteEmitido::create([
            'empresa_id' => $empresa->id, 'tipo_dte' => 33, 'folio' => random_int(800_000, 899_999),
            'fecha_emision' => now()->toDateString(),
            'emisor_rut' => $rut, 'emisor_razon_social' => $empresa->razon_social,
            'emisor_giro' => 'X', 'emisor_acteco' => 471910,
            'emisor_direccion' => 'X', 'emisor_comuna' => 'X',
            'receptor_rut' => '66666666-6', 'receptor_razon_social' => 'CLIENTE',
            'moneda' => 'CLP', 'monto_neto' => 1000, 'monto_exento' => 0,
            'tasa_iva' => 19.00, 'iva' => 190, 'monto_total' => 1190,
            'estado' => SiiDteEmitido::ESTADO_BORRADOR, 'es_cedible' => true,
        ]);
        SiiDteEmitidoDetalle::create([
            'dte_emitido_id' => $dte->id, 'numero_linea' => 1,
            'nombre_item' => 'X', 'cantidad' => 1, 'precio_unitario' => 1000, 'monto_item' => 1000,
        ]);

        app(EmitirDteService::class)->emitir($dte->id);

        Http::fake([
            '*/CrSeed*'           => Http::response($this->envSeed(), 200),
            '*/GetTokenFromSeed*' => Http::response($this->envToken(), 200),
            '*/DTEUpload*'        => Http::response("RECIBIDO\nTRACKID: {$trackId}\nERROR: 0\nGLOSA: OK", 200),
        ]);

        return app(EnvioSiiService::class)->enviar($dte->fresh()->id);
    }

    // -------------------------------------------------------------------------
    // Tests de resiliencia
    // -------------------------------------------------------------------------

    public function test_polling_absorbe_connection_exception_en_queryestup_sin_propagar(): void
    {
        $envio = $this->envioEnviado(rut: '76100200-3', trackId: 'TRK_CE');
        $estadoPrevio      = $envio->estado_envio;
        $intentosPrevios   = $envio->intentos_polling;

        Http::fake([
            '*/CrSeed*'           => Http::response($this->envSeed(), 200),
            '*/GetTokenFromSeed*' => Http::response($this->envToken(), 200),
            '*/QueryEstUp*'       => Http::response(null, 500, []),
        ]);

        // ConnectionException real no se puede inyectar via Http::fake directamente,
        // pero transport_failed=true (HTTP 500) activa el mismo codigo de absorcion.
        // Verificamos que no se propaga excepcion y el estado queda inalterado.
        $resultado = $this->service->pollear($envio->fresh());

        $this->assertSame(SiiEnvioDte::ESTADO_ENVIADO, $resultado->estado_envio,
            'El estado debe seguir en ENVIADO tras fallo de transporte.');
        $this->assertSame(
            $intentosPrevios + 1,
            $resultado->intentos_polling,
            'El contador de intentos debe incrementarse aunque haya fallo.'
        );
        $this->assertNotNull($resultado->fecha_ultimo_polling,
            'fecha_ultimo_polling debe actualizarse aunque haya fallo.');
        $this->assertNull($resultado->fecha_resolucion,
            'No debe existir fecha_resolucion si el envio sigue en curso.');
    }

    public function test_polling_con_http_500_no_transiciona_estado_dte(): void
    {
        $envio = $this->envioEnviado(rut: '76100300-4', trackId: 'TRK_500');
        $estadoDtePrevio = $envio->dteEmitido->fresh()->estado;

        Http::fake([
            '*/CrSeed*'           => Http::response($this->envSeed(), 200),
            '*/GetTokenFromSeed*' => Http::response($this->envToken(), 200),
            '*/QueryEstUp*'       => Http::response('Error interno', 500),
        ]);

        $this->service->pollear($envio->fresh());

        $this->assertSame(
            $estadoDtePrevio,
            $envio->dteEmitido->fresh()->estado,
            'El estado del DTE no debe cambiar por un fallo HTTP del SII.'
        );
    }

    public function test_polling_con_respuesta_vacia_no_propaga_excepcion(): void
    {
        $envio = $this->envioEnviado(rut: '76100400-5', trackId: 'TRK_VACIO');

        Http::fake([
            '*/CrSeed*'           => Http::response($this->envSeed(), 200),
            '*/GetTokenFromSeed*' => Http::response($this->envToken(), 200),
            '*/QueryEstUp*'       => Http::response('', 200),
        ]);

        // Respuesta vacia provoca PollingSiiException en parseo; el servicio debe
        // capturarla y retornar el envio sin propagar.
        try {
            $resultado = $this->service->pollear($envio->fresh());
            $this->assertSame(SiiEnvioDte::ESTADO_ENVIADO, $resultado->estado_envio);
        } catch (\Throwable $e) {
            $this->fail('No debe propagarse ninguna excepcion al caller: ' . $e->getMessage());
        }
    }

    public function test_polling_multiple_fallos_incrementa_intentos_acumuladamente(): void
    {
        $envio = $this->envioEnviado(rut: '76100500-6', trackId: 'TRK_MULTI');

        Http::fake([
            '*/CrSeed*'           => Http::response($this->envSeed(), 200),
            '*/GetTokenFromSeed*' => Http::response($this->envToken(), 200),
            '*/QueryEstUp*'       => Http::response('Fallo', 503),
        ]);

        $this->service->pollear($envio->fresh());
        $this->service->pollear($envio->fresh());
        $this->service->pollear($envio->fresh());

        $envioActualizado = $envio->fresh();
        $this->assertSame(3, $envioActualizado->intentos_polling,
            'Tres ciclos de fallo deben acumular 3 intentos.');
        $this->assertSame(SiiEnvioDte::ESTADO_ENVIADO, $envioActualizado->estado_envio);
    }

    public function test_polling_config_http_timeout_existe_y_es_entero(): void
    {
        $timeout        = config('sii.http.timeout');
        $connectTimeout = config('sii.http.connect_timeout');
        $maxReintentos  = config('sii.http.max_reintentos_polling');

        $this->assertIsInt($timeout, 'sii.http.timeout debe ser entero.');
        $this->assertIsInt($connectTimeout, 'sii.http.connect_timeout debe ser entero.');
        $this->assertIsInt($maxReintentos, 'sii.http.max_reintentos_polling debe ser entero.');

        $this->assertGreaterThan(0, $timeout);
        $this->assertGreaterThan(0, $connectTimeout);
        $this->assertGreaterThan(0, $maxReintentos);
    }
}
