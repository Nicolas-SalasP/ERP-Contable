<?php

namespace Tests\Feature\Integraciones;

use App\Domains\Comercial\Models\Factura;
use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Core\Models\Empresa;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\InventarioDevolucionOrden;
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

    /**
     * Empresa lista + venta confirmada con el neto de linea del producto forzado exactamente vía
     * `monto_neto_linea` (para poder verificar redondeo sin depender de si precio_venta_neto x
     * cantidad divide limpio). Devuelve [token, producto, empresa, factura_id].
     *
     * @return array{0: string, 1: Producto, 2: Empresa, 3: int}
     */
    private function prepararEmpresaConVentaConMontoNetoExacto(
        float $cantidadVendida,
        float $montoNetoLinea,
        float $stock = 10,
    ): array {
        Event::fake([FacturaListaParaEmitirEvent::class]);

        $empresa = $this->crearEmpresa();
        $this->crearPlanCuentasVenta($empresa);
        // precio_venta_neto sirve de tope (debe ser >= monto_neto_linea / cantidad).
        $producto = $this->crearProducto($empresa, [
            'sku' => 'RMA-'.strtoupper(substr(uniqid(), -6)),
            'precio_venta_neto' => $montoNetoLinea,
        ]);
        $bodega = $this->crearBodega($empresa);
        $this->crearStock($empresa, $producto, $bodega, $stock);
        $token = $this->habilitarModuloYEmitirKey($empresa, ['ventas:escribir', 'devoluciones:escribir']);

        $reservaId = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/integraciones/v2/reservas', ['sku' => $producto->sku, 'cantidad' => $cantidadVendida])
            ->json('data.reserva_id');

        $respuestaVenta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/integraciones/v2/ventas', [
                'reserva_id' => $reservaId,
                'cliente' => ['rut' => '11222333-4', 'nombre' => 'Cliente Web'],
                'items' => [['sku' => $producto->sku, 'cantidad' => $cantidadVendida, 'monto_neto_linea' => $montoNetoLinea]],
            ]);

        $respuestaVenta->assertCreated();
        $facturaId = $respuestaVenta->json('data.factura_id');

        return [$token, $producto->fresh(), $empresa, $facturaId];
    }

    public function test_devolucion_parcial_de_linea_no_divisible_no_arrastra_deriva_de_redondeo(): void
    {
        // Linea de $25.210 neto vendida en 3 unidades: precio unitario real es periodico
        // (8403.3333...). Se devuelve 1 unidad; el monto de la NC debe ser exactamente el mismo
        // que calcular directamente 25210/3 redondeado una sola vez, sin deriva de centavos.
        [$token, $producto, , $facturaId] = $this->prepararEmpresaConVentaConMontoNetoExacto(
            cantidadVendida: 3,
            montoNetoLinea: 25210,
        );

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/integraciones/v2/devoluciones', [
                'factura_id' => $facturaId,
                'items' => [['sku' => $producto->sku, 'cantidad' => 1]],
            ]);

        $respuesta->assertCreated();
        $nc = Factura::findOrFail($respuesta->json('data.nota_credito_id'));

        $montoEsperado = round(25210 / 3, 2);
        $this->assertEqualsWithDelta($montoEsperado, (float) $nc->monto_neto, 0.0001);
    }

    /**
     * Misma venta que prepararEmpresaConVentaConfirmada, pero con linea de despacho. Devuelve
     * [token, producto, empresa, factura_id].
     *
     * @return array{0: string, 1: Producto, 2: Empresa, 3: int}
     */
    private function prepararEmpresaConVentaConDespacho(
        float $cantidadVendida,
        float $montoNetoLineaProducto,
        float $montoNetoDespacho,
        float $stock = 10,
    ): array {
        Event::fake([FacturaListaParaEmitirEvent::class]);

        $empresa = $this->crearEmpresa();
        $this->crearPlanCuentasVenta($empresa);
        $producto = $this->crearProducto($empresa, [
            'sku' => 'RMA-'.strtoupper(substr(uniqid(), -6)),
            'precio_venta_neto' => $montoNetoLineaProducto,
        ]);
        $bodega = $this->crearBodega($empresa);
        $this->crearStock($empresa, $producto, $bodega, $stock);
        $token = $this->habilitarModuloYEmitirKey($empresa, ['ventas:escribir', 'devoluciones:escribir']);

        $reservaId = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/integraciones/v2/reservas', ['sku' => $producto->sku, 'cantidad' => $cantidadVendida])
            ->json('data.reserva_id');

        $respuestaVenta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/integraciones/v2/ventas', [
                'reserva_id' => $reservaId,
                'cliente' => ['rut' => '11222333-4', 'nombre' => 'Cliente Web'],
                'items' => [['sku' => $producto->sku, 'cantidad' => $cantidadVendida, 'monto_neto_linea' => $montoNetoLineaProducto]],
                'despacho' => ['monto_neto' => $montoNetoDespacho],
            ]);

        $respuestaVenta->assertCreated();
        $facturaId = $respuestaVenta->json('data.factura_id');

        return [$token, $producto->fresh(), $empresa, $facturaId];
    }

    public function test_retracto_completo_incluye_el_despacho_en_la_nota_de_credito(): void
    {
        [$token, $producto, , $facturaId] = $this->prepararEmpresaConVentaConDespacho(
            cantidadVendida: 3,
            montoNetoLineaProducto: 3000,
            montoNetoDespacho: 2000,
        );

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/integraciones/v2/devoluciones', [
                'factura_id' => $facturaId,
                'tipo' => 'retracto',
                'items' => [['sku' => $producto->sku, 'cantidad' => 3]],
            ]);

        $respuesta->assertCreated();
        $nc = Factura::findOrFail($respuesta->json('data.nota_credito_id'));

        // Neto = 3000 (todo el producto) + 2000 (despacho, porque es retracto TOTAL).
        $this->assertEqualsWithDelta(5000.0, (float) $nc->monto_neto, 0.01);
    }

    public function test_retracto_parcial_no_incluye_el_despacho_en_la_nota_de_credito(): void
    {
        [$token, $producto, , $facturaId] = $this->prepararEmpresaConVentaConDespacho(
            cantidadVendida: 3,
            montoNetoLineaProducto: 3000,
            montoNetoDespacho: 2000,
        );

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/integraciones/v2/devoluciones', [
                'factura_id' => $facturaId,
                'tipo' => 'retracto',
                'items' => [['sku' => $producto->sku, 'cantidad' => 1]],
            ]);

        $respuesta->assertCreated();
        $nc = Factura::findOrFail($respuesta->json('data.nota_credito_id'));

        // Neto = 1000 (1 de 3 unidades) SIN despacho: retracto parcial, politica conservadora.
        $this->assertEqualsWithDelta(1000.0, (float) $nc->monto_neto, 0.01);
    }

    public function test_incluir_despacho_true_con_devolucion_total_incluye_el_despacho(): void
    {
        [$token, $producto, , $facturaId] = $this->prepararEmpresaConVentaConDespacho(
            cantidadVendida: 3,
            montoNetoLineaProducto: 3000,
            montoNetoDespacho: 2000,
        );

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/integraciones/v2/devoluciones', [
                'factura_id' => $facturaId,
                'tipo' => 'retracto',
                'incluir_despacho' => true,
                'items' => [['sku' => $producto->sku, 'cantidad' => 3]],
            ]);

        $respuesta->assertCreated();
        $nc = Factura::findOrFail($respuesta->json('data.nota_credito_id'));

        // Neto = 3000 (todo el producto) + 2000 (despacho): incluir_despacho=true coincide con
        // que la factura efectivamente se devuelve entera.
        $this->assertEqualsWithDelta(5000.0, (float) $nc->monto_neto, 0.01);
    }

    public function test_incluir_despacho_false_con_devolucion_total_no_incluye_el_despacho(): void
    {
        [$token, $producto, , $facturaId] = $this->prepararEmpresaConVentaConDespacho(
            cantidadVendida: 3,
            montoNetoLineaProducto: 3000,
            montoNetoDespacho: 2000,
        );

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/integraciones/v2/devoluciones', [
                'factura_id' => $facturaId,
                'tipo' => 'retracto',
                'incluir_despacho' => false,
                'items' => [['sku' => $producto->sku, 'cantidad' => 3]],
            ]);

        $respuesta->assertCreated();
        $nc = Factura::findOrFail($respuesta->json('data.nota_credito_id'));

        // Neto = 3000 (solo el producto): la web decidio explicitamente que el despacho no
        // corresponde (ej. porque hay otras facturas del mismo pedido sin devolver), aunque
        // esta factura se devuelva entera.
        $this->assertEqualsWithDelta(3000.0, (float) $nc->monto_neto, 0.01);
    }

    public function test_incluir_despacho_true_con_devolucion_parcial_no_incluye_el_despacho(): void
    {
        [$token, $producto, , $facturaId] = $this->prepararEmpresaConVentaConDespacho(
            cantidadVendida: 3,
            montoNetoLineaProducto: 3000,
            montoNetoDespacho: 2000,
        );

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/integraciones/v2/devoluciones', [
                'factura_id' => $facturaId,
                'tipo' => 'retracto',
                'incluir_despacho' => true,
                'items' => [['sku' => $producto->sku, 'cantidad' => 1]],
            ]);

        $respuesta->assertCreated();
        $nc = Factura::findOrFail($respuesta->json('data.nota_credito_id'));

        // Neto = 1000 (1 de 3 unidades) SIN despacho: la red de seguridad ignora
        // incluir_despacho=true porque la factura no se devuelve entera.
        $this->assertEqualsWithDelta(1000.0, (float) $nc->monto_neto, 0.01);
    }

    public function test_garantia_nunca_incluye_el_despacho_aunque_la_devolucion_sea_completa(): void
    {
        [$token, $producto, , $facturaId] = $this->prepararEmpresaConVentaConDespacho(
            cantidadVendida: 3,
            montoNetoLineaProducto: 3000,
            montoNetoDespacho: 2000,
        );

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/integraciones/v2/devoluciones', [
                'factura_id' => $facturaId,
                'tipo' => 'garantia',
                'items' => [['sku' => $producto->sku, 'cantidad' => 3]],
            ]);

        $respuesta->assertCreated();
        $nc = Factura::findOrFail($respuesta->json('data.nota_credito_id'));

        // Neto = 3000 (todo el producto), sin despacho: es garantia, nunca lo incluye.
        $this->assertEqualsWithDelta(3000.0, (float) $nc->monto_neto, 0.01);
    }

    public function test_devolucion_sin_tipo_informado_no_incluye_el_despacho(): void
    {
        [$token, $producto, , $facturaId] = $this->prepararEmpresaConVentaConDespacho(
            cantidadVendida: 3,
            montoNetoLineaProducto: 3000,
            montoNetoDespacho: 2000,
        );

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/integraciones/v2/devoluciones', [
                'factura_id' => $facturaId,
                'items' => [['sku' => $producto->sku, 'cantidad' => 3]],
            ]);

        $respuesta->assertCreated();
        $nc = Factura::findOrFail($respuesta->json('data.nota_credito_id'));

        $this->assertEqualsWithDelta(3000.0, (float) $nc->monto_neto, 0.01);
    }

    public function test_devolucion_con_tipo_invalido_es_rechazada(): void
    {
        [$token, $producto, , $facturaId] = $this->prepararEmpresaConVentaConfirmada(
            stock: 10,
            cantidadVendida: 1,
        );

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/integraciones/v2/devoluciones', [
                'factura_id' => $facturaId,
                'tipo' => 'cualquier-cosa',
                'items' => [['sku' => $producto->sku, 'cantidad' => 1]],
            ]);

        $respuesta->assertStatus(422);
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

    /**
     * solo_despacho exige que ESTA factura ya tenga una devolucion de productos confirmada. Se
     * registra el mismo tipo de fila que crea InventarioDevolucionService::crearDesdeOrigenExterno
     * en el flujo normal (origen_modulo/origen_id/estado CONFIRMADA) directo por Eloquent, para no
     * arrastrar el efecto colateral de que esa devolucion de productos tambien emita su propia
     * Nota de Credito (regla ya existente y no relacionada: una factura solo admite una NC activa
     * a la vez) y así poder aislar la validacion nueva bajo prueba.
     */
    private function confirmarDevolucionDeProductosConfirmada(Empresa $empresa, int $facturaId): void
    {
        InventarioDevolucionOrden::create([
            'empresa_id' => $empresa->id,
            'despacho_orden_id' => null,
            'bodega_id' => $this->crearBodega($empresa)->id,
            'codigo' => 'RMA-TEST-'.strtoupper(substr(uniqid(), -8)),
            'tipo' => InventarioDevolucionOrden::TIPO_DEVOLUCION,
            'estado' => InventarioDevolucionOrden::ESTADO_CONFIRMADA,
            'motivo' => 'devolucion_integraciones',
            'origen_modulo' => 'integraciones_devolucion',
            'origen_id' => $facturaId,
            'fecha_creacion' => now(),
            'fecha_confirmacion' => now(),
        ]);
    }

    public function test_solo_despacho_true_contra_factura_con_despacho_genera_nc_solo_por_el_despacho_sin_tocar_stock(): void
    {
        [$token, $producto, $empresa, $facturaId] = $this->prepararEmpresaConVentaConDespacho(
            cantidadVendida: 3,
            montoNetoLineaProducto: 3000,
            montoNetoDespacho: 2000,
        );

        $this->confirmarDevolucionDeProductosConfirmada($empresa, $facturaId);

        $stockAntes = StockProducto::where('empresa_id', $empresa->id)->where('producto_id', $producto->id)->first();

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/integraciones/v2/devoluciones', [
                'factura_id' => $facturaId,
                'tipo' => 'retracto',
                'solo_despacho' => true,
            ]);

        $respuesta->assertCreated();
        $this->assertNull($respuesta->json('data.devolucion_id'));
        $notaCreditoId = $respuesta->json('data.nota_credito_id');
        $this->assertNotNull($notaCreditoId);

        $nc = Factura::findOrFail($notaCreditoId);
        // Neto = 2000 (solo el despacho): no toca el monto del producto (ya devuelto aparte).
        $this->assertEqualsWithDelta(2000.0, (float) $nc->monto_neto, 0.01);

        $stockDespues = StockProducto::where('empresa_id', $empresa->id)->where('producto_id', $producto->id)->first();
        $this->assertEquals((float) $stockAntes->stock_actual, (float) $stockDespues->stock_actual);
    }

    public function test_solo_despacho_true_contra_factura_sin_despacho_no_crea_nc_y_responde_error_claro(): void
    {
        [$token, $producto, , $facturaId] = $this->prepararEmpresaConVentaConfirmada(
            stock: 10,
            cantidadVendida: 2,
        );

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/integraciones/v2/devoluciones', [
                'factura_id' => $facturaId,
                'tipo' => 'retracto',
                'solo_despacho' => true,
            ]);

        $respuesta->assertStatus(422);
        $this->assertNull(
            Factura::where('factura_referencia_id', $facturaId)->where('tipo_documento', 'NOTA_CREDITO')->first()
        );
    }

    public function test_solo_despacho_true_contra_factura_intacta_sin_devolucion_de_productos_es_rechazado(): void
    {
        // Misma factura con despacho que el caso feliz, pero SIN devolver nunca los productos:
        // solo_despacho no puede generar una NC de despacho sobre una factura que en realidad
        // nunca se retracto.
        [$token, , , $facturaId] = $this->prepararEmpresaConVentaConDespacho(
            cantidadVendida: 3,
            montoNetoLineaProducto: 3000,
            montoNetoDespacho: 2000,
        );

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/integraciones/v2/devoluciones', [
                'factura_id' => $facturaId,
                'tipo' => 'retracto',
                'solo_despacho' => true,
            ]);

        $respuesta->assertStatus(422);
        $this->assertNull(
            Factura::where('factura_referencia_id', $facturaId)->where('tipo_documento', 'NOTA_CREDITO')->first()
        );
    }

    public function test_solo_despacho_es_idempotente_con_el_mismo_header(): void
    {
        [$token, , $empresa, $facturaId] = $this->prepararEmpresaConVentaConDespacho(
            cantidadVendida: 3,
            montoNetoLineaProducto: 3000,
            montoNetoDespacho: 2000,
        );

        $this->confirmarDevolucionDeProductosConfirmada($empresa, $facturaId);

        $payload = [
            'factura_id' => $facturaId,
            'tipo' => 'retracto',
            'solo_despacho' => true,
        ];

        $primera = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Idempotency-Key' => 'reintento-solo-despacho-1',
        ])->postJson('/api/integraciones/v2/devoluciones', $payload);
        $primera->assertCreated();

        $segunda = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Idempotency-Key' => 'reintento-solo-despacho-1',
        ])->postJson('/api/integraciones/v2/devoluciones', $payload);
        $segunda->assertCreated();

        $this->assertEquals($primera->json('data.nota_credito_id'), $segunda->json('data.nota_credito_id'));

        $this->assertEquals(
            1,
            Factura::where('factura_referencia_id', $facturaId)->where('tipo_documento', 'NOTA_CREDITO')->count()
        );
    }

    public function test_solo_despacho_dos_veces_sin_mismo_idempotency_key_es_rechazado_y_no_duplica_la_nc(): void
    {
        [$token, , $empresa, $facturaId] = $this->prepararEmpresaConVentaConDespacho(
            cantidadVendida: 3,
            montoNetoLineaProducto: 3000,
            montoNetoDespacho: 2000,
        );

        $this->confirmarDevolucionDeProductosConfirmada($empresa, $facturaId);

        $payload = [
            'factura_id' => $facturaId,
            'tipo' => 'retracto',
            'solo_despacho' => true,
        ];

        // Idempotency-Key distinta en cada request (simula dos intentos "genuinamente
        // distintos" del canal externo, no un reintento del mismo request): la proteccion
        // real contra duplicar la NC no depende de la clave de idempotencia sino de la regla
        // de FacturaService::emitirNotaCreditoVenta de que la factura solo admite una NC activa.
        $primera = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Idempotency-Key' => 'intento-solo-despacho-A',
        ])->postJson('/api/integraciones/v2/devoluciones', $payload);
        $primera->assertCreated();

        $segunda = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Idempotency-Key' => 'intento-solo-despacho-B',
        ])->postJson('/api/integraciones/v2/devoluciones', $payload);
        $segunda->assertStatus(422);

        $this->assertEquals(
            1,
            Factura::where('factura_referencia_id', $facturaId)->where('tipo_documento', 'NOTA_CREDITO')->count()
        );
    }

    public function test_incluir_despacho_pedido_de_una_sola_linea_sigue_funcionando_sin_solo_despacho(): void
    {
        [$token, $producto, , $facturaId] = $this->prepararEmpresaConVentaConDespacho(
            cantidadVendida: 2,
            montoNetoLineaProducto: 4000,
            montoNetoDespacho: 1500,
        );

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/integraciones/v2/devoluciones', [
                'factura_id' => $facturaId,
                'tipo' => 'retracto',
                'items' => [['sku' => $producto->sku, 'cantidad' => 2]],
            ]);

        $respuesta->assertCreated();
        $nc = Factura::findOrFail($respuesta->json('data.nota_credito_id'));

        // Neto = 4000 (producto) + 1500 (despacho): pedido de 1 sola linea, mecanismo historico.
        $this->assertEqualsWithDelta(5500.0, (float) $nc->monto_neto, 0.01);
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
