<?php

namespace Tests\Feature\Sii\Integracion;

use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\FacturaDetalle;
use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Events\FacturaListaParaEmitirEvent;
use App\Domains\Sii\Exceptions\FacturaIncompletaParaSii;
use App\Domains\Sii\Listeners\ProcesarFacturaParaSiiListener;
use App\Domains\Sii\Models\SiiDteEmitido;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Monolog\Handler\TestHandler;
use Tests\Concerns\OrquestaFlujoCompletoEnTests;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class ProcesarFacturaParaSiiListenerTest extends TestCase
{
    use OrquestaFlujoCompletoEnTests;
    use PreparaEntornoBase;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        if ($this->localizarOpensslCnf() === null) {
            $this->markTestSkipped('openssl.cnf no encontrado.');
        }

        config([
            'sii.upload.timeout_seconds' => 5,
            'sii.upload.retries' => 2,
            'sii.upload.retry_delay_ms' => 1,
        ]);

        Storage::fake(config('sii.storage.disk', 'local'));
    }

    /**
     * Setup completo: empresa+cert+caf+cliente+factura emisible.
     * Reusa setupEmpresaConFlujoCompleto del trait F5.4 pero ADEMAS crea
     * un Cliente del Comercial y una Factura emisible vinculada a el.
     */
    private function escenarioFacturaEmisible(string $rut = '76555444-3'): array
    {
        $ctx = $this->setupEmpresaConFlujoCompleto(['rut' => $rut]);
        // El setup creo un SiiDteEmitido independiente; no lo usamos para F6.2.
        $ctx['dte']->delete();

        $cliente = Cliente::create([
            'rut' => '11222333-4',
            'razon_social' => 'CLIENTE LISTENER',
            'contacto_email' => 'listener@cli.cl',
            'direccion' => 'X',
            'comuna' => 'Stgo', 'ciudad' => 'Stgo',
            'giro' => 'Comercio',
            'estado' => 'ACTIVO',
            'empresa_id' => $ctx['empresa']->id,
        ]);

        $factura = Factura::create([
            'empresa_id' => $ctx['empresa']->id,
            'codigo_unico' => Factura::generarCodigoUnico(),
            'cliente_id' => $cliente->id,
            'numero_factura' => 'F-LIS-'.random_int(1000, 99999),
            'tipo' => 'VENTA',
            'tipo_documento' => 'FACTURA',
            'tipo_dte' => 33,
            'fecha_emision' => now()->toDateString(),
            'monto_neto' => 1000, 'monto_iva' => 190, 'monto_bruto' => 1190,
            'estado' => 'REGISTRADA',
        ]);
        FacturaDetalle::create([
            'factura_id' => $factura->id, 'numero_linea' => 1,
            'nombre_item' => 'Servicio', 'cantidad' => 1, 'precio_unitario' => 1000,
            'monto_item' => 1000, 'exento' => false,
        ]);

        return [
            'empresa' => $ctx['empresa'],
            'cliente' => $cliente,
            'factura' => $factura->fresh(['cliente', 'empresa', 'detalles']),
        ];
    }

    /**
     * Escenario boleta (tipo_dte 39): empresa+cert+CAF boleta+cliente+factura emisible.
     */
    private function escenarioBoletaEmisible(string $rut = '76666555-2'): array
    {
        $ctx = $this->setupEmpresaConFlujoCompleto(['rut' => $rut, 'tipo_dte' => 39]);
        $ctx['dte']->delete();

        $cliente = Cliente::create([
            'rut' => '11333222-5',
            'razon_social' => 'CLIENTE BOLETA LISTENER',
            'contacto_email' => 'boleta-listener@cli.cl',
            'direccion' => 'X',
            'comuna' => 'Stgo', 'ciudad' => 'Stgo',
            'giro' => 'Comercio',
            'estado' => 'ACTIVO',
            'empresa_id' => $ctx['empresa']->id,
        ]);

        $factura = Factura::create([
            'empresa_id' => $ctx['empresa']->id,
            'codigo_unico' => Factura::generarCodigoUnico(),
            'cliente_id' => $cliente->id,
            'numero_factura' => 'B-LIS-'.random_int(1000, 99999),
            'tipo' => 'VENTA',
            'tipo_documento' => 'BOLETA',
            'tipo_dte' => 39,
            'fecha_emision' => now()->toDateString(),
            'monto_neto' => 1000, 'monto_iva' => 190, 'monto_bruto' => 1190,
            'estado' => 'REGISTRADA',
        ]);
        FacturaDetalle::create([
            'factura_id' => $factura->id, 'numero_linea' => 1,
            'nombre_item' => 'Servicio', 'cantidad' => 1, 'precio_unitario' => 1000,
            'monto_item' => 1000, 'exento' => false,
        ]);

        return [
            'empresa' => $ctx['empresa'],
            'cliente' => $cliente,
            'factura' => $factura->fresh(['cliente', 'empresa', 'detalles']),
        ];
    }

    private function fakeRespuestasBoletaFlujoCompleto(string $trackId = '1014'): void
    {
        Http::clearResolvedInstance('http');

        Http::fake([
            '*apicert.sii.cl/recursos/v1/boleta.electronica.semilla*' => Http::response(
                '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/XMLSchema"><SII:RESP_HDR><ESTADO>00</ESTADO></SII:RESP_HDR><SII:RESP_BODY><SEMILLA>123456789</SEMILLA></SII:RESP_BODY></SII:RESPUESTA>',
                200
            ),
            '*apicert.sii.cl/recursos/v1/boleta.electronica.token*' => Http::response(
                '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/XMLSchema"><SII:RESP_HDR><ESTADO>00</ESTADO></SII:RESP_HDR><SII:RESP_BODY><TOKEN>TOK_BOLETA_DEMO</TOKEN></SII:RESP_BODY></SII:RESPUESTA>',
                200
            ),
            '*pangal.sii.cl/recursos/v1/boleta.electronica.envio*' => Http::response(json_encode([
                'rut_emisor' => '76666555-2',
                'rut_envia' => '76666555-2',
                'trackid' => (int) $trackId,
                'fecha_recepcion' => now()->toDateTimeString(),
                'estado' => 'REC',
                'file' => 'boleta.xml',
            ]), 200),
        ]);
    }

    private function listener(): ProcesarFacturaParaSiiListener
    {
        return app(ProcesarFacturaParaSiiListener::class);
    }

    public function test_listener_procesa_factura_completa_map_emit_send(): void
    {
        $e = $this->escenarioFacturaEmisible();
        $this->fakeRespuestasSiiFlujoCompleto('aceptado', trackId: 'TRK_LIS');

        $event = new FacturaListaParaEmitirEvent($e['factura'], [], 'manual', 1);
        $this->listener()->handle($event);

        $factura = $e['factura']->fresh();
        $this->assertNotNull($factura->sii_dte_emitido_id);
        $dte = SiiDteEmitido::findOrFail($factura->sii_dte_emitido_id);
        $this->assertSame(SiiDteEmitido::ESTADO_ENVIADO_SII, $dte->estado);
        $this->assertSame('TRK_LIS', $dte->track_id);
    }

    public function test_listener_idempotente_skip_si_factura_ya_tiene_dte_asociado(): void
    {
        $e = $this->escenarioFacturaEmisible();
        // DTE ya ENVIADO al SII: el listener debe hacer skip (no reprocesar).
        // (Un DTE en BORRADOR/FIRMADO ahora SE REANUDA en vez de saltarse.)
        $dteExistente = SiiDteEmitido::factory()->create([
            'empresa_id' => $e['empresa']->id,
            'estado' => SiiDteEmitido::ESTADO_ENVIADO_SII,
        ]);
        $e['factura']->update(['sii_dte_emitido_id' => $dteExistente->id]);

        $handler = new TestHandler;
        Log::channel('sii')->getLogger()->pushHandler($handler);

        $event = new FacturaListaParaEmitirEvent($e['factura']->fresh(), [], 'manual', 1);
        $this->listener()->handle($event);

        // No se creo DTE nuevo
        $this->assertSame($dteExistente->id, $e['factura']->fresh()->sii_dte_emitido_id);
        // Log de skip
        $skipLog = collect($handler->getRecords())->first(fn ($r) => str_contains((string) $r['message'], 'Listener skip'));
        $this->assertNotNull($skipLog);
        $this->assertSame($dteExistente->id, $skipLog['context']['dte_id']);
    }

    public function test_listener_falla_en_mapeo_marca_job_failed(): void
    {
        $e = $this->escenarioFacturaEmisible();
        // Hacemos invalida: tipo_dte null → mapper lanzara FacturaIncompletaParaSii.
        $e['factura']->update(['tipo_dte' => null]);

        $event = new FacturaListaParaEmitirEvent($e['factura']->fresh(), [], 'manual', 1);

        $this->expectException(FacturaIncompletaParaSii::class);
        $this->listener()->handle($event);
    }

    public function test_listener_falla_en_envio_dt_e_queda_en_firmado(): void
    {
        $e = $this->escenarioFacturaEmisible();

        // Construimos el fake completo en UNA sola llamada (Http::fake mergea
        // matchers entre llamadas sucesivas y retiene los primeros responses
        // por URL — bug de Laravel ya identificado en F5.4).
        $envSeed = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body><getSeedResponse>'
                  .'<getSeedReturn><![CDATA[<SII:RESPUESTA xmlns:SII="http://www.sii.cl/XMLSchema"><SII:RESP_HDR><ESTADO>00</ESTADO></SII:RESP_HDR><SII:RESP_BODY><SEMILLA>S</SEMILLA></SII:RESP_BODY></SII:RESPUESTA>]]></getSeedReturn>'
                  .'</getSeedResponse></soapenv:Body></soapenv:Envelope>';
        $envToken = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body><getTokenResponse>'
                  .'<getTokenReturn><![CDATA[<SII:RESPUESTA xmlns:SII="http://www.sii.cl/XMLSchema"><SII:RESP_HDR><ESTADO>00</ESTADO></SII:RESP_HDR><SII:RESP_BODY><TOKEN>T</TOKEN></SII:RESP_BODY></SII:RESPUESTA>]]></getTokenReturn>'
                  .'</getTokenResponse></soapenv:Body></soapenv:Envelope>';

        Http::fake([
            '*/DTEWS/CrSeed*' => Http::response($envSeed, 200),
            '*/DTEWS/GetTokenFromSeed*' => Http::response($envToken, 200),
            '*/cgi_dte/UPL/DTEUpload*' => Http::response('ServerError', 500),
        ]);

        $event = new FacturaListaParaEmitirEvent($e['factura'], [], 'manual', 1);
        // En F5.2 el envio retorna SiiEnvioDte con estado ERROR_TRANSPORTE sin
        // lanzar excepcion. El listener NO falla en este caso; el DTE queda
        // en FIRMADO (porque emitir ya commiteo).
        $this->listener()->handle($event);

        $factura = $e['factura']->fresh();
        $dte = SiiDteEmitido::findOrFail($factura->sii_dte_emitido_id);
        $this->assertSame(SiiDteEmitido::ESTADO_FIRMADO, $dte->estado,
            'Si envio falla en transporte, F5.2 marca envio ERROR_TRANSPORTE y deja DTE FIRMADO.');
    }

    public function test_listener_origen_y_usuario_id_loggeados(): void
    {
        $e = $this->escenarioFacturaEmisible();
        $this->fakeRespuestasSiiFlujoCompleto('aceptado');

        $handler = new TestHandler;
        Log::channel('sii')->getLogger()->pushHandler($handler);

        $event = new FacturaListaParaEmitirEvent($e['factura'], [], 'reintento', 999);
        $this->listener()->handle($event);

        $logMapeo = collect($handler->getRecords())
            ->first(fn ($r) => str_contains((string) $r['message'], 'mapeada'));
        $this->assertNotNull($logMapeo);
        $this->assertSame('reintento', $logMapeo['context']['origen']);
        $this->assertSame(999, $logMapeo['context']['usuario_id']);
    }

    public function test_listener_propiedades_queue_tries_timeout_backoff(): void
    {
        $listener = $this->listener();

        $this->assertSame('sii', $listener->queue);
        $this->assertSame(3, $listener->tries);
        $this->assertSame(120, $listener->timeout);
        $this->assertTrue($listener->failOnTimeout);
        $this->assertSame([60, 300, 900], $listener->backoff());
    }

    public function test_listener_failed_hook_loguea_critical(): void
    {
        $handler = new TestHandler;
        Log::channel('sii')->getLogger()->pushHandler($handler);

        $e = $this->escenarioFacturaEmisible();
        $event = new FacturaListaParaEmitirEvent($e['factura'], [], 'manual', 1);

        $this->listener()->failed($event, new \RuntimeException('boom final'));

        $criticoLog = collect($handler->getRecords())
            ->first(fn ($r) => str_contains((string) $r['message'], 'despues de todos los reintentos'));
        $this->assertNotNull($criticoLog);
        $this->assertSame('boom final', $criticoLog['context']['message']);
    }

    public function test_aislamiento_multitenant_factura_de_otra_empresa_se_procesa_a_su_empresa(): void
    {
        $a = $this->escenarioFacturaEmisible('76111111-1');
        $b = $this->escenarioFacturaEmisible('77222222-2');

        $this->fakeRespuestasSiiFlujoCompleto('aceptado');

        $this->listener()->handle(new FacturaListaParaEmitirEvent($a['factura'], [], 'manual', 1));
        $this->listener()->handle(new FacturaListaParaEmitirEvent($b['factura'], [], 'manual', 1));

        $dteA = SiiDteEmitido::findOrFail($a['factura']->fresh()->sii_dte_emitido_id);
        $dteB = SiiDteEmitido::findOrFail($b['factura']->fresh()->sii_dte_emitido_id);

        $this->assertSame($a['empresa']->id, (int) $dteA->empresa_id);
        $this->assertSame($b['empresa']->id, (int) $dteB->empresa_id);
        $this->assertNotSame($dteA->id, $dteB->id);
    }

    public function test_listener_boleta_se_envia_via_envio_boleta_service_no_envio_sii_service(): void
    {
        $e = $this->escenarioBoletaEmisible();
        $this->fakeRespuestasBoletaFlujoCompleto('1014');

        $event = new FacturaListaParaEmitirEvent($e['factura'], [], 'manual', 1);
        $this->listener()->handle($event);

        $factura = $e['factura']->fresh();
        $this->assertNotNull($factura->sii_dte_emitido_id);
        $dte = SiiDteEmitido::findOrFail($factura->sii_dte_emitido_id);
        $this->assertSame(39, $dte->tipo_dte);
        $this->assertSame(SiiDteEmitido::ESTADO_ENVIADO_SII, $dte->estado);
        $this->assertSame('1014', $dte->track_id);

        Http::assertSent(function (Request $r) {
            return str_contains($r->url(), 'pangal.sii.cl');
        });
        Http::assertNotSent(function (Request $r) {
            return str_contains($r->url(), 'cgi_dte/UPL/DTEUpload');
        });
    }

    public function test_listener_factura_normal_sigue_usando_envio_sii_service_no_boleta(): void
    {
        $e = $this->escenarioFacturaEmisible();
        $this->fakeRespuestasSiiFlujoCompleto('aceptado', trackId: 'TRK_FACT');

        $event = new FacturaListaParaEmitirEvent($e['factura'], [], 'manual', 1);
        $this->listener()->handle($event);

        $factura = $e['factura']->fresh();
        $dte = SiiDteEmitido::findOrFail($factura->sii_dte_emitido_id);
        $this->assertSame(33, $dte->tipo_dte);
        $this->assertSame(SiiDteEmitido::ESTADO_ENVIADO_SII, $dte->estado);

        Http::assertNotSent(function (Request $r) {
            return str_contains($r->url(), 'boleta.electronica.envio');
        });
    }

    public function test_listener_implementa_should_queue(): void
    {
        $this->assertInstanceOf(
            ShouldQueue::class,
            $this->listener()
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
