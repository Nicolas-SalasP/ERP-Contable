<?php

namespace Tests\Feature\Integracion;

use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Cotizacion;
use App\Domains\Comercial\Models\EstadoCotizacion;
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
 * Auditoría de integración Inventario <-> Contabilidad.
 *
 * HALLAZGO PRINCIPAL (ver HALLAZGOS-COLATERALES.md): NO existe hoy un flujo automático que
 * conecte una venta (Comercial\Services\CotizacionService::convertirEnFactura /
 * FacturaService) con una salida de inventario (Inventario\Services\
 * InventarioMovimientoService::registrarMovimiento). Los detalles de una Cotizacion
 * (cotizacion_detalles.producto_nombre) son texto libre, sin FK al modelo
 * Inventario\Models\Producto. El asiento contable que genera una venta solo registra
 * CxC/Ventas/IVA Débito — nunca una línea de "Costo de Venta" / "Costo de Mercadería
 * Vendida" tomada de Inventario. Cada módulo se opera de forma completamente
 * independiente.
 *
 * Esta clase por lo tanto NO puede escribir el test end-to-end "venta -> salida de
 * inventario -> asiento con costo de venta" pedido en el Paso 2 del plan, porque ese
 * flujo no existe en el código. En su lugar:
 *  - Prueba 1: demuestra objetivamente la ausencia de conexión (ejercitando el flujo real
 *    de venta con un producto que sí existe en Inventario, y verificando que Inventario
 *    queda intacto).
 *  - Pruebas 2-6: ejercitan cada mitad por separado con las clases reales encadenadas
 *    (sin mocks): el costeo FIFO/PMP end-to-end vía InventarioMovimientoService, y el
 *    asiento de venta vía CotizacionService/FacturaService.
 *  - Pruebas 7-8: casos de borde de integración (stock insuficiente, anulación de
 *    factura) documentando el comportamiento real observado.
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
     * Prueba 1 (hallazgo principal): se vende -vía el flujo real Cotizacion -> Factura- un
     * producto que efectivamente existe en Inventario con stock valorizado. Si existiera
     * integración automática, la venta debería generar una salida de inventario (y una
     * línea de costo de venta en el asiento). No ocurre: Inventario queda exactamente
     * igual que antes de la venta, y el asiento solo tiene las 3 líneas comerciales.
     */
    public function test_convertir_cotizacion_en_factura_no_genera_ninguna_salida_de_inventario(): void
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

        $cliente = Cliente::create([
            'empresa_id' => $this->empresa->id,
            'rut' => '9.999.999-9',
            'razon_social' => 'Cliente Integración',
            'estado' => 'ACTIVO',
        ]);

        $cotizacion = Cotizacion::create([
            'empresa_id' => $this->empresa->id,
            'cliente_id' => $cliente->id,
            'nombre_cliente' => $cliente->razon_social,
            'estado_id' => 3, // Aceptada
            'numero_cotizacion' => 'COT-INT-001',
            'subtotal' => 5000,
            'monto_neto' => 5000,
            'monto_iva' => 950,
            'monto_total' => 5950,
            'total' => 5950,
            'fecha_emision' => now(),
        ]);
        // El detalle referencia el producto solo por nombre libre: no hay FK real al
        // Producto de Inventario, aunque el nombre coincida con uno existente.
        $cotizacion->detalles()->create([
            'producto_nombre' => $producto->nombre,
            'cantidad' => 5,
            'precio_unitario' => 1000,
            'subtotal' => 5000,
        ]);

        $stockAntes = StockProducto::where('empresa_id', $this->empresa->id)
            ->where('producto_id', $producto->id)
            ->where('bodega_id', $bodega->id)
            ->first();
        $this->assertEquals(10.0, (float) $stockAntes->stock_actual);

        $factura = app(CotizacionService::class)->convertirEnFactura($this->empresa->id, $cotizacion->id);

        // La venta se registró y generó su asiento comercial (CxC/Ventas/IVA)...
        $this->assertNotNull($factura->comprobante_contable);
        $asiento = AsientoContable::with('detalles')
            ->where('empresa_id', $this->empresa->id)
            ->where('numero_comprobante', $factura->comprobante_contable)
            ->first();
        $this->assertCount(3, $asiento->detalles);
        $codigosCuenta = $asiento->detalles->pluck('cuenta_contable')->sort()->values()->all();
        $this->assertEquals(['152005', '353360', '501105'], $codigosCuenta);

        // ... pero el stock de Inventario NO se movió ni un poco: la "venta" nunca tocó
        // InventarioMovimientoService. No hay línea de costo de venta y no hay ningún
        // MovimientoInventario de salida asociado a esta factura.
        $stockDespues = StockProducto::where('empresa_id', $this->empresa->id)
            ->where('producto_id', $producto->id)
            ->where('bodega_id', $bodega->id)
            ->first();
        $this->assertEquals(10.0, (float) $stockDespues->stock_actual, 'La venta no debería mover stock: no existe integración automática.');

        $this->assertSame(0, MovimientoInventario::where('empresa_id', $this->empresa->id)
            ->where('tipo', MovimientoInventario::TIPO_SALIDA)
            ->count());
    }

    /**
     * Prueba 2: mitad Inventario en aislamiento, método FIFO, capa única.
     * Verifica que InventarioMovimientoService (el servicio real que se llamaría en un
     * flujo de venta si existiera integración) calcula el costo de venta correcto.
     */
    public function test_fifo_capa_unica_calcula_costo_de_venta_correcto_via_movimiento_service(): void
    {
        $producto = $this->crearProductoFifo();
        $bodega = $this->crearBodega();
        $servicio = app(InventarioMovimientoService::class);

        $servicio->registrarMovimiento([
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodega->id,
            'cantidad' => 10,
            'costo_unitario' => 1000,
        ], $this->empresa->id, $this->usuario->id);

        $salida = $servicio->registrarMovimiento([
            'tipo' => MovimientoInventario::TIPO_SALIDA,
            'producto_id' => $producto->id,
            'bodega_origen_id' => $bodega->id,
            'cantidad' => 5,
        ], $this->empresa->id, $this->usuario->id);

        $this->assertEquals(1000.0, (float) $salida->costo_unitario);
        $this->assertEquals(5000.0, (float) $salida->costo_total);
    }

    /**
     * Prueba 3: mitad Inventario en aislamiento, método FIFO multi-lote (costos distintos).
     * El costo de venta debe reflejar el costo mezclado real de las capas consumidas,
     * no un promedio simplificado de todas las entradas históricas.
     */
    public function test_fifo_multi_lote_calcula_costo_mezclado_real_no_promedio_simplificado(): void
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

        // Venta de 20 unidades: consume 10@1000 + 10@1500 = 25.000 / 20 = 1.250 c/u.
        $salida = $servicio->registrarMovimiento([
            'tipo' => MovimientoInventario::TIPO_SALIDA,
            'producto_id' => $producto->id,
            'bodega_origen_id' => $bodega->id,
            'cantidad' => 20,
        ], $this->empresa->id, $this->usuario->id);

        $this->assertEquals(1250.0, (float) $salida->costo_unitario);
        $this->assertEquals(25000.0, (float) $salida->costo_total);
        // Confirma que NO es el promedio simplificado (1000+1500)/2 = 1250 coincide numéricamente
        // en este caso por simetría de cantidades; se agrega un tercer lote para descartar ese
        // espejismo y forzar una mezcla realmente ponderada por cantidad.
        $servicio->registrarMovimiento([
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodega->id,
            'cantidad' => 3,
            'costo_unitario' => 3000,
        ], $this->empresa->id, $this->usuario->id);

        // Queda: 5@1500 + 3@3000. Venta de 6: consume 5@1500 + 1@3000 = (7500+3000)/6 = 1750.
        $salida2 = $servicio->registrarMovimiento([
            'tipo' => MovimientoInventario::TIPO_SALIDA,
            'producto_id' => $producto->id,
            'bodega_origen_id' => $bodega->id,
            'cantidad' => 6,
        ], $this->empresa->id, $this->usuario->id);

        $this->assertEquals(1750.0, (float) $salida2->costo_unitario);
        $this->assertEquals(10500.0, (float) $salida2->costo_total);
    }

    /**
     * Prueba 4/5: mitad Inventario en aislamiento, método PMP (costo promedio ponderado).
     * El costo de venta debe ser el costo promedio ponderado vigente al momento de la salida.
     */
    public function test_pmp_calcula_costo_de_venta_como_promedio_ponderado_vigente(): void
    {
        $producto = $this->crearProductoPmp();
        $bodega = $this->crearBodega();
        $servicio = app(InventarioMovimientoService::class);

        // Entrada 1: 10 unidades a $1.000. Entrada 2: 10 unidades a $2.000.
        // Promedio ponderado: (10*1000 + 10*2000) / 20 = 1.500.
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

        $salida = $servicio->registrarMovimiento([
            'tipo' => MovimientoInventario::TIPO_SALIDA,
            'producto_id' => $producto->id,
            'bodega_origen_id' => $bodega->id,
            'cantidad' => 8,
        ], $this->empresa->id, $this->usuario->id);

        $this->assertEquals(1500.0, (float) $salida->costo_unitario);
        $this->assertEquals(12000.0, (float) $salida->costo_total);

        $stock = StockProducto::where('empresa_id', $this->empresa->id)
            ->where('producto_id', $producto->id)
            ->where('bodega_id', $bodega->id)
            ->first();
        // El promedio ponderado no cambia con la salida (PMP solo se recalcula en entradas).
        $this->assertEquals(1500.0, (float) $stock->costo_promedio);
        $this->assertEquals(12.0, (float) $stock->stock_actual);
    }

    /**
     * Prueba 6: mitad Contabilidad en aislamiento. Confirma que el asiento generado por
     * CotizacionService::convertirEnFactura no incluye, bajo ninguna circunstancia, una
     * línea de costo de venta/costo de mercadería vendida (porque no hay dato de
     * Inventario que se le pueda pasar): solo las 3 líneas comerciales de siempre.
     */
    public function test_asiento_de_venta_nunca_incluye_linea_de_costo_de_venta(): void
    {
        $cliente = Cliente::create([
            'empresa_id' => $this->empresa->id,
            'rut' => '8.888.888-8',
            'razon_social' => 'Cliente Sin Costo Venta',
            'estado' => 'ACTIVO',
        ]);

        $cotizacion = Cotizacion::create([
            'empresa_id' => $this->empresa->id,
            'cliente_id' => $cliente->id,
            'nombre_cliente' => $cliente->razon_social,
            'estado_id' => 3,
            'numero_cotizacion' => 'COT-INT-002',
            'subtotal' => 10000,
            'monto_neto' => 10000,
            'monto_iva' => 1900,
            'monto_total' => 11900,
            'total' => 11900,
            'fecha_emision' => now(),
        ]);
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

        foreach ($asiento->detalles as $detalle) {
            $this->assertStringNotContainsStringIgnoringCase('costo', (string) $detalle->descripcion_extensa);
        }
        $this->assertCount(3, $asiento->detalles);
    }

    /**
     * Prueba 7 (caso de borde): si la salida de inventario pide más cantidad de la
     * disponible, InventarioMovimientoService bloquea la operación con una excepción de
     * validación (no permite stock negativo ni deja un costo de venta "no calculable").
     * Como no hay integración automática, este guard nunca se ejerce hoy desde una venta
     * real -- solo si alguien registra manualmente la salida de inventario.
     */
    public function test_salida_sin_stock_suficiente_es_bloqueada_no_se_permite_stock_negativo(): void
    {
        $producto = $this->crearProductoFifo();
        $bodega = $this->crearBodega();
        $servicio = app(InventarioMovimientoService::class);

        $servicio->registrarMovimiento([
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodega->id,
            'cantidad' => 5,
            'costo_unitario' => 1000,
        ], $this->empresa->id, $this->usuario->id);

        $this->expectException(ValidationException::class);

        try {
            $servicio->registrarMovimiento([
                'tipo' => MovimientoInventario::TIPO_SALIDA,
                'producto_id' => $producto->id,
                'bodega_origen_id' => $bodega->id,
                'cantidad' => 10,
            ], $this->empresa->id, $this->usuario->id);
        } finally {
            $stock = StockProducto::where('empresa_id', $this->empresa->id)
                ->where('producto_id', $producto->id)
                ->where('bodega_id', $bodega->id)
                ->first();
            $this->assertEquals(5.0, (float) $stock->stock_actual, 'El stock no debe quedar negativo ni alterado tras el rechazo.');
        }
    }

    /**
     * Prueba 8 (caso de borde de integración — hallazgo): al anular una factura de VENTA,
     * FacturaService::anularFactura reversa correctamente el asiento contable (CxC/Ventas/
     * IVA), pero como nunca existió un MovimientoInventario vinculado a esa factura, no hay
     * nada que "revertir" del lado de Inventario. El stock permanece exactamente donde
     * estaba antes y después de anular la venta: ni se descontó al vender, ni se repone al
     * anular. Esto documenta objetivamente que la ausencia de integración automática
     * también implica ausencia de reversa automática (consistente por diseño, pero crítico
     * para la auditoría: si mañana se agrega la integración de venta -> salida, se debe
     * agregar en el mismo cambio la reversa de esa salida al anular).
     */
    public function test_anular_factura_venta_reversa_el_asiento_pero_no_toca_inventario_porque_nunca_lo_vinculo(): void
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

        $cliente = Cliente::create([
            'empresa_id' => $this->empresa->id,
            'rut' => '7.777.777-7',
            'razon_social' => 'Cliente Anulacion',
            'estado' => 'ACTIVO',
        ]);

        $cotizacion = Cotizacion::create([
            'empresa_id' => $this->empresa->id,
            'cliente_id' => $cliente->id,
            'nombre_cliente' => $cliente->razon_social,
            'estado_id' => 3,
            'numero_cotizacion' => 'COT-INT-003',
            'subtotal' => 5000,
            'monto_neto' => 5000,
            'monto_iva' => 950,
            'monto_total' => 5950,
            'total' => 5950,
            'fecha_emision' => now(),
        ]);
        $cotizacion->detalles()->create([
            'producto_nombre' => $producto->nombre,
            'cantidad' => 5,
            'precio_unitario' => 1000,
            'subtotal' => 5000,
        ]);

        $factura = app(CotizacionService::class)->convertirEnFactura($this->empresa->id, $cotizacion->id);

        // Ahora, fuera de este flujo, alguien registra manualmente la salida real de
        // inventario para esta venta (como se opera hoy en producción: dos procesos
        // separados y manuales). El costo original calculado en ese momento fue $1.000/u,
        // aunque el costo actual del producto cambie después.
        $servicio = app(InventarioMovimientoService::class);
        $salidaManual = $servicio->registrarMovimiento([
            'tipo' => MovimientoInventario::TIPO_SALIDA,
            'producto_id' => $producto->id,
            'bodega_origen_id' => $bodega->id,
            'cantidad' => 5,
        ], $this->empresa->id, $this->usuario->id);
        $this->assertEquals(1000.0, (float) $salidaManual->costo_unitario);

        // Cambia el costo vigente del producto con una nueva entrada a $5.000/u.
        $servicio->registrarMovimiento([
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

        // El asiento comercial quedó cuadrado (reversado): la suma de detalles del asiento
        // original + su reverso es 0 en cada cuenta (verificado indirectamente via el status
        // REVERSADO del asiento original).
        $asientoOriginal = AsientoContable::where('empresa_id', $this->empresa->id)
            ->where('numero_comprobante', $factura->comprobante_contable)
            ->first();
        $this->assertNotNull($asientoOriginal);
        $this->assertTrue(
            in_array(strtoupper((string) $asientoOriginal->estado), ['REVERSADO', 'ANULADO'], true),
            'El asiento original de la venta debe quedar marcado como reversado tras anular la factura.'
        );

        // Inventario, en cambio, no se enteró de la anulación: el movimiento de salida
        // manual sigue existiendo tal cual, con el costo original ($1.000/u), y el stock NO
        // se repuso automáticamente. Si mañana existiera integración automática venta ->
        // salida, este es el mismo punto donde debería dispararse la reversa del
        // movimiento de inventario.
        $salidaManual->refresh();
        $this->assertEquals(1000.0, (float) $salidaManual->costo_unitario, 'El costo del movimiento original no debe recalcularse con el costo vigente actual ($5.000).');

        $stockFinal = StockProducto::where('empresa_id', $this->empresa->id)
            ->where('producto_id', $producto->id)
            ->where('bodega_id', $bodega->id)
            ->first();
        // 10 (entrada inicial) - 5 (salida manual) + 5 (segunda entrada) = 10; no hubo reposición automática por la anulación.
        $this->assertEquals(10.0, (float) $stockFinal->stock_actual);
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
