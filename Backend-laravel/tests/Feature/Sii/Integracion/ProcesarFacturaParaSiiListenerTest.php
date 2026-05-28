<?php

namespace Tests\Feature\Sii\Integracion;

use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\FacturaDetalle;
use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Events\FacturaListaParaEmitirEvent;
use App\Domains\Sii\Listeners\ProcesarFacturaParaSiiListener;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Services\Emision\EmitirDteService;
use App\Domains\Sii\Services\Envio\EnvioSiiService;
use App\Domains\Sii\Services\Mapping\FacturaAComercialDteMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Monolog\Handler\TestHandler;
use Tests\Concerns\OrquestaFlujoCompletoEnTests;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class ProcesarFacturaParaSiiListenerTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;
    use OrquestaFlujoCompletoEnTests;

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
            'rut'             => '11222333-4',
            'razon_social'    => 'CLIENTE LISTENER',
            'contacto_email'  => 'listener@cli.cl',
            'direccion'       => 'X',
            'comuna'          => 'Stgo', 'ciudad' => 'Stgo',
            'giro'            => 'Comercio',
            'estado'          => 'ACTIVO',
            'empresa_id'      => $ctx['empresa']->id,
        ]);

        $factura = Factura::create([
            'empresa_id'     => $ctx['empresa']->id,
            'codigo_unico'   => Factura::generarCodigoUnico(),
            'cliente_id'     => $cliente->id,
            'numero_factura' => 'F-LIS-' . random_int(1000, 99999),
            'tipo'           => 'VENTA',
            'tipo_documento' => 'FACTURA',
            'tipo_dte'       => 33,
            'fecha_emision'  => now()->toDateString(),
            'monto_neto'     => 1000, 'monto_iva' => 190, 'monto_bruto' => 1190,
            'estado'         => 'REGISTRADA',
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
        $dteExistente = SiiDteEmitido::factory()->create(['empresa_id' => $e['empresa']->id]);
        $e['factura']->update(['sii_dte_emitido_id' => $dteExistente->id]);

        $handler = new TestHandler();
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

        $this->expectException(\App\Domains\Sii\Exceptions\FacturaIncompletaParaSii::class);
        $this->listener()->handle($event);
    }

    public function test_listener_falla_en_envio_DTE_queda_en_FIRMADO(): void
    {
        $e = $this->escenarioFacturaEmisible();

        // Construimos el fake completo en UNA sola llamada (Http::fake mergea
        // matchers entre llamadas sucesivas y retiene los primeros responses
        // por URL — bug de Laravel ya identificado en F5.4).
        $envSeed  = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body><getSeedResponse>'
                  . '<getSeedReturn><![CDATA[<SII:RESPUESTA xmlns:SII="http://www.sii.cl/XMLSchema"><SII:RESP_HDR><ESTADO>00</ESTADO></SII:RESP_HDR><SII:RESP_BODY><SEMILLA>S</SEMILLA></SII:RESP_BODY></SII:RESPUESTA>]]></getSeedReturn>'
                  . '</getSeedResponse></soapenv:Body></soapenv:Envelope>';
        $envToken = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body><getTokenResponse>'
                  . '<getTokenReturn><![CDATA[<SII:RESPUESTA xmlns:SII="http://www.sii.cl/XMLSchema"><SII:RESP_HDR><ESTADO>00</ESTADO></SII:RESP_HDR><SII:RESP_BODY><TOKEN>T</TOKEN></SII:RESP_BODY></SII:RESPUESTA>]]></getTokenReturn>'
                  . '</getTokenResponse></soapenv:Body></soapenv:Envelope>';

        \Illuminate\Support\Facades\Http::fake([
            '*/DTEWS/CrSeed*'           => \Illuminate\Support\Facades\Http::response($envSeed, 200),
            '*/DTEWS/GetTokenFromSeed*' => \Illuminate\Support\Facades\Http::response($envToken, 200),
            '*/cgi_dte/UPL/DTEUpload*'  => \Illuminate\Support\Facades\Http::response('ServerError', 500),
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

        $handler = new TestHandler();
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
        $handler = new TestHandler();
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

    public function test_listener_implementa_ShouldQueue(): void
    {
        $this->assertInstanceOf(
            \Illuminate\Contracts\Queue\ShouldQueue::class,
            $this->listener()
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
