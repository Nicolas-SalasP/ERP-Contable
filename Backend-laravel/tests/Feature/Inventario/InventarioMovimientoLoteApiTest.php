<?php

namespace Tests\Feature\Inventario;

use App\Domains\Core\Models\Empresa;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\LoteInventario;
use App\Domains\Inventario\Models\MovimientoInventario;
use App\Domains\Inventario\Models\MovimientoLoteInventario;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\StockLoteInventario;
use App\Domains\Inventario\Models\StockProducto;
use App\Domains\Inventario\Models\UnidadMedida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaInventarioTest;
use Tests\TestCase;

class InventarioMovimientoLoteApiTest extends TestCase
{
    use RefreshDatabase;
    use PreparaInventarioTest;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararUsuariosInventarioDemo();
    }

    public function test_entrada_con_lote_nuevo_crea_lote_stock_lote_y_detalle_movimiento(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosMovimientoLotesCompleto());

        $producto = $this->crearProducto($empresa, [
            'maneja_lotes' => true,
            'requiere_fecha_vencimiento' => true,
        ]);

        $bodega = $this->crearBodega($empresa);

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodega->id,
            'cantidad' => 10,
            'costo_unitario' => 100,
            'lote' => [
                'codigo_lote' => 'LOT-ENTRADA-NUEVO',
                'fecha_fabricacion' => '2026-01-01',
                'fecha_vencimiento' => '2026-12-31',
                'observacion' => 'Lote creado desde movimiento',
            ],
            'referencia' => 'ENT-LOTE-NUEVO',
            'motivo' => 'compra',
            'observacion' => 'Entrada con lote nuevo',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tipo', MovimientoInventario::TIPO_ENTRADA);

        $lote = LoteInventario::query()
            ->where('empresa_id', $empresa->id)
            ->where('producto_id', $producto->id)
            ->where('codigo_lote', 'LOT-ENTRADA-NUEVO')
            ->firstOrFail();

        $stockConsolidado = $this->obtenerStockConsolidado($empresa, $producto, $bodega);
        $stockLote = $this->obtenerStockLote($empresa, $producto, $bodega, $lote);

        $this->assertEquals(10.0, (float) $stockConsolidado->stock_actual);
        $this->assertEquals(10.0, (float) $stockLote->stock_actual);

        $movimiento = MovimientoInventario::query()
            ->where('empresa_id', $empresa->id)
            ->where('referencia', 'ENT-LOTE-NUEVO')
            ->firstOrFail();

        $this->assertDatabaseHas('inventario_movimiento_lotes', [
            'empresa_id' => $empresa->id,
            'movimiento_inventario_id' => $movimiento->id,
            'producto_id' => $producto->id,
            'lote_id' => $lote->id,
            'bodega_destino_id' => $bodega->id,
        ]);

        $detalle = MovimientoLoteInventario::query()
            ->where('movimiento_inventario_id', $movimiento->id)
            ->firstOrFail();

        $this->assertEquals(10.0, (float) $detalle->cantidad);
        $this->assertEquals(0.0, (float) $detalle->stock_lote_destino_antes);
        $this->assertEquals(10.0, (float) $detalle->stock_lote_destino_despues);
    }

    public function test_entrada_con_lote_existente_incrementa_stock_del_lote(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosMovimientoLotesCompleto());

        $producto = $this->crearProducto($empresa, [
            'maneja_lotes' => true,
        ]);

        $bodega = $this->crearBodega($empresa);

        $lote = $this->crearLote($empresa, $producto, [
            'codigo_lote' => 'LOT-EXISTENTE',
        ]);

        Sanctum::actingAs($usuario);

        $this->registrarEntradaConLoteExistente(
            producto: $producto,
            bodega: $bodega,
            lote: $lote,
            cantidad: 6,
            referencia: 'ENT-LOTE-EXISTENTE-1'
        );

        $this->registrarEntradaConLoteExistente(
            producto: $producto,
            bodega: $bodega,
            lote: $lote,
            cantidad: 4,
            referencia: 'ENT-LOTE-EXISTENTE-2'
        );

        $stockConsolidado = $this->obtenerStockConsolidado($empresa, $producto, $bodega);
        $stockLote = $this->obtenerStockLote($empresa, $producto, $bodega, $lote);

        $this->assertEquals(10.0, (float) $stockConsolidado->stock_actual);
        $this->assertEquals(10.0, (float) $stockLote->stock_actual);

        $this->assertEquals(2, MovimientoLoteInventario::query()
            ->where('empresa_id', $empresa->id)
            ->where('producto_id', $producto->id)
            ->where('lote_id', $lote->id)
            ->where('bodega_destino_id', $bodega->id)
            ->count());
    }

    public function test_salida_desde_lote_descuenta_stock_lote_y_stock_consolidado(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosMovimientoLotesCompleto());

        $producto = $this->crearProducto($empresa, [
            'maneja_lotes' => true,
        ]);

        $bodega = $this->crearBodega($empresa);
        $lote = $this->crearLote($empresa, $producto);

        Sanctum::actingAs($usuario);

        $this->registrarEntradaConLoteExistente(
            producto: $producto,
            bodega: $bodega,
            lote: $lote,
            cantidad: 10,
            referencia: 'ENT-ANTES-SALIDA'
        );

        $response = $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_SALIDA,
            'producto_id' => $producto->id,
            'bodega_origen_id' => $bodega->id,
            'cantidad' => 3,
            'lote_id' => $lote->id,
            'referencia' => 'SAL-LOTE-001',
            'motivo' => 'consumo',
            'observacion' => 'Salida desde lote específico',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tipo', MovimientoInventario::TIPO_SALIDA);

        $stockConsolidado = $this->obtenerStockConsolidado($empresa, $producto, $bodega);
        $stockLote = $this->obtenerStockLote($empresa, $producto, $bodega, $lote);

        $this->assertEquals(7.0, (float) $stockConsolidado->stock_actual);
        $this->assertEquals(7.0, (float) $stockLote->stock_actual);

        $movimientoSalida = MovimientoInventario::query()
            ->where('empresa_id', $empresa->id)
            ->where('referencia', 'SAL-LOTE-001')
            ->firstOrFail();

        $detalle = MovimientoLoteInventario::query()
            ->where('movimiento_inventario_id', $movimientoSalida->id)
            ->firstOrFail();

        $this->assertEquals($bodega->id, (int) $detalle->bodega_origen_id);
        $this->assertNull($detalle->bodega_destino_id);
        $this->assertEquals(10.0, (float) $detalle->stock_lote_origen_antes);
        $this->assertEquals(7.0, (float) $detalle->stock_lote_origen_despues);
    }

    public function test_salida_falla_si_stock_del_lote_es_insuficiente_y_revierte_movimiento(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosMovimientoLotesCompleto());

        $producto = $this->crearProducto($empresa, [
            'maneja_lotes' => true,
        ]);

        $bodega = $this->crearBodega($empresa);

        $loteConStock = $this->crearLote($empresa, $producto, [
            'codigo_lote' => 'LOT-CON-STOCK',
        ]);

        $loteSinStock = $this->crearLote($empresa, $producto, [
            'codigo_lote' => 'LOT-SIN-STOCK',
        ]);

        Sanctum::actingAs($usuario);

        $this->registrarEntradaConLoteExistente(
            producto: $producto,
            bodega: $bodega,
            lote: $loteConStock,
            cantidad: 10,
            referencia: 'ENT-STOCK-LOTE-A'
        );

        $response = $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_SALIDA,
            'producto_id' => $producto->id,
            'bodega_origen_id' => $bodega->id,
            'cantidad' => 1,
            'lote_id' => $loteSinStock->id,
            'referencia' => 'SAL-STOCK-LOTE-INSUF',
            'motivo' => 'consumo',
            'observacion' => 'Debe fallar por stock insuficiente del lote',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $stockConsolidado = $this->obtenerStockConsolidado($empresa, $producto, $bodega);
        $stockLoteConStock = $this->obtenerStockLote($empresa, $producto, $bodega, $loteConStock);

        $this->assertEquals(10.0, (float) $stockConsolidado->stock_actual);
        $this->assertEquals(10.0, (float) $stockLoteConStock->stock_actual);

        $this->assertDatabaseMissing('inventario_movimientos', [
            'empresa_id' => $empresa->id,
            'referencia' => 'SAL-STOCK-LOTE-INSUF',
        ]);

        $this->assertDatabaseMissing('inventario_movimiento_lotes', [
            'empresa_id' => $empresa->id,
            'lote_id' => $loteSinStock->id,
        ]);
    }

    public function test_traspaso_con_lote_mueve_stock_entre_bodegas_y_conserva_lote(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosMovimientoLotesCompleto());

        $producto = $this->crearProducto($empresa, [
            'maneja_lotes' => true,
        ]);

        $bodegaOrigen = $this->crearBodega($empresa, [
            'codigo' => 'BOD-ORIGEN',
            'nombre' => 'Bodega Origen',
        ]);

        $bodegaDestino = $this->crearBodega($empresa, [
            'codigo' => 'BOD-DESTINO',
            'nombre' => 'Bodega Destino',
        ]);

        $lote = $this->crearLote($empresa, $producto, [
            'codigo_lote' => 'LOT-TRASPASO',
        ]);

        Sanctum::actingAs($usuario);

        $this->registrarEntradaConLoteExistente(
            producto: $producto,
            bodega: $bodegaOrigen,
            lote: $lote,
            cantidad: 10,
            referencia: 'ENT-ANTES-TRASPASO'
        );

        $response = $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_TRASPASO,
            'producto_id' => $producto->id,
            'bodega_origen_id' => $bodegaOrigen->id,
            'bodega_destino_id' => $bodegaDestino->id,
            'cantidad' => 4,
            'lote_id' => $lote->id,
            'referencia' => 'TR-LOTE-001',
            'motivo' => 'traspaso_bodega',
            'observacion' => 'Traspaso conservando lote',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tipo', MovimientoInventario::TIPO_TRASPASO);

        $stockConsolidadoOrigen = $this->obtenerStockConsolidado($empresa, $producto, $bodegaOrigen);
        $stockConsolidadoDestino = $this->obtenerStockConsolidado($empresa, $producto, $bodegaDestino);

        $stockLoteOrigen = $this->obtenerStockLote($empresa, $producto, $bodegaOrigen, $lote);
        $stockLoteDestino = $this->obtenerStockLote($empresa, $producto, $bodegaDestino, $lote);

        $this->assertEquals(6.0, (float) $stockConsolidadoOrigen->stock_actual);
        $this->assertEquals(4.0, (float) $stockConsolidadoDestino->stock_actual);

        $this->assertEquals(6.0, (float) $stockLoteOrigen->stock_actual);
        $this->assertEquals(4.0, (float) $stockLoteDestino->stock_actual);

        $movimientoTraspaso = MovimientoInventario::query()
            ->where('empresa_id', $empresa->id)
            ->where('referencia', 'TR-LOTE-001')
            ->firstOrFail();

        $detalle = MovimientoLoteInventario::query()
            ->where('movimiento_inventario_id', $movimientoTraspaso->id)
            ->firstOrFail();

        $this->assertEquals($lote->id, (int) $detalle->lote_id);
        $this->assertEquals($bodegaOrigen->id, (int) $detalle->bodega_origen_id);
        $this->assertEquals($bodegaDestino->id, (int) $detalle->bodega_destino_id);
        $this->assertEquals(10.0, (float) $detalle->stock_lote_origen_antes);
        $this->assertEquals(6.0, (float) $detalle->stock_lote_origen_despues);
        $this->assertEquals(0.0, (float) $detalle->stock_lote_destino_antes);
        $this->assertEquals(4.0, (float) $detalle->stock_lote_destino_despues);
    }

    public function test_producto_que_maneja_lotes_exige_lote_en_movimiento(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosMovimientoLotesCompleto());

        $producto = $this->crearProducto($empresa, [
            'maneja_lotes' => true,
        ]);

        $bodega = $this->crearBodega($empresa);

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodega->id,
            'cantidad' => 5,
            'costo_unitario' => 100,
            'referencia' => 'ENT-SIN-LOTE',
            'motivo' => 'compra',
            'observacion' => 'Debe fallar porque el producto maneja lotes',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('inventario_movimientos', [
            'empresa_id' => $empresa->id,
            'referencia' => 'ENT-SIN-LOTE',
        ]);
    }

    public function test_producto_que_no_maneja_lotes_rechaza_payload_lote(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosMovimientoLotesCompleto());

        $producto = $this->crearProducto($empresa, [
            'maneja_lotes' => false,
        ]);

        $bodega = $this->crearBodega($empresa);

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodega->id,
            'cantidad' => 5,
            'costo_unitario' => 100,
            'lote' => [
                'codigo_lote' => 'LOT-NO-PERMITIDO',
            ],
            'referencia' => 'ENT-LOTE-NO-PERMITIDO',
            'motivo' => 'compra',
            'observacion' => 'Debe fallar porque el producto no maneja lotes',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('inventario_movimientos', [
            'empresa_id' => $empresa->id,
            'referencia' => 'ENT-LOTE-NO-PERMITIDO',
        ]);

        $this->assertDatabaseMissing('inventario_lotes', [
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'codigo_lote' => 'LOT-NO-PERMITIDO',
        ]);
    }

    public function test_kardex_producto_puede_filtrar_por_lote(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosMovimientoLotesCompleto());

        $producto = $this->crearProducto($empresa, [
            'maneja_lotes' => true,
        ]);

        $bodega = $this->crearBodega($empresa);

        $loteA = $this->crearLote($empresa, $producto, [
            'codigo_lote' => 'LOT-KARDEX-A',
        ]);

        $loteB = $this->crearLote($empresa, $producto, [
            'codigo_lote' => 'LOT-KARDEX-B',
        ]);

        Sanctum::actingAs($usuario);

        $this->registrarEntradaConLoteExistente(
            producto: $producto,
            bodega: $bodega,
            lote: $loteA,
            cantidad: 5,
            referencia: 'ENT-KARDEX-LOTE-A'
        );

        $this->registrarEntradaConLoteExistente(
            producto: $producto,
            bodega: $bodega,
            lote: $loteB,
            cantidad: 7,
            referencia: 'ENT-KARDEX-LOTE-B'
        );

        $response = $this->getJson("/api/inventario/productos/{$producto->id}/kardex?lote_id={$loteA->id}");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $referencias = collect($response->json('data'))
            ->pluck('referencia')
            ->all();

        $this->assertContains('ENT-KARDEX-LOTE-A', $referencias);
        $this->assertNotContains('ENT-KARDEX-LOTE-B', $referencias);
    }

    public function test_stock_consolidado_coincide_con_suma_de_stock_lotes_por_bodega(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosMovimientoLotesCompleto());

        $producto = $this->crearProducto($empresa, [
            'maneja_lotes' => true,
        ]);

        $bodegaOrigen = $this->crearBodega($empresa, [
            'codigo' => 'BOD-INV-ORIGEN',
            'nombre' => 'Bodega Invariante Origen',
        ]);

        $bodegaDestino = $this->crearBodega($empresa, [
            'codigo' => 'BOD-INV-DESTINO',
            'nombre' => 'Bodega Invariante Destino',
        ]);

        $loteA = $this->crearLote($empresa, $producto, [
            'codigo_lote' => 'LOT-INV-A',
        ]);

        $loteB = $this->crearLote($empresa, $producto, [
            'codigo_lote' => 'LOT-INV-B',
        ]);

        Sanctum::actingAs($usuario);

        $this->registrarEntradaConLoteExistente(
            producto: $producto,
            bodega: $bodegaOrigen,
            lote: $loteA,
            cantidad: 10,
            referencia: 'ENT-INV-A'
        );

        $this->registrarEntradaConLoteExistente(
            producto: $producto,
            bodega: $bodegaOrigen,
            lote: $loteB,
            cantidad: 5,
            referencia: 'ENT-INV-B'
        );

        $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_SALIDA,
            'producto_id' => $producto->id,
            'bodega_origen_id' => $bodegaOrigen->id,
            'cantidad' => 2,
            'lote_id' => $loteB->id,
            'referencia' => 'SAL-INV-B',
            'motivo' => 'consumo',
            'observacion' => 'Salida para probar invariante',
        ])->assertCreated();

        $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_TRASPASO,
            'producto_id' => $producto->id,
            'bodega_origen_id' => $bodegaOrigen->id,
            'bodega_destino_id' => $bodegaDestino->id,
            'cantidad' => 4,
            'lote_id' => $loteA->id,
            'referencia' => 'TR-INV-A',
            'motivo' => 'traspaso_bodega',
            'observacion' => 'Traspaso para probar invariante',
        ])->assertCreated();

        $this->assertStockConsolidadoIgualASumaLotes($empresa, $producto, $bodegaOrigen);
        $this->assertStockConsolidadoIgualASumaLotes($empresa, $producto, $bodegaDestino);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers de permisos
    |--------------------------------------------------------------------------
    */

    private function permisosMovimientoLotesCompleto(): array
    {
        return [
            'inventario.productos.ver',
            'inventario.productos.crear',
            'inventario.productos.editar',

            'inventario.bodegas.ver',
            'inventario.bodegas.crear',

            'inventario.movimientos.ver',
            'inventario.movimientos.entrada',
            'inventario.movimientos.salida',
            'inventario.movimientos.traspaso',
            'inventario.movimientos.ajuste',

            'inventario.kardex.ver',

            'inventario.valorizacion.ver',

            'inventario.lotes.ver',
            'inventario.lotes.crear',
            'inventario.lotes.editar',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers de requests
    |--------------------------------------------------------------------------
    */

    private function registrarEntradaConLoteExistente(
        Producto $producto,
        Bodega $bodega,
        LoteInventario $lote,
        float $cantidad,
        string $referencia
    ): void {
        $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodega->id,
            'cantidad' => $cantidad,
            'costo_unitario' => 100,
            'lote_id' => $lote->id,
            'referencia' => $referencia,
            'motivo' => 'compra',
            'observacion' => 'Entrada auxiliar con lote existente',
        ])->assertCreated()
            ->assertJsonPath('success', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers de datos
    |--------------------------------------------------------------------------
    */

    private function crearEmpresa(): Empresa
    {
        return Empresa::create([
            'rut' => $this->rutUnico(),
            'razon_social' => 'Empresa Inventario ' . uniqid(),
        ]);
    }

    private function crearProducto(Empresa $empresa, array $overrides = []): Producto
    {
        $unidad = $this->obtenerUnidadBase();

        return Producto::create(array_merge([
            'empresa_id' => $empresa->id,
            'sku' => 'PROD-' . strtoupper(substr(uniqid(), -8)),
            'nombre' => 'Producto Movimiento Lote Test',
            'descripcion' => 'Producto para pruebas de movimientos por lote',
            'tipo_producto' => 'BIEN',
            'unidad_medida_id' => $unidad->id,
            'metodo_valorizacion' => 'PMP',
            'costo_promedio' => 0,
            'precio_venta_neto' => 1000,
            'afecto_iva' => true,
            'codigo_barra' => '780' . random_int(1000000000, 9999999999),
            'stock_minimo' => 0,
            'bodega_defecto_id' => null,
            'permite_merma' => true,
            'maneja_lotes' => false,
            'requiere_fecha_vencimiento' => false,
            'activo' => true,
        ], $overrides));
    }

    private function crearBodega(Empresa $empresa, array $overrides = []): Bodega
    {
        return Bodega::create(array_merge([
            'empresa_id' => $empresa->id,
            'codigo' => 'BOD-' . strtoupper(substr(uniqid(), -6)),
            'nombre' => 'Bodega Movimiento Lote Test',
            'direccion' => 'Santiago, Chile',
            'estado' => 'ACTIVA',
        ], $overrides));
    }

    private function crearLote(Empresa $empresa, Producto $producto, array $overrides = []): LoteInventario
    {
        return LoteInventario::create(array_merge([
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'codigo_lote' => 'LOT-' . strtoupper(substr(uniqid(), -8)),
            'fecha_fabricacion' => null,
            'fecha_vencimiento' => null,
            'observacion' => 'Lote creado por test',
            'activo' => true,
        ], $overrides));
    }

    private function obtenerUnidadBase(): UnidadMedida
    {
        return UnidadMedida::firstOrCreate(
            ['codigo' => 'UN'],
            [
                'nombre' => 'Unidad',
                'permite_decimal' => false,
                'activo' => true,
            ]
        );
    }

    private function rutUnico(): string
    {
        return (string) random_int(70000000, 99999999) . '-' . random_int(0, 9);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers de stock
    |--------------------------------------------------------------------------
    */

    private function obtenerStockConsolidado(Empresa $empresa, Producto $producto, Bodega $bodega): StockProducto
    {
        return StockProducto::query()
            ->where('empresa_id', $empresa->id)
            ->where('producto_id', $producto->id)
            ->where('bodega_id', $bodega->id)
            ->firstOrFail();
    }

    private function obtenerStockLote(
        Empresa $empresa,
        Producto $producto,
        Bodega $bodega,
        LoteInventario $lote
    ): StockLoteInventario {
        return StockLoteInventario::query()
            ->where('empresa_id', $empresa->id)
            ->where('producto_id', $producto->id)
            ->where('bodega_id', $bodega->id)
            ->where('lote_id', $lote->id)
            ->firstOrFail();
    }

    private function assertStockConsolidadoIgualASumaLotes(
        Empresa $empresa,
        Producto $producto,
        Bodega $bodega
    ): void {
        $stockConsolidado = $this->obtenerStockConsolidado($empresa, $producto, $bodega);

        $sumaLotes = StockLoteInventario::query()
            ->where('empresa_id', $empresa->id)
            ->where('producto_id', $producto->id)
            ->where('bodega_id', $bodega->id)
            ->sum('stock_actual');

        $this->assertEquals(
            round((float) $stockConsolidado->stock_actual, 4),
            round((float) $sumaLotes, 4),
            "El stock consolidado no coincide con la suma de stock_lotes para bodega {$bodega->id}."
        );
    }
}