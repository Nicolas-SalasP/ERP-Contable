<?php

namespace Tests\Feature\Sii\Integracion;

use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\FacturaDetalle;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoEvento;
use App\Domains\Sii\Models\SiiEnvioDte;
use App\Domains\Sii\Services\Integracion\EmitirDteDesdeFacturaService;
use App\Domains\Sii\Services\Polling\PollearEstadoSiiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\OrquestaFlujoCompletoEnTests;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * F6.2 — E2E del flujo completo desde Factura del Comercial:
 *   service->dispatch → evento → listener (queue sync) → map → emit → send.
 *
 * Polling final invocado manualmente (sin esperar el job programado de F5.3)
 * para validar transicion terminal en el mismo test.
 */
class EmisionDesdeFacturaE2ETest extends TestCase
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

        // En tests forzamos queue sincrona para que el listener corra inline.
        config([
            'queue.default'              => 'sync',
            'sii.upload.timeout_seconds' => 5,
            'sii.upload.retries'         => 2,
            'sii.upload.retry_delay_ms'  => 1,
        ]);

        Storage::fake(config('sii.storage.disk', 'local'));
    }

    /**
     * Crea empresa+cert+caf+cliente+factura emisible.
     */
    private function escenario(string $rut = '76555444-3'): array
    {
        $ctx = $this->setupEmpresaConFlujoCompleto(['rut' => $rut]);
        $ctx['dte']->delete(); // borramos el DTE auxiliar creado por el setup

        $cliente = Cliente::create([
            'rut' => '11222333-4', 'razon_social' => 'CLI E2E',
            'direccion' => 'X', 'comuna' => 'Stgo', 'ciudad' => 'Stgo',
            'giro' => 'Comercio', 'contacto_email' => 'e2e@cli.cl',
            'empresa_id' => $ctx['empresa']->id, 'estado' => 'ACTIVO',
        ]);
        $factura = Factura::create([
            'empresa_id' => $ctx['empresa']->id,
            'codigo_unico' => Factura::generarCodigoUnico(),
            'cliente_id' => $cliente->id,
            'numero_factura' => 'F-E2E-' . random_int(1000, 99999),
            'tipo' => 'VENTA', 'tipo_documento' => 'FACTURA', 'tipo_dte' => 33,
            'fecha_emision' => now()->toDateString(),
            'monto_neto' => 1000, 'monto_iva' => 190, 'monto_bruto' => 1190,
            'estado' => 'REGISTRADA',
        ]);
        FacturaDetalle::create([
            'factura_id' => $factura->id, 'numero_linea' => 1,
            'nombre_item' => 'Servicio E2E', 'cantidad' => 1,
            'precio_unitario' => 1000, 'monto_item' => 1000, 'exento' => false,
        ]);

        return ['empresa' => $ctx['empresa'], 'cliente' => $cliente, 'factura' => $factura];
    }

    private function emitirYpollear(Factura $factura, string $escenario): SiiEnvioDte
    {
        $this->fakeRespuestasSiiFlujoCompleto($escenario);

        app(EmitirDteDesdeFacturaService::class)->dispatch($factura, [], 'manual', 1);

        // El listener corrio inline (queue=sync). Polling final manual.
        $envio = SiiEnvioDte::where('dte_emitido_id', $factura->fresh()->sii_dte_emitido_id)->latest('id')->first();
        return app(PollearEstadoSiiService::class)->pollear($envio->fresh());
    }

    public function test_escenario_aceptado_factura_termina_con_DTE_ACEPTADO(): void
    {
        $e = $this->escenario();

        $envio = $this->emitirYpollear($e['factura'], 'aceptado');

        $this->assertSame(SiiEnvioDte::ESTADO_ACEPTADO, $envio->estado_envio);
        $dte = $e['factura']->fresh()->dteEmitido;
        $this->assertSame(SiiDteEmitido::ESTADO_ACEPTADO, $dte->estado);
    }

    public function test_escenario_rechazado_factura_queda_con_DTE_RECHAZADO(): void
    {
        $e = $this->escenario('76777777-7');

        $envio = $this->emitirYpollear($e['factura'], 'rechazado');

        $this->assertSame(SiiEnvioDte::ESTADO_RECHAZADO, $envio->estado_envio);
        $this->assertSame(SiiDteEmitido::ESTADO_RECHAZADO, $e['factura']->fresh()->dteEmitido->estado);
    }

    public function test_escenario_procesando_factura_queda_con_DTE_ENVIADO_SII(): void
    {
        $e = $this->escenario('76888888-8');

        $envio = $this->emitirYpollear($e['factura'], 'procesando');

        $this->assertSame(SiiEnvioDte::ESTADO_ENVIADO, $envio->estado_envio);
        $this->assertSame(SiiDteEmitido::ESTADO_ENVIADO_SII, $e['factura']->fresh()->dteEmitido->estado);
    }

    public function test_aislamiento_multitenant_2_facturas_de_empresas_distintas_no_interfieren(): void
    {
        $a = $this->escenario('76111111-1');
        $b = $this->escenario('77222222-2');

        $this->fakeRespuestasSiiFlujoCompleto('aceptado');

        app(EmitirDteDesdeFacturaService::class)->dispatch($a['factura'], [], 'manual', 1);
        app(EmitirDteDesdeFacturaService::class)->dispatch($b['factura'], [], 'manual', 2);

        $facturaA = $a['factura']->fresh();
        $facturaB = $b['factura']->fresh();

        $this->assertNotNull($facturaA->sii_dte_emitido_id);
        $this->assertNotNull($facturaB->sii_dte_emitido_id);
        $this->assertNotSame($facturaA->sii_dte_emitido_id, $facturaB->sii_dte_emitido_id);

        $dteA = SiiDteEmitido::findOrFail($facturaA->sii_dte_emitido_id);
        $dteB = SiiDteEmitido::findOrFail($facturaB->sii_dte_emitido_id);
        $this->assertSame($a['empresa']->id, (int) $dteA->empresa_id);
        $this->assertSame($b['empresa']->id, (int) $dteB->empresa_id);
    }

    public function test_audit_log_contiene_3_eventos_post_aceptado(): void
    {
        $e = $this->escenario('76999999-9');

        $envio = $this->emitirYpollear($e['factura'], 'aceptado');

        $dte = $envio->dteEmitido;
        $eventos = SiiDteEmitidoEvento::where('dte_emitido_id', $dte->id)->orderBy('id')->get();
        $this->assertCount(3, $eventos);
        $this->assertSame(SiiDteEmitido::ESTADO_BORRADOR,    $eventos[0]->estado_anterior);
        $this->assertSame(SiiDteEmitido::ESTADO_FIRMADO,     $eventos[0]->estado_nuevo);
        $this->assertSame(SiiDteEmitido::ESTADO_FIRMADO,     $eventos[1]->estado_anterior);
        $this->assertSame(SiiDteEmitido::ESTADO_ENVIADO_SII, $eventos[1]->estado_nuevo);
        $this->assertSame(SiiDteEmitido::ESTADO_ENVIADO_SII, $eventos[2]->estado_anterior);
        $this->assertSame(SiiDteEmitido::ESTADO_ACEPTADO,    $eventos[2]->estado_nuevo);
    }
}
