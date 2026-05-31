<?php

namespace Tests\Feature\Sii\Integracion;

use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\FacturaDetalle;
use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Events\FacturaListaParaEmitirEvent;
use App\Domains\Sii\Exceptions\FacturaNoEmisibleException;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Services\Integracion\EmitirDteDesdeFacturaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class EmitirDteDesdeFacturaServiceTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    private EmitirDteDesdeFacturaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->service = app(EmitirDteDesdeFacturaService::class);
    }

    private function empresa(): Empresa
    {
        return Empresa::create(['rut' => '76123456-7', 'razon_social' => 'EMP F6.2']);
    }

    private function cliente(int $empresaId): Cliente
    {
        return Cliente::create([
            'rut' => '11222333-4', 'razon_social' => 'CLI', 'empresa_id' => $empresaId, 'estado' => 'ACTIVO',
        ]);
    }

    private function facturaEmisible(Empresa $e, Cliente $c, array $overrides = []): Factura
    {
        $factura = Factura::create(array_merge([
            'empresa_id'     => $e->id,
            'codigo_unico'   => Factura::generarCodigoUnico(),
            'cliente_id'     => $c->id,
            'numero_factura' => 'F-' . random_int(1000, 99999),
            'tipo'           => 'VENTA',
            'tipo_documento' => 'FACTURA',
            'tipo_dte'       => 33,
            'fecha_emision'  => now()->toDateString(),
            'monto_neto'     => 1000, 'monto_iva' => 190, 'monto_bruto' => 1190,
            'estado'         => 'REGISTRADA',
        ], $overrides));
        FacturaDetalle::create([
            'factura_id' => $factura->id, 'numero_linea' => 1,
            'nombre_item' => 'X', 'cantidad' => 1, 'precio_unitario' => 1000, 'monto_item' => 1000, 'exento' => false,
        ]);
        return $factura->fresh();
    }

    public function test_dispatch_dispara_evento_FacturaListaParaEmitirEvent(): void
    {
        Event::fake([FacturaListaParaEmitirEvent::class]);

        $factura = $this->facturaEmisible($this->empresa(), $this->cliente($this->empresa()->id));

        $this->service->dispatch($factura);

        Event::assertDispatched(FacturaListaParaEmitirEvent::class, function (FacturaListaParaEmitirEvent $e) use ($factura) {
            return $e->factura->id === $factura->id
                && $e->origen === 'manual'
                && $e->usuarioId === null;
        });
    }

    public function test_dispatch_pasa_referencias_y_origen_al_evento(): void
    {
        Event::fake([FacturaListaParaEmitirEvent::class]);

        $empresa = $this->empresa();
        $cliente = $this->cliente($empresa->id);
        // Para tipo_dte=61 necesitamos coherencia tipo_documento + referencias.
        $factura = $this->facturaEmisible($empresa, $cliente, [
            'tipo_dte' => 61, 'tipo_documento' => 'NOTA_CREDITO',
        ]);
        $refs = [['tipo_doc' => 33, 'folio_ref' => '100', 'fecha_ref' => '2026-01-01']];

        $this->service->dispatch($factura, $refs, 'reintento', 42);

        Event::assertDispatched(FacturaListaParaEmitirEvent::class, function ($e) use ($refs) {
            return $e->referencias === $refs
                && $e->origen === 'reintento'
                && $e->usuarioId === 42;
        });
    }

    public function test_dispatch_falla_si_tipo_dte_null(): void
    {
        $factura = $this->facturaEmisible($this->empresa(), $this->cliente($this->empresa()->id), ['tipo_dte' => null]);

        try {
            $this->service->dispatch($factura);
            $this->fail('Debio lanzar FacturaNoEmisibleException');
        } catch (FacturaNoEmisibleException $e) {
            $this->assertSame(FacturaNoEmisibleException::RAZON_TIPO_DTE_FALTANTE, $e->razon);
            $this->assertSame($factura->id, $e->facturaId);
        }
    }

    public function test_dispatch_falla_si_cliente_id_null(): void
    {
        $empresa = $this->empresa();
        $factura = $this->facturaEmisible($empresa, $this->cliente($empresa->id), ['cliente_id' => null]);

        try {
            $this->service->dispatch($factura);
            $this->fail('Debio lanzar');
        } catch (FacturaNoEmisibleException $e) {
            $this->assertSame(FacturaNoEmisibleException::RAZON_CLIENTE_FALTANTE, $e->razon);
        }
    }

    public function test_dispatch_falla_si_estado_ANULADA(): void
    {
        $factura = $this->facturaEmisible($this->empresa(), $this->cliente($this->empresa()->id), ['estado' => 'ANULADA']);

        try {
            $this->service->dispatch($factura);
            $this->fail('Debio lanzar');
        } catch (FacturaNoEmisibleException $e) {
            $this->assertSame(FacturaNoEmisibleException::RAZON_ESTADO_ANULADA, $e->razon);
        }
    }

    public function test_dispatch_falla_si_ya_emitida(): void
    {
        $empresa = $this->empresa();
        $cliente = $this->cliente($empresa->id);
        $dteExistente = SiiDteEmitido::factory()->create(['empresa_id' => $empresa->id]);
        $factura = $this->facturaEmisible($empresa, $cliente, ['sii_dte_emitido_id' => $dteExistente->id]);

        try {
            $this->service->dispatch($factura);
            $this->fail('Debio lanzar');
        } catch (FacturaNoEmisibleException $e) {
            $this->assertSame(FacturaNoEmisibleException::RAZON_YA_EMITIDA, $e->razon);
            $this->assertSame($dteExistente->id, $e->contexto['dte_emitido_id']);
        }
    }

    public function test_dispatch_falla_si_sin_detalles(): void
    {
        $factura = $this->facturaEmisible($this->empresa(), $this->cliente($this->empresa()->id));
        $factura->detalles()->delete();

        try {
            $this->service->dispatch($factura->fresh());
            $this->fail('Debio lanzar');
        } catch (FacturaNoEmisibleException $e) {
            $this->assertSame(FacturaNoEmisibleException::RAZON_SIN_DETALLES, $e->razon);
        }
    }

    public function test_dispatch_after_commit_no_dispara_si_tx_revierte(): void
    {
        Event::fake([FacturaListaParaEmitirEvent::class]);

        $factura = $this->facturaEmisible($this->empresa(), $this->cliente($this->empresa()->id));

        DB::beginTransaction();
        $this->service->dispatch($factura);
        // Mientras la tx esta abierta, el evento NO debe haberse disparado todavia
        // (esta encolado en pendingDispatchAfterCommit).
        Event::assertNotDispatched(FacturaListaParaEmitirEvent::class);
        DB::rollBack();

        // Tras el rollback, tampoco debe dispararse (la tx no commiteo).
        Event::assertNotDispatched(FacturaListaParaEmitirEvent::class);
    }
}
