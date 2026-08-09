<?php

namespace Tests\Feature\Integraciones;

use App\Domains\Comercial\Models\Factura;
use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Core\Models\Empresa;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\ReservaInventario;
use App\Domains\Inventario\Models\StockProducto;
use App\Domains\Inventario\Models\UnidadMedida;
use App\Domains\Integraciones\Services\IntegracionApiKeyService;
use App\Domains\Sii\Events\FacturaListaParaEmitirEvent;
use App\Domains\Sii\Exceptions\FacturaNoEmisibleException;
use App\Domains\Sii\Services\Integracion\EmitirDteDesdeFacturaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * POST /ventas (Fase 2): confirmar checkout externo -> consume la reserva (movimiento real de
 * inventario), crea Factura+FacturaDetalle+asiento contable, y encola el DTE. Cubre boleta/
 * factura segun rut, idempotencia por Idempotency-Key, fallo controlado del DTE, y aislamiento
 * multitenant.
 */
class VentaIntegracionApiTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    private function habilitarModuloYEmitirKey(Empresa $empresa, array $scopes): string
    {
        $this->crearUsuario($empresa, $this->rolAdministrador, [
            'module_keys' => ['integraciones.api'],
        ]);

        $emitida = app(IntegracionApiKeyService::class)->emitir($empresa->id, 'Test', $scopes);

        return $emitida['token'];
    }

    private function crearPlanCuentasVenta(Empresa $empresa): void
    {
        foreach ([
            ['152005', 'Clientes', 'ACTIVO'],
            ['501105', 'Ingresos por Venta', 'INGRESO'],
            ['353360', 'IVA Débito Fiscal', 'PASIVO'],
            ['601205', 'Costo Ventas Nacional', 'GASTO'],
            ['151005', 'Inventario Materiales', 'ACTIVO'],
        ] as [$codigo, $nombre, $tipo]) {
            PlanCuenta::create([
                'empresa_id' => $empresa->id,
                'codigo' => $codigo,
                'nombre' => $nombre,
                'tipo' => $tipo,
                'imputable' => true,
                'activo' => true,
            ]);
        }
    }

    private function crearProducto(Empresa $empresa, array $overrides = []): Producto
    {
        $unidad = UnidadMedida::firstOrCreate(
            ['codigo' => 'UN'],
            ['nombre' => 'Unidad', 'permite_decimal' => false, 'activo' => true]
        );

        return Producto::create(array_merge([
            'empresa_id' => $empresa->id,
            'sku' => 'SKU-'.strtoupper(substr(uniqid(), -8)),
            'nombre' => 'Producto Test',
            'descripcion' => 'Descripcion de prueba',
            'tipo_producto' => 'BIEN',
            'unidad_medida_id' => $unidad->id,
            'metodo_valorizacion' => 'PMP',
            'costo_promedio' => 100,
            'precio_venta_neto' => 1000,
            'afecto_iva' => true,
            'codigo_barra' => '780'.random_int(1000000000, 9999999999),
            'stock_minimo' => 0,
            'permite_merma' => true,
            'activo' => true,
            'visible_web' => true,
        ], $overrides));
    }

    private function crearBodega(Empresa $empresa): Bodega
    {
        return Bodega::create([
            'empresa_id' => $empresa->id,
            'codigo' => 'BOD-'.strtoupper(substr(uniqid(), -6)),
            'nombre' => 'Bodega Test',
            'estado' => 'ACTIVA',
        ]);
    }

    private function crearStock(Empresa $empresa, Producto $producto, Bodega $bodega, float $cantidad): StockProducto
    {
        return StockProducto::create([
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'stock_actual' => $cantidad,
            'costo_promedio' => 100,
            'valor_total' => $cantidad * 100,
        ]);
    }

    /** @return array{0: string, 1: Producto, 2: Empresa} */
    private function prepararEmpresaConReserva(float $stock = 10, float $cantidadReservada = 3): array
    {
        $empresa = $this->crearEmpresa();
        $this->crearPlanCuentasVenta($empresa);
        $producto = $this->crearProducto($empresa, ['sku' => 'VTA-'.strtoupper(substr(uniqid(), -6))]);
        $bodega = $this->crearBodega($empresa);
        $this->crearStock($empresa, $producto, $bodega, $stock);
        $token = $this->habilitarModuloYEmitirKey($empresa, ['ventas:escribir']);

        $reservaId = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/integraciones/v2/reservas', ['sku' => $producto->sku, 'cantidad' => $cantidadReservada])
            ->json('data.reserva_id');

        return [$token, $producto, $empresa, $reservaId];
    }

    public function test_confirmar_venta_con_rut_crea_factura_tipo_33(): void
    {
        Event::fake([FacturaListaParaEmitirEvent::class]);

        [$token, $producto, $empresa, $reservaId] = $this->prepararEmpresaConReserva();

        $respuesta = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Idempotency-Key' => 'pedido-web-100',
        ])->postJson('/api/integraciones/v2/ventas', [
            'reserva_id' => $reservaId,
            'cliente' => ['rut' => '11222333-4', 'nombre' => 'Cliente Web', 'email' => 'cliente@example.com'],
            'items' => [['sku' => $producto->sku, 'cantidad' => 3]],
        ]);

        $respuesta->assertCreated();
        $facturaId = $respuesta->json('data.factura_id');
        $this->assertNotNull($facturaId);
        $this->assertEquals('pendiente', $respuesta->json('data.dte_estado'));

        $factura = Factura::findOrFail($facturaId);
        $this->assertEquals(33, $factura->tipo_dte);
        $this->assertEquals($empresa->id, $factura->empresa_id);
        $this->assertEquals(3000.0, (float) $factura->monto_neto);
        $this->assertEqualsWithDelta(3000 * (float) config('fiscal.tasa_iva'), (float) $factura->monto_iva, 0.5);

        $reserva = ReservaInventario::findOrFail($reservaId);
        $this->assertEquals(ReservaInventario::ESTADO_CONSUMIDA, $reserva->estado);

        Event::assertDispatched(FacturaListaParaEmitirEvent::class, fn ($e) => $e->factura->id === $facturaId);
    }

    public function test_confirmar_venta_sin_rut_crea_boleta_tipo_39(): void
    {
        Event::fake([FacturaListaParaEmitirEvent::class]);

        [$token, $producto, , $reservaId] = $this->prepararEmpresaConReserva();

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/integraciones/v2/ventas', [
                'reserva_id' => $reservaId,
                'items' => [['sku' => $producto->sku, 'cantidad' => 3]],
            ]);

        $respuesta->assertCreated();
        $factura = Factura::findOrFail($respuesta->json('data.factura_id'));
        $this->assertEquals(39, $factura->tipo_dte);
        $this->assertEquals('BOLETA', $factura->tipo_documento);
    }

    public function test_confirmar_venta_es_idempotente_con_el_mismo_header(): void
    {
        Event::fake([FacturaListaParaEmitirEvent::class]);

        [$token, $producto, , $reservaId] = $this->prepararEmpresaConReserva();

        $payload = [
            'reserva_id' => $reservaId,
            'cliente' => ['rut' => '11222333-4', 'nombre' => 'Cliente Web'],
            'items' => [['sku' => $producto->sku, 'cantidad' => 3]],
        ];

        $primera = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Idempotency-Key' => 'reintento-1',
        ])->postJson('/api/integraciones/v2/ventas', $payload);
        $primera->assertCreated();

        $segunda = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Idempotency-Key' => 'reintento-1',
        ])->postJson('/api/integraciones/v2/ventas', $payload);
        $segunda->assertCreated();

        $this->assertEquals($primera->json('data.factura_id'), $segunda->json('data.factura_id'));
        $this->assertSame(1, Factura::where('empresa_id', Factura::find($primera->json('data.factura_id'))->empresa_id)->count());
    }

    public function test_confirmar_venta_con_fallo_al_encolar_dte_no_revierte_la_venta(): void
    {
        $this->mock(EmitirDteDesdeFacturaService::class, function ($mock) {
            $mock->shouldReceive('dispatch')
                ->once()
                ->andThrow(FacturaNoEmisibleException::sinDetalles(0));
        });

        [$token, $producto, , $reservaId] = $this->prepararEmpresaConReserva();

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/integraciones/v2/ventas', [
                'reserva_id' => $reservaId,
                'cliente' => ['rut' => '11222333-4', 'nombre' => 'Cliente Web'],
                'items' => [['sku' => $producto->sku, 'cantidad' => 3]],
            ]);

        $respuesta->assertCreated();
        $this->assertEquals('error_encolando', $respuesta->json('data.dte_estado'));

        $factura = Factura::findOrFail($respuesta->json('data.factura_id'));
        $this->assertEquals('REGISTRADA', $factura->estado);
        $this->assertNull($factura->sii_dte_emitido_id);
    }

    public function test_confirmar_venta_de_reserva_de_otra_empresa_devuelve_404(): void
    {
        [, , , $reservaIdEmpresaA] = $this->prepararEmpresaConReserva();

        $empresaB = $this->crearEmpresa();
        $this->crearPlanCuentasVenta($empresaB);
        $tokenB = $this->habilitarModuloYEmitirKey($empresaB, ['ventas:escribir']);

        $this->withHeaders(['Authorization' => 'Bearer '.$tokenB])
            ->postJson('/api/integraciones/v2/ventas', ['reserva_id' => $reservaIdEmpresaA])
            ->assertNotFound();
    }

    public function test_confirmar_venta_sin_scope_ventas_escribir_es_rechazado(): void
    {
        $empresa = $this->crearEmpresa();
        $token = $this->habilitarModuloYEmitirKey($empresa, ['inventario:leer']);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/integraciones/v2/ventas', ['reserva_id' => 1])
            ->assertForbidden();
    }
}
