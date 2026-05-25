<?php

namespace Tests\Feature\Sii\Integracion;

use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\FacturaDetalle;
use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Events\FacturaListaParaEmitirEvent;
use App\Domains\Sii\Exceptions\ReintentoNoAplicableException;
use App\Domains\Sii\Jobs\ReintentarEmisionDteJob;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiEnvioDte;
use App\Domains\Sii\Services\Integracion\ReintentarEmisionFacturaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class ReintentarEmisionFacturaServiceTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    private ReintentarEmisionFacturaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->service = app(ReintentarEmisionFacturaService::class);
    }

    private function escenario(?string $estadoDte = null, ?string $estadoUltimoEnvio = null): array
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();

        $cliente = Cliente::create([
            'rut' => '11222333-4', 'razon_social' => 'CLI',
            'empresa_id' => $empresa->id, 'estado' => 'ACTIVO',
        ]);

        $factura = Factura::create([
            'empresa_id'     => $empresa->id,
            'codigo_unico'   => Factura::generarCodigoUnico(),
            'cliente_id'     => $cliente->id,
            'numero_factura' => 'F-' . random_int(1000, 99999),
            'tipo'           => 'VENTA', 'tipo_documento' => 'FACTURA', 'tipo_dte' => 33,
            'fecha_emision'  => now()->toDateString(),
            'monto_neto'     => 1000, 'monto_iva' => 190, 'monto_bruto' => 1190,
            'estado'         => 'REGISTRADA',
        ]);
        FacturaDetalle::create([
            'factura_id' => $factura->id, 'numero_linea' => 1,
            'nombre_item' => 'X', 'cantidad' => 1, 'precio_unitario' => 1000,
            'monto_item' => 1000, 'exento' => false,
        ]);

        $dte = null;
        if ($estadoDte !== null) {
            $dte = SiiDteEmitido::factory()->create([
                'empresa_id' => $empresa->id,
                'estado'     => $estadoDte,
                'tipo_dte'   => 33,
                'factura_id' => $factura->id,
            ]);
            $factura->update(['sii_dte_emitido_id' => $dte->id]);

            if ($estadoUltimoEnvio !== null) {
                SiiEnvioDte::create([
                    'empresa_id'     => $empresa->id,
                    'dte_emitido_id' => $dte->id,
                    'ambiente_sii'   => $empresa->ambiente_sii ?? 'certificacion',
                    'estado_envio'   => $estadoUltimoEnvio,
                    'intentos_envio' => 1,
                    'intentos_polling' => 0,
                    'fecha_envio'    => now(),
                ]);
            }
        }

        return compact('empresa', 'usuario', 'cliente', 'factura', 'dte');
    }

    public function test_factura_sin_dte_dispara_redispatch_evento(): void
    {
        Event::fake([FacturaListaParaEmitirEvent::class]);

        $e = $this->escenario(estadoDte: null);

        $accion = $this->service->reintentar($e['factura'], 'red intermitente', $e['usuario']->id);

        $this->assertSame('redispatch_evento', $accion);
        Event::assertDispatched(FacturaListaParaEmitirEvent::class, function ($ev) use ($e) {
            return $ev->factura->id === $e['factura']->id
                && $ev->origen === 'reintento'
                && $ev->usuarioId === $e['usuario']->id;
        });
    }

    public function test_dte_BORRADOR_encola_reanudar_firma(): void
    {
        Bus::fake([ReintentarEmisionDteJob::class]);

        $e = $this->escenario(estadoDte: SiiDteEmitido::ESTADO_BORRADOR);

        $accion = $this->service->reintentar($e['factura'], null, $e['usuario']->id);

        $this->assertSame('reanudar_firma', $accion);
        Bus::assertDispatched(ReintentarEmisionDteJob::class, function (ReintentarEmisionDteJob $j) use ($e) {
            return $j->dteEmitidoId === $e['dte']->id
                && $j->accion === ReintentarEmisionDteJob::ACCION_REANUDAR_FIRMA
                && $j->usuarioId === $e['usuario']->id;
        });
    }

    public function test_dte_FIRMADO_encola_reanudar_envio(): void
    {
        Bus::fake([ReintentarEmisionDteJob::class]);

        $e = $this->escenario(estadoDte: SiiDteEmitido::ESTADO_FIRMADO);

        $accion = $this->service->reintentar($e['factura']);

        $this->assertSame('reanudar_envio', $accion);
        Bus::assertDispatched(ReintentarEmisionDteJob::class, fn ($j) =>
            $j->accion === ReintentarEmisionDteJob::ACCION_REANUDAR_ENVIO
        );
    }

    public function test_dte_ENVIADO_SII_con_ultimo_envio_ERROR_TRANSPORTE_encola_reanudar_envio(): void
    {
        Bus::fake([ReintentarEmisionDteJob::class]);

        $e = $this->escenario(
            estadoDte: SiiDteEmitido::ESTADO_ENVIADO_SII,
            estadoUltimoEnvio: SiiEnvioDte::ESTADO_ERROR_TRANSPORTE
        );

        $accion = $this->service->reintentar($e['factura']);

        $this->assertSame('reanudar_envio', $accion);
        Bus::assertDispatched(ReintentarEmisionDteJob::class);
    }

    public function test_dte_ENVIADO_SII_con_ultimo_envio_ERROR_TIMEOUT_encola_reanudar_envio(): void
    {
        Bus::fake([ReintentarEmisionDteJob::class]);

        $e = $this->escenario(
            estadoDte: SiiDteEmitido::ESTADO_ENVIADO_SII,
            estadoUltimoEnvio: SiiEnvioDte::ESTADO_ERROR_TIMEOUT
        );

        $this->assertSame('reanudar_envio', $this->service->reintentar($e['factura']));
        Bus::assertDispatched(ReintentarEmisionDteJob::class);
    }

    public function test_dte_ENVIADO_SII_con_ultimo_envio_ERROR_PERMANENTE_tambien_encola(): void
    {
        // Decision tecnica F6.4: ERROR_PERMANENTE incluido como reintentable
        // bajo accion deliberada del operador.
        Bus::fake([ReintentarEmisionDteJob::class]);

        $e = $this->escenario(
            estadoDte: SiiDteEmitido::ESTADO_ENVIADO_SII,
            estadoUltimoEnvio: SiiEnvioDte::ESTADO_ERROR_PERMANENTE
        );

        $this->assertSame('reanudar_envio', $this->service->reintentar($e['factura']));
        Bus::assertDispatched(ReintentarEmisionDteJob::class);
    }

    public function test_dte_ENVIADO_SII_con_ultimo_envio_ENVIADO_lanza_yaEnProceso(): void
    {
        Bus::fake([ReintentarEmisionDteJob::class]);

        $e = $this->escenario(
            estadoDte: SiiDteEmitido::ESTADO_ENVIADO_SII,
            estadoUltimoEnvio: SiiEnvioDte::ESTADO_ENVIADO
        );

        try {
            $this->service->reintentar($e['factura']);
            $this->fail('Se esperaba ReintentoNoAplicableException');
        } catch (ReintentoNoAplicableException $ex) {
            $this->assertSame(ReintentoNoAplicableException::RAZON_YA_EN_PROCESO, $ex->razon);
            $this->assertSame(SiiEnvioDte::ESTADO_ENVIADO, $ex->estadoActual);
        }
        Bus::assertNotDispatched(ReintentarEmisionDteJob::class);
    }

    public function test_dte_ENVIADO_SII_sin_envios_encola_reanudar_envio_defensivo(): void
    {
        Bus::fake([ReintentarEmisionDteJob::class]);

        // Estado inconsistente fabricado a proposito (DTE en ENVIADO_SII sin envio).
        $e = $this->escenario(estadoDte: SiiDteEmitido::ESTADO_ENVIADO_SII);

        $this->assertSame('reanudar_envio', $this->service->reintentar($e['factura']));
        Bus::assertDispatched(ReintentarEmisionDteJob::class);
    }

    public function test_dte_ACEPTADO_lanza_estadoTerminal(): void
    {
        Bus::fake([ReintentarEmisionDteJob::class]);
        $e = $this->escenario(estadoDte: SiiDteEmitido::ESTADO_ACEPTADO);

        try {
            $this->service->reintentar($e['factura']);
            $this->fail('Esperada ReintentoNoAplicableException');
        } catch (ReintentoNoAplicableException $ex) {
            $this->assertSame(ReintentoNoAplicableException::RAZON_ESTADO_TERMINAL, $ex->razon);
            $this->assertSame('ACEPTADO', $ex->estadoActual);
        }
        Bus::assertNotDispatched(ReintentarEmisionDteJob::class);
    }

    public function test_dte_RECHAZADO_lanza_estadoTerminal(): void
    {
        $e = $this->escenario(estadoDte: SiiDteEmitido::ESTADO_RECHAZADO);
        $this->expectException(ReintentoNoAplicableException::class);
        $this->service->reintentar($e['factura']);
    }

    public function test_dte_ACEPTADO_CON_REPAROS_lanza_estadoTerminal(): void
    {
        $e = $this->escenario(estadoDte: SiiDteEmitido::ESTADO_ACEPTADO_CON_REPAROS);
        $this->expectException(ReintentoNoAplicableException::class);
        $this->service->reintentar($e['factura']);
    }

    public function test_dte_XML_GENERADO_lanza_dteNoReintentable(): void
    {
        // Estado intermedio no contemplado en happy path de reintento.
        $e = $this->escenario(estadoDte: SiiDteEmitido::ESTADO_XML_GENERADO);

        try {
            $this->service->reintentar($e['factura']);
            $this->fail('Esperada ReintentoNoAplicableException');
        } catch (ReintentoNoAplicableException $ex) {
            $this->assertSame(ReintentoNoAplicableException::RAZON_DTE_NO_REINTENTABLE, $ex->razon);
        }
    }

    public function test_reintento_propaga_razon_y_usuario_al_job(): void
    {
        Bus::fake([ReintentarEmisionDteJob::class]);

        $e = $this->escenario(estadoDte: SiiDteEmitido::ESTADO_BORRADOR);

        $this->service->reintentar($e['factura'], 'Cliente reporto error', $e['usuario']->id);

        Bus::assertDispatched(ReintentarEmisionDteJob::class, function (ReintentarEmisionDteJob $j) use ($e) {
            return $j->razon === 'Cliente reporto error'
                && $j->usuarioId === $e['usuario']->id
                && $j->dteEmitidoId === $e['dte']->id;
        });
    }
}
