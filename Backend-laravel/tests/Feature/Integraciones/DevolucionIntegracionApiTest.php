<?php

namespace Tests\Feature\Integraciones;

use App\Domains\Comercial\Models\Factura;
use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Core\Models\Empresa;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\ProductoSerie;
use App\Domains\Inventario\Models\StockProducto;
use App\Domains\Inventario\Models\UnidadMedida;
use App\Domains\Integraciones\Services\IntegracionApiKeyService;
use App\Domains\Sii\Events\FacturaListaParaEmitirEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * POST /devoluciones (Fase 4, RMA): reingresa stock real sobre una factura de venta creada via
 * POST /ventas (Fase 2), marca la serie devuelta cuando corresponde, y emite Nota de Credito
 * (tipo 61) contra la factura original via FacturaService::emitirNotaCreditoVenta. Cubre
 * reingreso de stock, serie opcional, cantidad excedida, idempotencia, scope faltante y
 * aislamiento multitenant.
 */
class DevolucionIntegracionApiTest extends TestCase
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

    /**
     * Empresa lista + una venta ya confirmada (Fase 2) de $cantidadVendida unidades, opcionalmente
     * con numero_serie. Devuelve [token, producto, empresa, factura_id].
     *
     * @return array{0: string, 1: Producto, 2: Empresa, 3: int}
     */
    private function prepararEmpresaConVentaConfirmada(
        float $stock = 10,
        float $cantidadVendida = 3,
        ?string $numeroSerie = null,
        array $overridesProducto = []
    ): array {
        Event::fake([FacturaListaParaEmitirEvent::class]);

        $empresa = $this->crearEmpresa();
        $this->crearPlanCuentasVenta($empresa);
        $producto = $this->crearProducto($empresa, array_merge(['sku' => 'RMA-'.strtoupper(substr(uniqid(), -6))], $overridesProducto));
        $bodega = $this->crearBodega($empresa);
        $this->crearStock($empresa, $producto, $bodega, $stock);
        $token = $this->habilitarModuloYEmitirKey($empresa, ['ventas:escribir', 'devoluciones:escribir']);

        $reservaId = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/integraciones/v2/reservas', ['sku' => $producto->sku, 'cantidad' => $cantidadVendida])
            ->json('data.reserva_id');

        $item = ['sku' => $producto->sku, 'cantidad' => $cantidadVendida];
        if ($numeroSerie !== null) {
            $item['numero_serie'] = $numeroSerie;
        }

        $respuestaVenta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/integraciones/v2/ventas', [
                'reserva_id' => $reservaId,
                'cliente' => ['rut' => '11222333-4', 'nombre' => 'Cliente Web'],
                'items' => [$item],
            ]);

        $respuestaVenta->assertCreated();
        $facturaId = $respuestaVenta->json('data.factura_id');

        return [$token, $producto->fresh(), $empresa, $facturaId];
    }

    public function test_devolucion_feliz_con_serie_reingresa_stock_marca_serie_y_genera_nc(): void
    {
        [$token, $producto, $empresa, $facturaId] = $this->prepararEmpresaConVentaConfirmada(
            stock: 10,
            cantidadVendida: 3,
            numeroSerie: 'SN-0001',
            overridesProducto: ['requiere_serie' => true],
        );

        $stockPostVenta = StockProducto::where('empresa_id', $empresa->id)->where('producto_id', $producto->id)->first();
        $this->assertEquals(7.0, (float) $stockPostVenta->stock_actual);

        $respuesta = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Idempotency-Key' => 'devolucion-1',
        ])->postJson('/api/integraciones/v2/devoluciones', [
            'factura_id' => $facturaId,
            'items' => [[
                'sku' => $producto->sku,
                'cantidad' => 1,
                'numero_serie' => 'SN-0001',
                'motivo' => 'producto defectuoso',
            ]],
        ]);

        $respuesta->assertCreated();
        $respuesta->assertJsonPath('data.estado', 'CONFIRMADA');
        $this->assertNotNull($respuesta->json('data.devolucion_id'));
        $notaCreditoId = $respuesta->json('data.nota_credito_id');
        $this->assertNotNull($notaCreditoId);

        $stockPostDevolucion = StockProducto::where('empresa_id', $empresa->id)->where('producto_id', $producto->id)->first();
        $this->assertEquals(8.0, (float) $stockPostDevolucion->stock_actual);

        $serie = ProductoSerie::where('empresa_id', $empresa->id)
            ->where('producto_id', $producto->id)
            ->where('numero_serie', 'SN-0001')
            ->first();
        $this->assertNotNull($serie);
        $this->assertEquals(ProductoSerie::ESTADO_DEVUELTO, $serie->estado);

        $nc = Factura::findOrFail($notaCreditoId);
        $this->assertEquals('NOTA_CREDITO', $nc->tipo_documento);
        $this->assertEquals(61, $nc->tipo_dte);
        $this->assertEquals($facturaId, $nc->factura_referencia_id);
        $this->assertEqualsWithDelta(1000.0, (float) $nc->monto_neto, 0.5);
    }

    public function test_devolucion_sin_serie_para_producto_que_no_la_requiere_funciona(): void
    {
        [$token, $producto, $empresa, $facturaId] = $this->prepararEmpresaConVentaConfirmada(
            stock: 10,
            cantidadVendida: 2,
        );

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/integraciones/v2/devoluciones', [
                'factura_id' => $facturaId,
                'items' => [['sku' => $producto->sku, 'cantidad' => 2, 'motivo' => 'no era lo esperado']],
            ]);

        $respuesta->assertCreated();
        $this->assertNotNull($respuesta->json('data.nota_credito_id'));

        $stock = StockProducto::where('empresa_id', $empresa->id)->where('producto_id', $producto->id)->first();
        $this->assertEquals(10.0, (float) $stock->stock_actual);
    }

    public function test_devolucion_de_mas_cantidad_que_la_vendida_es_rechazada(): void
    {
        [$token, $producto, , $facturaId] = $this->prepararEmpresaConVentaConfirmada(
            stock: 10,
            cantidadVendida: 2,
        );

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/integraciones/v2/devoluciones', [
                'factura_id' => $facturaId,
                'items' => [['sku' => $producto->sku, 'cantidad' => 5]],
            ]);

        $respuesta->assertStatus(422);
    }

    public function test_devolucion_requiere_serie_si_el_producto_lo_exige(): void
    {
        [$token, $producto, , $facturaId] = $this->prepararEmpresaConVentaConfirmada(
            stock: 10,
            cantidadVendida: 1,
            numeroSerie: 'SN-0099',
            overridesProducto: ['requiere_serie' => true],
        );

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/integraciones/v2/devoluciones', [
                'factura_id' => $facturaId,
                'items' => [['sku' => $producto->sku, 'cantidad' => 1]],
            ]);

        $respuesta->assertStatus(422);
    }

    public function test_devolucion_es_idempotente_con_el_mismo_header(): void
    {
        [$token, $producto, $empresa, $facturaId] = $this->prepararEmpresaConVentaConfirmada(
            stock: 10,
            cantidadVendida: 2,
        );

        $payload = [
            'factura_id' => $facturaId,
            'items' => [['sku' => $producto->sku, 'cantidad' => 1]],
        ];

        $primera = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Idempotency-Key' => 'reintento-devolucion-1',
        ])->postJson('/api/integraciones/v2/devoluciones', $payload);
        $primera->assertCreated();

        $segunda = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Idempotency-Key' => 'reintento-devolucion-1',
        ])->postJson('/api/integraciones/v2/devoluciones', $payload);
        $segunda->assertCreated();

        $this->assertEquals($primera->json('data.devolucion_id'), $segunda->json('data.devolucion_id'));

        $stock = StockProducto::where('empresa_id', $empresa->id)->where('producto_id', $producto->id)->first();
        $this->assertEquals(9.0, (float) $stock->stock_actual);
    }

    public function test_devolucion_de_factura_de_otra_empresa_es_rechazada(): void
    {
        [, , , $facturaIdEmpresaA] = $this->prepararEmpresaConVentaConfirmada();

        $empresaB = $this->crearEmpresa();
        $this->crearPlanCuentasVenta($empresaB);
        $tokenB = $this->habilitarModuloYEmitirKey($empresaB, ['devoluciones:escribir']);

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$tokenB])
            ->postJson('/api/integraciones/v2/devoluciones', [
                'factura_id' => $facturaIdEmpresaA,
                'items' => [['sku' => 'CUALQUIERA', 'cantidad' => 1]],
            ]);

        $respuesta->assertStatus(422);
    }

    public function test_devolucion_sin_scope_devoluciones_escribir_es_rechazada(): void
    {
        $empresa = $this->crearEmpresa();
        $token = $this->habilitarModuloYEmitirKey($empresa, ['inventario:leer']);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/integraciones/v2/devoluciones', ['factura_id' => 1, 'items' => []])
            ->assertForbidden();
    }

    public function test_venta_de_producto_que_requiere_serie_sin_informarla_no_bloquea_la_venta(): void
    {
        Event::fake([FacturaListaParaEmitirEvent::class]);

        $empresa = $this->crearEmpresa();
        $this->crearPlanCuentasVenta($empresa);
        $producto = $this->crearProducto($empresa, ['sku' => 'RMA-SERIE-'.strtoupper(substr(uniqid(), -6)), 'requiere_serie' => true]);
        $bodega = $this->crearBodega($empresa);
        $this->crearStock($empresa, $producto, $bodega, 5);
        $token = $this->habilitarModuloYEmitirKey($empresa, ['ventas:escribir']);

        $reservaId = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/integraciones/v2/reservas', ['sku' => $producto->sku, 'cantidad' => 1])
            ->json('data.reserva_id');

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/integraciones/v2/ventas', [
                'reserva_id' => $reservaId,
                'items' => [['sku' => $producto->sku, 'cantidad' => 1]],
            ]);

        $respuesta->assertCreated();
    }
}
