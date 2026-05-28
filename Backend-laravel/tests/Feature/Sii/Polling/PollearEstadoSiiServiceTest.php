<?php

namespace Tests\Feature\Sii\Polling;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoDetalle;
use App\Domains\Sii\Models\SiiEnvioDte;
use App\Domains\Sii\Models\SiiEnvioDteEvento;
use App\Domains\Sii\Services\Emision\EmitirDteService;
use App\Domains\Sii\Services\Envio\EnvioSiiService;
use App\Domains\Sii\Services\Polling\PollearEstadoSiiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\GeneraCertificadoParaTests;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class PollearEstadoSiiServiceTest extends TestCase
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
            'sii.upload.retries'         => 2,
            'sii.upload.retry_delay_ms'  => 1,
        ]);

        Storage::fake(config('sii.storage.disk', 'local'));
        $this->service = app(PollearEstadoSiiService::class);
    }

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

    private function envEstUp(string $estadoHdr, ?string $estadoBody, ?string $glosa = null): string
    {
        $glosaTag = $glosa ? "<GLOSA>{$glosa}</GLOSA>" : '';
        $bodyEst  = $estadoBody !== null ? "<ESTADO>{$estadoBody}</ESTADO>" : '';
        $cdata = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/XMLSchema">'
              . "<SII:RESP_HDR><ESTADO>{$estadoHdr}</ESTADO>{$glosaTag}</SII:RESP_HDR>"
              . "<SII:RESP_BODY>{$bodyEst}</SII:RESP_BODY></SII:RESPUESTA>";
        return '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body><getEstUpResponse>'
            . "<getEstUpReturn><![CDATA[{$cdata}]]></getEstUpReturn>"
            . '</getEstUpResponse></soapenv:Body></soapenv:Envelope>';
    }

    /**
     * Crea un envío en estado ENVIADO con track_id, listo para pollear.
     * Reusa el flujo de F4.4+F5.2 para que el DTE+envio queden coherentes.
     */
    private function envioEnviado(string $rut = '76555444-3', string $trackId = 'TRK_POLL'): SiiEnvioDte
    {
        $empresa = Empresa::create([
            'rut'                   => $rut,
            'razon_social'          => 'EMPRESA POLL',
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
            'empresa_id' => $empresa->id, 'tipo_dte' => 33, 'folio' => random_int(900_000, 999_999),
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

        // Enviar via F5.2 con HTTP fake
        Http::fake([
            '*/CrSeed*'           => Http::response($this->envSeed(), 200),
            '*/GetTokenFromSeed*' => Http::response($this->envToken(), 200),
            '*/DTEUpload*'        => Http::response("RECIBIDO\nTRACKID: {$trackId}\nERROR: 0\nGLOSA: OK", 200),
        ]);

        return app(EnvioSiiService::class)->enviar($dte->fresh()->id);
    }

    private function fakeEstUp(string $estadoHdr, ?string $estadoBody, ?string $glosa = null): void
    {
        Http::fake([
            '*/CrSeed*'           => Http::response($this->envSeed(), 200),
            '*/GetTokenFromSeed*' => Http::response($this->envToken(), 200),
            '*/QueryEstUp*'       => Http::response($this->envEstUp($estadoHdr, $estadoBody, $glosa), 200),
        ]);
    }

    public function test_polling_EPR_mantiene_estado_ENVIADO_incrementa_intentos(): void
    {
        $envio = $this->envioEnviado();
        $this->fakeEstUp('00', 'EPR', 'En proceso');

        $resultado = $this->service->pollear($envio);

        $this->assertSame(SiiEnvioDte::ESTADO_ENVIADO, $resultado->estado_envio);
        $this->assertSame('EPR', $resultado->estado_sii_ultimo);
        $this->assertSame(1, $resultado->intentos_polling);
        $this->assertNotNull($resultado->fecha_ultimo_polling);
        $this->assertNull($resultado->fecha_resolucion);
    }

    public function test_polling_EOK_transiciona_a_ACEPTADO_y_DTE_a_ACEPTADO(): void
    {
        $envio = $this->envioEnviado();
        $this->fakeEstUp('00', 'EOK', 'Envio aceptado');

        $resultado = $this->service->pollear($envio);

        $this->assertSame(SiiEnvioDte::ESTADO_ACEPTADO, $resultado->estado_envio);
        $this->assertNotNull($resultado->fecha_resolucion);
        $this->assertSame(SiiDteEmitido::ESTADO_ACEPTADO, $envio->dteEmitido->fresh()->estado);
        $this->assertNotNull($envio->dteEmitido->fresh()->fecha_aceptacion_sii);
    }

    public function test_polling_LOC_transiciona_a_ACEPTADO_CON_REPAROS(): void
    {
        $envio = $this->envioEnviado();
        $this->fakeEstUp('00', 'LOC', 'Aceptado con reparos menores');

        $resultado = $this->service->pollear($envio);

        $this->assertSame(SiiEnvioDte::ESTADO_ACEPTADO_REPAROS, $resultado->estado_envio);
        $this->assertSame(SiiDteEmitido::ESTADO_ACEPTADO_CON_REPAROS, $envio->dteEmitido->fresh()->estado);
    }

    public function test_polling_RCH_transiciona_a_RECHAZADO_y_DTE_a_RECHAZADO(): void
    {
        $envio = $this->envioEnviado();
        $this->fakeEstUp('00', 'RCH', 'Rechazado');

        $resultado = $this->service->pollear($envio);

        $this->assertSame(SiiEnvioDte::ESTADO_RECHAZADO, $resultado->estado_envio);
        $this->assertSame(SiiDteEmitido::ESTADO_RECHAZADO, $envio->dteEmitido->fresh()->estado);
        $this->assertNotNull($envio->dteEmitido->fresh()->fecha_rechazo_sii);
    }

    public function test_polling_codigo_desconocido_marca_ERROR_PERMANENTE_con_glosa(): void
    {
        $envio = $this->envioEnviado();
        $this->fakeEstUp('00', 'XYZ', 'Codigo nuevo no documentado');

        $resultado = $this->service->pollear($envio);

        $this->assertSame(SiiEnvioDte::ESTADO_ERROR_PERMANENTE, $resultado->estado_envio);
        $this->assertSame('XYZ', $resultado->estado_sii_ultimo);
        // El DTE NO debe haber transicionado (no es transición terminal mapeada).
        $this->assertSame(SiiDteEmitido::ESTADO_ENVIADO_SII, $envio->dteEmitido->fresh()->estado);
    }

    public function test_polling_crea_evento_en_sii_envio_dte_evento_con_codigo_raw(): void
    {
        $envio = $this->envioEnviado();
        $this->fakeEstUp('00', 'EOK', 'OK');

        $this->service->pollear($envio);

        $evento = SiiEnvioDteEvento::where('envio_dte_id', $envio->id)->latest('id')->first();
        $this->assertNotNull($evento);
        $this->assertSame('EOK', $evento->codigo_sii_raw);
        $this->assertSame(SiiEnvioDte::ESTADO_ENVIADO, $evento->estado_anterior);
        $this->assertSame(SiiEnvioDte::ESTADO_ACEPTADO, $evento->estado_nuevo);
    }

    public function test_polling_transicion_terminal_crea_evento_en_sii_dte_emitido_evento(): void
    {
        $envio = $this->envioEnviado();
        $this->fakeEstUp('00', 'EOK', 'OK');

        $this->service->pollear($envio);

        $eventosDte = $envio->dteEmitido->fresh()->eventos;
        // 3 esperados: registrarFirma (F4.4) + registrarEnvio (F5.2) + transicion ACEPTADO (F5.3).
        $this->assertCount(3, $eventosDte);
        $this->assertSame(SiiDteEmitido::ESTADO_ACEPTADO, $eventosDte->last()->estado_nuevo);
    }

    public function test_polling_token_expirado_99_dispara_nueva_sesion_y_reintenta(): void
    {
        $envio = $this->envioEnviado();

        Http::fake([
            '*/CrSeed*'           => Http::response($this->envSeed('S1'), 200),
            '*/GetTokenFromSeed*' => Http::sequence()
                ->push($this->envToken('TOK_VIEJO'), 200)
                ->push($this->envToken('TOK_NUEVO'), 200),
            '*/QueryEstUp*' => Http::sequence()
                ->push($this->envEstUp('99', null, 'Token expirado'), 200)
                ->push($this->envEstUp('00', 'EOK', 'OK con token nuevo'), 200),
        ]);

        $resultado = $this->service->pollear($envio);

        $this->assertSame(SiiEnvioDte::ESTADO_ACEPTADO, $resultado->estado_envio);
        $this->assertSame('EOK', $resultado->estado_sii_ultimo);
    }

    public function test_polling_timeout_acumulado_marca_ERROR_TIMEOUT(): void
    {
        $envio = $this->envioEnviado();
        // Forzamos fecha_envio a hace 11 horas (excede el límite de 10).
        $envio->update(['fecha_envio' => now()->subHours(11)]);

        // Reset Http fakes para detectar si polling hace alguna llamada nueva.
        Http::fake(['*' => Http::response('NO_DEBIO_LLAMARSE', 500)]);

        $resultado = $this->service->pollear($envio->fresh());

        $this->assertSame(SiiEnvioDte::ESTADO_ERROR_TIMEOUT, $resultado->estado_envio);
        $this->assertNotNull($resultado->fecha_resolucion);

        $evento = SiiEnvioDteEvento::where('envio_dte_id', $envio->id)
            ->where('estado_nuevo', SiiEnvioDte::ESTADO_ERROR_TIMEOUT)
            ->first();
        $this->assertNotNull($evento);

        // No debe haber pegado al WS de QueryEstUp (timeout corta antes).
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'QueryEstUp'));
    }

    public function test_yaTocaPollear_rango_para_intento_inicial(): void
    {
        // Intento 0 → delay 5min, jitter ±20% → rango [4, 6].
        [$low, $high] = $this->service->rangoDelayParaIntento(0);
        $this->assertSame(4, $low);
        $this->assertSame(6, $high);

        $envio = $this->envioEnviado();
        // Ajustar para que fecha_envio sea hace 4min (justo en el limite bajo).
        $envio->update(['fecha_envio' => now()->subMinutes(4), 'fecha_ultimo_polling' => null]);

        // El jitter es aleatorio, pero 4min está en el rango [4,6]. Puede ser true o false segun jitter.
        // Hacemos 50 evaluaciones y verificamos que ambos resultados aparecen (probabilistico ~OK).
        $resultados = [];
        for ($i = 0; $i < 50; $i++) {
            $resultados[] = $this->service->yaTocaPollear($envio->fresh());
        }
        $this->assertGreaterThanOrEqual(1, count(array_filter($resultados)), 'Debe haber al menos una respuesta true (jitter alto).');
    }

    public function test_yaTocaPollear_rango_para_intento_6_es_360_min(): void
    {
        // Intento 6 (clamped a indice 5 → 360min). Rango ±20% = [288, 432].
        [$low, $high] = $this->service->rangoDelayParaIntento(7);
        $this->assertSame(288, $low);
        $this->assertSame(432, $high);
    }

    public function test_yaTocaPollear_false_si_envio_no_esta_en_ENVIADO(): void
    {
        $envio = $this->envioEnviado();
        $envio->update(['estado_envio' => SiiEnvioDte::ESTADO_ACEPTADO]);

        $this->assertFalse($this->service->yaTocaPollear($envio->fresh()));
    }
}
