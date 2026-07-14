<?php

namespace Tests\Feature\Integracion;

use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Cotizacion;
use App\Domains\Comercial\Models\EstadoCotizacion;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Services\CotizacionService;
use App\Domains\Comercial\Services\FacturaService;
use App\Domains\Contabilidad\Models\AsientoContable;
use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\MovimientoInventario;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\StockProducto;
use App\Domains\Inventario\Models\UnidadMedida;
use App\Domains\Inventario\Services\InventarioMovimientoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Integración real Inventario <-> Contabilidad vía CotizacionService::convertirEnFactura.
 *
 * Cubre el flujo agregado: una línea de cotización con `producto_id` vinculado a un
 * Producto de Inventario dispara, al facturar, una salida de inventario real (costeada
 * por InventarioMovimientoService, FIFO o PMP) y agrega una línea de Costo de Venta /
 * Inventario al asiento de la venta, con el monto exacto que Inventario calculó. Las
 * líneas sin `producto_id` (servicios) siguen sin tocar Inventario. La anulación de la
 * factura repone el stock. Ver HALLAZGOS-COLATERALES.md para el estado anterior
 * (sin integración) que este archivo documentaba.
 */
class InventarioContabilidadIntegracionTest extends TestCase
{
    use PreparaEntornoBase, RefreshDatabase;

    private Empresa $empresa;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        EstadoCotizacion::insert([
            ['id' => 1, 'nombre' => 'Borrador'],
            ['id' => 2, 'nombre' => 'Enviada'],
            ['id' => 3, 'nombre' => 'Aceptada'],
            ['id' => 4, 'nombre' => 'Rechazada'],
            ['id' => 5, 'nombre' => 'Facturada'],
        ]);

        $this->empresa = Empresa::create([
            'rut' => $this->rutUnico(),
            'razon_social' => 'Integracion Inv-Cont SpA',
        ]);

        $this->usuario = User::create([
            'nombre' => 'Operador Integracion',
            'email' => 'integracion@example.com',
            'password' => bcrypt('secret'),
            'empresa_id' => $this->empresa->id,
            'rol_id' => $this->rolSuperAdmin->id,
            'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id,
        ]);

        foreach ([
            ['152005', 'Clientes', 'ACTIVO'],
            ['501105', 'Ingresos por Venta', 'INGRESO'],
            ['353360', 'IVA Débito Fiscal', 'PASIVO'],
            ['601205', 'Costo Ventas Nacional', 'GASTO'],
            ['151005', 'Inventario Materiales', 'ACTIVO'],
        ] as [$codigo, $nombre, $tipo]) {
            PlanCuenta::create([
                'empresa_id' => $this->empresa->id,
                'codigo' => $codigo,
                'nombre' => $nombre,
                'tipo' => $tipo,
                'imputable' => true,
                'activo' => true,
            ]);
        }
    }

    /**
     * Prueba 1: vender -vía el flujo real Cotizacion -> Factura- un producto FIFO vinculado
     * por producto_id sí genera una salida de inventario real y una línea de costo de venta
     * en el asiento, con el costo exacto calculado por InventarioMovimientoService.
     */
    public function test_convertir_cotizacion_en_factura_con_producto_vinculado_genera_salida_de_inventario_y_costo_de_venta(): void
    {
        $producto = $this->crearProductoFifo();
        $bodega = $this->crearBodega();
        app(InventarioMovimientoService::class)->registrarMovimiento([
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodega->id,
            'cantidad' => 10,
            'costo_unitario' => 1000,
        ], $this->empresa->id, $this->usuario->id);

        $cliente = $this->crearCliente('9.999.999-9', 'Cliente Integración');

        $cotizacion = $this->crearCotizacionAceptada('COT-INT-001', $cliente, 5000, 950);
        $detalle = $cotizacion->detalles()->create([
            'producto_id' => $producto->id,
            'producto_nombre' => $producto->nombre,
            'cantidad' => 5,
            'precio_unitario' => 1000,
            'subtotal' => 5000,
        ]);

        $stockAntes = $this->obtenerStock($producto->id, $bodega->id);
        $this->assertEquals(10.0, (float) $stockAntes->stock_actual);

        $factura = app(CotizacionService::class)->convertirEnFactura($this->empresa->id, $cotizacion->id);

        $asiento = AsientoContable::with('detalles')
            ->where('empresa_id', $this->empresa->id)
            ->where('numero_comprobante', $factura->comprobante_contable)
            ->first();

        // Ahora el asiento tiene 5 líneas: CxC/Ventas/IVA (las 3 de siempre) + Costo de Venta/Inventario.
        $this->assertCount(5, $asiento->detalles);
        $lineaCosto = $asiento->detalles->firstWhere('cuenta_contable', '601205');
        $lineaInventario = $asiento->detalles->firstWhere('cuenta_contable', '151005');
        $this->assertNotNull($lineaCosto, 'Debe existir una línea de Costo de Venta (601205).');
        $this->assertNotNull($lineaInventario, 'Debe existir una línea de descarga de Inventario (151005).');
        $this->assertEquals(5000.0, (float) $lineaCosto->debe);
        $this->assertEquals(5000.0, (float) $lineaInventario->haber);

        // El stock se descontó realmente: 10 - 5 = 5.
        $stockDespues = $this->obtenerStock($producto->id, $bodega->id);
        $this->assertEquals(5.0, (float) $stockDespues->stock_actual);

        // El movimiento de salida quedó vinculado a la línea de la cotización.
        $detalle->refresh();
        $this->assertNotNull($detalle->movimiento_inventario_id);
        $movimiento = MovimientoInventario::find($detalle->movimiento_inventario_id);
        $this->assertSame(MovimientoInventario::TIPO_SALIDA, $movimiento->tipo);
        $this->assertEquals(5.0, (float) $movimiento->cantidad);
        $this->assertEquals(1000.0, (float) $movimiento->costo_unitario);
        $this->assertEquals(5000.0, (float) $movimiento->costo_total);
    }

    /**
     * Prueba 2: FIFO multi-lote. El costo de venta que llega al asiento debe ser el costo
     * mezclado real de las capas consumidas (no un promedio simplificado de las entradas).
     */
    public function test_asiento_de_venta_con_producto_fifo_multi_lote_incluye_costo_de_venta_exacto(): void
    {
        $producto = $this->crearProductoFifo();
        $bodega = $this->crearBodega();
        $servicio = app(InventarioMovimientoService::class);

        // Lote 1: 10 unidades a $1.000. Lote 2: 15 unidades a $1.500.
        $servicio->registrarMovimiento([
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodega->id,
            'cantidad' => 10,
            'costo_unitario' => 1000,
        ], $this->empresa->id, $this->usuario->id);

        $servicio->registrarMovimiento([
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodega->id,
            'cantidad' => 15,
            'costo_unitario' => 1500,
        ], $this->empresa->id, $this->usuario->id);

        $cliente = $this->crearCliente('9.888.888-8', 'Cliente FIFO Multi Lote');
        $cotizacion = $this->crearCotizacionAceptada('COT-INT-FIFO-MULTI', $cliente, 20000, 3800);
        // Venta de 20 unidades: consume 10@1000 + 10@1500 = 25.000.
        $cotizacion->detalles()->create([
            'producto_id' => $producto->id,
            'producto_nombre' => $producto->nombre,
            'cantidad' => 20,
            'precio_unitario' => 1000,
            'subtotal' => 20000,
        ]);

        $factura = app(CotizacionService::class)->convertirEnFactura($this->empresa->id, $cotizacion->id);

        $asiento = AsientoContable::with('detalles')
            ->where('empresa_id', $this->empresa->id)
            ->where('numero_comprobante', $factura->comprobante_contable)
            ->first();

        $lineaCosto = $asiento->detalles->firstWhere('cuenta_contable', '601205');
        $this->assertEquals(25000.0, (float) $lineaCosto->debe);
    }

    /**
     * Prueba 3: PMP (costo promedio ponderado). El costo de venta debe ser el promedio
     * ponderado vigente al momento de la salida.
     */
    public function test_asiento_de_venta_con_producto_pmp_incluye_costo_de_venta_como_promedio_ponderado(): void
    {
        $producto = $this->crearProductoPmp();
        $bodega = $this->crearBodega();
        $servicio = app(InventarioMovimientoService::class);

        // Entrada 1: 10 unidades a $1.000. Entrada 2: 10 unidades a $2.000. Promedio: $1.500.
        $servicio->registrarMovimiento([
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodega->id,
            'cantidad' => 10,
            'costo_unitario' => 1000,
        ], $this->empresa->id, $this->usuario->id);

        $servicio->registrarMovimiento([
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodega->id,
            'cantidad' => 10,
            'costo_unitario' => 2000,
        ], $this->empresa->id, $this->usuario->id);

        $cliente = $this->crearCliente('9.777.777-7', 'Cliente PMP');
        $cotizacion = $this->crearCotizacionAceptada('COT-INT-PMP', $cliente, 8000, 1520);
        $cotizacion->detalles()->create([
            'producto_id' => $producto->id,
            'producto_nombre' => $producto->nombre,
            'cantidad' => 8,
            'precio_unitario' => 1000,
            'subtotal' => 8000,
        ]);

        $factura = app(CotizacionService::class)->convertirEnFactura($this->empresa->id, $cotizacion->id);

        $asiento = AsientoContable::with('detalles')
            ->where('empresa_id', $this->empresa->id)
            ->where('numero_comprobante', $factura->comprobante_contable)
            ->first();

        $lineaCosto = $asiento->detalles->firstWhere('cuenta_contable', '601205');
        // 8 unidades * $1.500 (promedio ponderado) = $12.000.
        $this->assertEquals(12000.0, (float) $lineaCosto->debe);
    }

    /**
     * Prueba 4 (caso de borde): vender más cantidad de la disponible bloquea la operación
     * completa. No debe quedar factura, ni asiento, ni movimiento de inventario huérfano:
     * todo dentro de la misma transacción de convertirEnFactura.
     */
    public function test_vender_sin_stock_suficiente_revierte_toda_la_venta_sin_dejar_huerfanos(): void
    {
        $producto = $this->crearProductoFifo();
        $bodega = $this->crearBodega();
        app(InventarioMovimientoService::class)->registrarMovimiento([
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodega->id,
            'cantidad' => 3,
            'costo_unitario' => 1000,
        ], $this->empresa->id, $this->usuario->id);

        $cliente = $this->crearCliente('9.666.666-6', 'Cliente Sin Stock');
        $cotizacion = $this->crearCotizacionAceptada('COT-INT-SINSTOCK', $cliente, 10000, 1900);
        $cotizacion->detalles()->create([
            'producto_id' => $producto->id,
            'producto_nombre' => $producto->nombre,
            'cantidad' => 10, // más que el stock disponible (3)
            'precio_unitario' => 1000,
            'subtotal' => 10000,
        ]);

        $this->expectException(ValidationException::class);

        try {
            app(CotizacionService::class)->convertirEnFactura($this->empresa->id, $cotizacion->id);
        } finally {
            $this->assertSame(0, Factura::where('empresa_id', $this->empresa->id)->count());
            $this->assertSame(0, AsientoContable::where('empresa_id', $this->empresa->id)->count());
            $this->assertSame(0, MovimientoInventario::where('empresa_id', $this->empresa->id)
                ->where('tipo', MovimientoInventario::TIPO_SALIDA)
                ->count());

            $stock = $this->obtenerStock($producto->id, $bodega->id);
            $this->assertEquals(3.0, (float) $stock->stock_actual, 'El stock no debe quedar alterado tras el rollback.');

            $cotizacion->refresh();
            $this->assertNotEquals(5, $cotizacion->estado_id, 'La cotización no debe quedar marcada como Facturada tras el rollback.');
        }
    }

    /**
     * Prueba 5: anular la factura de venta repone el stock exactamente en la cantidad
     * vendida, reingresando al mismo costo con el que salió (no al costo vigente actual).
     */
    public function test_anular_factura_de_venta_repone_el_stock_de_inventario(): void
    {
        $producto = $this->crearProductoFifo();
        $bodega = $this->crearBodega();
        app(InventarioMovimientoService::class)->registrarMovimiento([
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodega->id,
            'cantidad' => 10,
            'costo_unitario' => 1000,
        ], $this->empresa->id, $this->usuario->id);

        $cliente = $this->crearCliente('9.555.555-5', 'Cliente Anulacion');
        $cotizacion = $this->crearCotizacionAceptada('COT-INT-ANULA', $cliente, 5000, 950);
        $cotizacion->detalles()->create([
            'producto_id' => $producto->id,
            'producto_nombre' => $producto->nombre,
            'cantidad' => 5,
            'precio_unitario' => 1000,
            'subtotal' => 5000,
        ]);

        $factura = app(CotizacionService::class)->convertirEnFactura($this->empresa->id, $cotizacion->id);

        $stockTrasVenta = $this->obtenerStock($producto->id, $bodega->id);
        $this->assertEquals(5.0, (float) $stockTrasVenta->stock_actual);

        // Cambia el costo vigente del producto: la reposición debe usar el costo original de
        // la salida ($1.000), no este nuevo costo ($5.000).
        app(InventarioMovimientoService::class)->registrarMovimiento([
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodega->id,
            'cantidad' => 5,
            'costo_unitario' => 5000,
        ], $this->empresa->id, $this->usuario->id);

        app(FacturaService::class)->anularFactura(
            $this->empresa->id,
            $this->usuario->id,
            $factura->id,
            'Anulación de prueba de integración'
        );

        $factura->refresh();
        $this->assertSame('ANULADA', $factura->estado);

        $asientoOriginal = AsientoContable::where('empresa_id', $this->empresa->id)
            ->where('numero_comprobante', $factura->comprobante_contable)
            ->first();
        $this->assertTrue(
            in_array(strtoupper((string) $asientoOriginal->estado), ['REVERSADO', 'ANULADO'], true),
            'El asiento original de la venta debe quedar marcado como reversado tras anular la factura.'
        );

        // Stock: 10 (entrada) - 5 (venta) + 5 (entrada a costo distinto) + 5 (reposición por anulación) = 15.
        $stockFinal = $this->obtenerStock($producto->id, $bodega->id);
        $this->assertEquals(15.0, (float) $stockFinal->stock_actual);

        $entradaReposicion = MovimientoInventario::where('empresa_id', $this->empresa->id)
            ->where('producto_id', $producto->id)
            ->where('tipo', MovimientoInventario::TIPO_ENTRADA)
            ->orderByDesc('id')
            ->first();
        $this->assertEquals(1000.0, (float) $entradaReposicion->costo_unitario, 'La reposición debe reingresar al costo original de la salida, no al costo vigente.');
    }

    /**
     * Anular una venta cuyo costo original fue $0 (bonificación/muestra gratis) no debe fallar:
     * reponer el stock al mismo costo $0 con el que salió es restaurar el estado anterior, no una
     * entrada nueva sin confirmar.
     */
    public function test_anular_factura_de_venta_a_costo_cero_repone_el_stock_sin_fallar(): void
    {
        $producto = $this->crearProductoFifo();
        $bodega = $this->crearBodega();
        app(InventarioMovimientoService::class)->registrarMovimiento([
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodega->id,
            'cantidad' => 5,
            'costo_unitario' => 0,
            'costo_cero_confirmado' => true,
        ], $this->empresa->id, $this->usuario->id);

        $cliente = $this->crearCliente('9.444.444-4', 'Cliente Costo Cero');
        $cotizacion = $this->crearCotizacionAceptada('COT-INT-CEROCOSTO', $cliente, 0, 0);
        $cotizacion->detalles()->create([
            'producto_id' => $producto->id,
            'producto_nombre' => $producto->nombre,
            'cantidad' => 5,
            'precio_unitario' => 0,
            'subtotal' => 0,
        ]);

        $factura = app(CotizacionService::class)->convertirEnFactura($this->empresa->id, $cotizacion->id);

        app(FacturaService::class)->anularFactura(
            $this->empresa->id,
            $this->usuario->id,
            $factura->id,
            'Anulación de venta a costo cero'
        );

        $factura->refresh();
        $this->assertSame('ANULADA', $factura->estado);

        $stockFinal = $this->obtenerStock($producto->id, $bodega->id);
        $this->assertEquals(5.0, (float) $stockFinal->stock_actual);
    }

    /**
     * Prueba 6: un ítem SIN producto_id (servicio) sigue sin tocar Inventario, como antes de
     * esta integración.
     */
    public function test_vender_item_sin_producto_id_sigue_sin_tocar_inventario(): void
    {
        $cliente = $this->crearCliente('8.888.888-8', 'Cliente Sin Costo Venta');
        $cotizacion = $this->crearCotizacionAceptada('COT-INT-002', $cliente, 10000, 1900);
        $cotizacion->detalles()->create([
            'producto_nombre' => 'Servicio genérico sin inventario',
            'cantidad' => 1,
            'precio_unitario' => 10000,
            'subtotal' => 10000,
        ]);

        $factura = app(CotizacionService::class)->convertirEnFactura($this->empresa->id, $cotizacion->id);

        $asiento = AsientoContable::with('detalles')
            ->where('empresa_id', $this->empresa->id)
            ->where('numero_comprobante', $factura->comprobante_contable)
            ->first();

        // Sigue teniendo exactamente 3 líneas: sin producto_id no hay costo de venta que agregar.
        $this->assertCount(3, $asiento->detalles);
        $codigosCuenta = $asiento->detalles->pluck('cuenta_contable')->sort()->values()->all();
        $this->assertEquals(['152005', '353360', '501105'], $codigosCuenta);

        $this->assertSame(0, MovimientoInventario::where('empresa_id', $this->empresa->id)
            ->where('tipo', MovimientoInventario::TIPO_SALIDA)
            ->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function crearProductoFifo(array $overrides = []): Producto
    {
        return Producto::create(array_merge([
            'empresa_id' => $this->empresa->id,
            'sku' => 'PROD-INT-FIFO-'.strtoupper(substr(uniqid(), -8)),
            'nombre' => 'Producto Integracion FIFO',
            'descripcion' => 'Producto para pruebas de integracion Inventario-Contabilidad (FIFO)',
            'tipo_producto' => 'BIEN',
            'unidad_medida_id' => $this->obtenerUnidadBase()->id,
            'metodo_valorizacion' => 'FIFO',
            'costo_promedio' => 0,
            'precio_venta_neto' => 1000,
            'afecto_iva' => true,
            'codigo_barra' => '781'.random_int(1000000000, 9999999999),
            'stock_minimo' => 0,
            'bodega_defecto_id' => null,
            'permite_merma' => true,
            'activo' => true,
        ], $overrides));
    }

    private function crearProductoPmp(array $overrides = []): Producto
    {
        return $this->crearProductoFifo(array_merge([
            'sku' => 'PROD-INT-PMP-'.strtoupper(substr(uniqid(), -8)),
            'nombre' => 'Producto Integracion PMP',
            'metodo_valorizacion' => 'PMP',
            'codigo_barra' => '782'.random_int(1000000000, 9999999999),
        ], $overrides));
    }

    private function crearBodega(array $overrides = []): Bodega
    {
        return Bodega::create(array_merge([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'BOD-INT-'.strtoupper(substr(uniqid(), -6)),
            'nombre' => 'Bodega Integracion Test',
            'direccion' => 'Santiago, Chile',
            'estado' => 'ACTIVA',
        ], $overrides));
    }

    private function crearCliente(string $rut, string $razonSocial): Cliente
    {
        return Cliente::create([
            'empresa_id' => $this->empresa->id,
            'rut' => $rut,
            'razon_social' => $razonSocial,
            'estado' => 'ACTIVO',
        ]);
    }

    private function crearCotizacionAceptada(string $numero, Cliente $cliente, float $montoNeto, float $montoIva): Cotizacion
    {
        return Cotizacion::create([
            'empresa_id' => $this->empresa->id,
            'cliente_id' => $cliente->id,
            'nombre_cliente' => $cliente->razon_social,
            'estado_id' => 3, // Aceptada
            'numero_cotizacion' => $numero,
            'subtotal' => $montoNeto,
            'monto_neto' => $montoNeto,
            'monto_iva' => $montoIva,
            'monto_total' => $montoNeto + $montoIva,
            'total' => $montoNeto + $montoIva,
            'fecha_emision' => now(),
        ]);
    }

    private function obtenerStock(int $productoId, int $bodegaId): ?StockProducto
    {
        return StockProducto::where('empresa_id', $this->empresa->id)
            ->where('producto_id', $productoId)
            ->where('bodega_id', $bodegaId)
            ->first();
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
        return (string) random_int(60000000, 69999999).'-'.random_int(0, 9);
    }
}
