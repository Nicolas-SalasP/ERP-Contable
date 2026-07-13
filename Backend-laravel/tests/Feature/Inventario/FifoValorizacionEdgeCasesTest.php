<?php

namespace Tests\Feature\Inventario;

use App\Domains\Core\Models\Empresa;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\InventarioValorizacionCapa;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\StockProducto;
use App\Domains\Inventario\Models\UnidadMedida;
use App\Domains\Inventario\Services\Valorizacion\FifoValorizacionStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Casos de borde de la estrategia FIFO de valorización de inventario, complementarios a:
 * - InventarioValorizacionServiceTest (PMP)
 * - InventarioEnterpriseHardeningTest::test_producto_fifo_consume_capas_en_orden_de_entrada (caso feliz multi-capa vía HTTP)
 *
 * Objetivo: cubrir bordes que rompen en producción real (consumo parcial/exacto, stock insuficiente,
 * desincronización capas/stock, costo cero, devoluciones, precisión decimal) sin duplicar lo ya probado.
 */
class FifoValorizacionEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    private FifoValorizacionStrategy $fifo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fifo = app(FifoValorizacionStrategy::class);
    }

    public function test_salida_consume_multiples_capas_con_costos_distintos_en_orden_de_entrada(): void
    {
        $empresa = $this->crearEmpresa();
        $producto = $this->crearProductoFifo($empresa);
        $bodega = $this->crearBodega($empresa);
        $stock = $this->crearStockVacio($empresa, $producto, $bodega);

        // Lote 1: 10 unidades a $100. Lote 2: 15 unidades a $150.
        $this->fifo->calcularEntrada($stock, $producto, 10, 100);
        $this->fifo->calcularEntrada($stock->refresh(), $producto, 15, 150);

        // Vender 20 unidades debe consumir las 10 del lote 1 + 10 del lote 2.
        $resultado = $this->fifo->calcularSalida($stock->refresh(), $producto, 20);

        $this->assertEquals(25.0, $resultado['stock_antes']);
        $this->assertEquals(5.0, $resultado['stock_despues']);
        // Costo mezclado: (10*100 + 10*150) / 20 = 125.
        $this->assertEquals(125.0, $resultado['costo_unitario']);
        $this->assertEquals(2500.0, $resultado['costo_total']);
        $this->assertEquals(3250.0, $resultado['valor_antes']);
        $this->assertEquals(750.0, $resultado['valor_despues']);

        $capas = $this->capasDe($empresa, $producto, $bodega);
        $this->assertCount(2, $capas);

        $this->assertEquals(0.0, (float) $capas[0]->cantidad_disponible);
        $this->assertEquals(0.0, (float) $capas[0]->valor_disponible);
        $this->assertEquals(InventarioValorizacionCapa::ESTADO_CONSUMIDA, $capas[0]->estado);

        $this->assertEquals(5.0, (float) $capas[1]->cantidad_disponible);
        $this->assertEquals(750.0, (float) $capas[1]->valor_disponible);
        $this->assertEquals(InventarioValorizacionCapa::ESTADO_ABIERTA, $capas[1]->estado);

        $stock->refresh();
        $this->assertEquals(5.0, (float) $stock->stock_actual);
        $this->assertEquals(750.0, (float) $stock->valor_total);
        $this->assertEquals(150.0, (float) $stock->costo_promedio);
    }

    public function test_salida_parcial_deja_la_capa_con_saldo_disponible_positivo(): void
    {
        $empresa = $this->crearEmpresa();
        $producto = $this->crearProductoFifo($empresa);
        $bodega = $this->crearBodega($empresa);
        $stock = $this->crearStockVacio($empresa, $producto, $bodega);

        $this->fifo->calcularEntrada($stock, $producto, 10, 100);
        $this->fifo->calcularSalida($stock->refresh(), $producto, 4);

        $capas = $this->capasDe($empresa, $producto, $bodega);
        $this->assertCount(1, $capas);
        $this->assertEquals(6.0, (float) $capas[0]->cantidad_disponible);
        $this->assertEquals(600.0, (float) $capas[0]->valor_disponible);
        $this->assertEquals(InventarioValorizacionCapa::ESTADO_ABIERTA, $capas[0]->estado);
    }

    public function test_salida_exacta_agota_la_capa_y_no_vuelve_a_consumirse(): void
    {
        $empresa = $this->crearEmpresa();
        $producto = $this->crearProductoFifo($empresa);
        $bodega = $this->crearBodega($empresa);
        $stock = $this->crearStockVacio($empresa, $producto, $bodega);

        $this->fifo->calcularEntrada($stock, $producto, 10, 100);
        $this->fifo->calcularSalida($stock->refresh(), $producto, 10);

        $capaAgotada = $this->capasDe($empresa, $producto, $bodega)->first();
        $this->assertEquals(0.0, (float) $capaAgotada->cantidad_disponible);
        $this->assertEquals(InventarioValorizacionCapa::ESTADO_CONSUMIDA, $capaAgotada->estado);

        // Nueva entrada crea una capa nueva; la salida siguiente NO debe reutilizar la capa ya agotada.
        $this->fifo->calcularEntrada($stock->refresh(), $producto, 5, 200);
        $resultado = $this->fifo->calcularSalida($stock->refresh(), $producto, 5);

        $this->assertEquals(200.0, $resultado['costo_unitario']);

        $capaAgotada->refresh();
        $this->assertEquals(0.0, (float) $capaAgotada->cantidad_disponible);
        $this->assertEquals(InventarioValorizacionCapa::ESTADO_CONSUMIDA, $capaAgotada->estado);

        $capaNueva = $this->capasDe($empresa, $producto, $bodega)->last();
        $this->assertEquals(0.0, (float) $capaNueva->cantidad_disponible);
        $this->assertEquals(InventarioValorizacionCapa::ESTADO_CONSUMIDA, $capaNueva->estado);
    }

    public function test_salida_mayor_al_stock_lanza_excepcion_y_no_toca_las_capas(): void
    {
        $empresa = $this->crearEmpresa();
        $producto = $this->crearProductoFifo($empresa);
        $bodega = $this->crearBodega($empresa);
        $stock = $this->crearStockVacio($empresa, $producto, $bodega);

        $this->fifo->calcularEntrada($stock, $producto, 10, 100);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Stock insuficiente');

        try {
            $this->fifo->calcularSalida($stock->refresh(), $producto, 15);
        } finally {
            $capa = $this->capasDe($empresa, $producto, $bodega)->first();
            $this->assertEquals(10.0, (float) $capa->cantidad_disponible);
            $this->assertEquals(InventarioValorizacionCapa::ESTADO_ABIERTA, $capa->estado);
        }
    }

    /**
     * Documenta un caso de borde real de inconsistencia de datos: si stock_actual queda
     * desincronizado por encima de la suma real de capas abiertas (p.ej. por una migración de
     * datos legados o un bug en otro punto), la validación inicial de "stock insuficiente" no lo
     * detecta (usa stock_actual), pero el consumo de capas sí falla con un mensaje distinto.
     * Verificamos además que, cuando el llamador envuelve la operación en una transacción (como
     * hace InventarioMovimientoService en producción), el consumo parcial de capas se revierte.
     */
    public function test_salida_con_capas_desincronizadas_del_stock_lanza_excepcion_distinta_y_revierte(): void
    {
        $empresa = $this->crearEmpresa();
        $producto = $this->crearProductoFifo($empresa);
        $bodega = $this->crearBodega($empresa);

        // Stock indica 20 unidades disponibles, pero solo existe una capa abierta con 15.
        $stock = StockProducto::create([
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'stock_actual' => 20,
            'costo_promedio' => 100,
            'valor_total' => 2000,
        ]);

        InventarioValorizacionCapa::create([
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'lote_id' => null,
            'movimiento_origen_id' => null,
            'cantidad_inicial' => 15,
            'cantidad_disponible' => 15,
            'costo_unitario' => 100,
            'valor_disponible' => 1500,
            'fecha_entrada' => now()->subDay(),
            'estado' => InventarioValorizacionCapa::ESTADO_ABIERTA,
        ]);

        $lanzada = null;

        try {
            DB::transaction(function () use ($stock, $producto) {
                $this->fifo->calcularSalida($stock, $producto, 20);
            });
        } catch (RuntimeException $e) {
            $lanzada = $e;
        }

        $this->assertNotNull($lanzada);
        $this->assertStringContainsString('Capas FIFO insuficientes', $lanzada->getMessage());

        // La capa debe quedar intacta: la transacción envolvente revirtió el consumo parcial.
        $capa = $this->capasDe($empresa, $producto, $bodega)->first();
        $this->assertEquals(15.0, (float) $capa->cantidad_disponible);
        $this->assertEquals(InventarioValorizacionCapa::ESTADO_ABIERTA, $capa->estado);

        $stock->refresh();
        $this->assertEquals(20.0, (float) $stock->stock_actual);
    }

    public function test_entrada_a_costo_cero_es_aceptada_y_diluye_el_costo_promedio(): void
    {
        $empresa = $this->crearEmpresa();
        $producto = $this->crearProductoFifo($empresa);
        $bodega = $this->crearBodega($empresa);
        $stock = $this->crearStockVacio($empresa, $producto, $bodega);

        $this->fifo->calcularEntrada($stock, $producto, 10, 100);
        $resultado = $this->fifo->calcularEntrada($stock->refresh(), $producto, 5, 0);

        $this->assertEquals(0.0, $resultado['costo_unitario']);
        $this->assertEquals(0.0, $resultado['costo_total']);
        // (1000 + 0) / 15 = 66.6667: una entrada a costo 0 diluye el promedio sin ninguna
        // validación de negocio que la rechace o advierta.
        $this->assertEquals(66.6667, $resultado['costo_promedio_despues']);

        $capaCeroCosto = $this->capasDe($empresa, $producto, $bodega)->last();
        $this->assertEquals(0.0, (float) $capaCeroCosto->costo_unitario);
        $this->assertEquals(5.0, (float) $capaCeroCosto->cantidad_disponible);
        $this->assertEquals(InventarioValorizacionCapa::ESTADO_ABIERTA, $capaCeroCosto->estado);
    }

    /**
     * Documenta el comportamiento real de una devolución FIFO (InventarioDevolucionService::
     * registrarMovimientoEntrada reingresa siempre pasando un costo_unitario explícito: el del
     * movimiento de salida original, no el de la capa FIFO vigente al momento de la devolución).
     * El resultado es una capa NUEVA al costo mezclado de la salida original, que además queda al
     * final de la cola FIFO (por fecha_entrada), no se antepone a las capas más nuevas.
     */
    public function test_devolucion_fifo_reingresa_al_costo_del_movimiento_original_no_al_costo_de_la_capa_vigente(): void
    {
        $empresa = $this->crearEmpresa();
        $producto = $this->crearProductoFifo($empresa);
        $bodega = $this->crearBodega($empresa);
        $stock = $this->crearStockVacio($empresa, $producto, $bodega);

        $this->fifo->calcularEntrada($stock, $producto, 10, 100);
        $this->fifo->calcularEntrada($stock->refresh(), $producto, 5, 200);

        // Salida de 12: consume 10@100 + 2@200. Costo mezclado del movimiento de salida original:
        // (10*100 + 2*200) / 12 = 116.6667. Ese es el costo que "recuerda" el movimiento, y el que
        // InventarioDevolucionService reinyecta al confirmar una devolución (no el costo de la
        // capa vigente en ese momento, que sería 200).
        $salida = $this->fifo->calcularSalida($stock->refresh(), $producto, 12);
        $costoMovimientoOriginal = (float) $salida['costo_unitario'];
        $this->assertEqualsWithDelta(116.6667, $costoMovimientoOriginal, 0.0001);

        $devolucion = $this->fifo->calcularEntrada(
            $stock->refresh(),
            $producto,
            4,
            $costoMovimientoOriginal
        );

        $this->assertEquals(116.6667, $devolucion['costo_unitario']);

        $capas = $this->capasDe($empresa, $producto, $bodega);
        $capaDevolucion = $capas->last();
        $this->assertEquals(116.6667, (float) $capaDevolucion->costo_unitario);
        $this->assertEquals(4.0, (float) $capaDevolucion->cantidad_disponible);

        // La capa remanente original (200) sigue intacta y sigue siendo "más antigua" que la
        // capa de devolución recién creada: la devolución NO se antepone en la cola FIFO.
        $capaRemanenteOriginal = $capas->get(1);
        $this->assertEquals(200.0, (float) $capaRemanenteOriginal->costo_unitario);
        $this->assertTrue($capaRemanenteOriginal->fecha_entrada->lessThanOrEqualTo($capaDevolucion->fecha_entrada));
    }

    /**
     * Hallazgo documentado (ver HALLAZGOS-COLATERALES.md): una entrada FIFO SIN costo_unitario
     * explícito no usa el costo de la capa más antigua ni el de la última compra real: usa
     * costo_promedio del stock, que es un promedio ponderado (mecanismo típico de PMP) mezclado
     * entre todas las capas vigentes. Esto puede insertar capas FIFO con un costo que no
     * corresponde a ninguna compra real.
     */
    public function test_entrada_fifo_sin_costo_explicito_usa_el_promedio_ponderado_del_stock(): void
    {
        $empresa = $this->crearEmpresa();
        $producto = $this->crearProductoFifo($empresa);
        $bodega = $this->crearBodega($empresa);
        $stock = $this->crearStockVacio($empresa, $producto, $bodega);

        $this->fifo->calcularEntrada($stock, $producto, 10, 100);
        $this->fifo->calcularEntrada($stock->refresh(), $producto, 5, 200);
        // costo_promedio del stock tras las dos entradas: (1000 + 1000) / 15 = 133.3333.

        $resultado = $this->fifo->calcularEntrada($stock->refresh(), $producto, 5, null);

        // Ni 100 (capa más antigua) ni 200 (última compra real): el promedio ponderado 133.3333.
        $this->assertEquals(133.3333, $resultado['costo_unitario']);

        $capaSinCostoExplicito = $this->capasDe($empresa, $producto, $bodega)->last();
        $this->assertEquals(133.3333, (float) $capaSinCostoExplicito->costo_unitario);
    }

    /**
     * PHPUnit corre en un único proceso, por lo que no se puede ejercer una condición de carrera
     * real entre dos requests concurrentes. Siguiendo el mismo patrón que
     * ActivoFijoConcurrenciaTest (llamadas secuenciales que dependen de lockForUpdate() para no
     * perder actualizaciones), este test ejercita el lockForUpdate() de
     * InventarioMovimientoService::obtenerOCrearStockBloqueado / InventarioValorizacionService::
     * obtenerOCrearStock: dos "solicitudes" que leen-bloquean-actualizan el mismo stock en
     * transacciones separadas deben acumularse correctamente, sin perder ninguna de las dos.
     */
    public function test_lockforupdate_serializa_dos_entradas_secuenciales_sobre_el_mismo_stock(): void
    {
        $empresa = $this->crearEmpresa();
        $producto = $this->crearProductoFifo($empresa);
        $bodega = $this->crearBodega($empresa);
        $stockInicial = $this->crearStockVacio($empresa, $producto, $bodega);

        // "Solicitud A": toma el lock, calcula y libera.
        DB::transaction(function () use ($empresa, $producto, $bodega) {
            $stockBloqueado = StockProducto::query()
                ->where('empresa_id', $empresa->id)
                ->where('producto_id', $producto->id)
                ->where('bodega_id', $bodega->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->fifo->calcularEntrada($stockBloqueado, $producto, 10, 100);
        });

        // "Solicitud B": si el lock de A no hubiese liberado el stock ya actualizado, B leería un
        // stock_actual desactualizado (0) y el resultado final sería incorrecto (15 en vez de 25).
        DB::transaction(function () use ($empresa, $producto, $bodega) {
            $stockBloqueado = StockProducto::query()
                ->where('empresa_id', $empresa->id)
                ->where('producto_id', $producto->id)
                ->where('bodega_id', $bodega->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->fifo->calcularEntrada($stockBloqueado, $producto, 15, 200);
        });

        $stockInicial->refresh();
        $this->assertEquals(25.0, (float) $stockInicial->stock_actual);
        $this->assertEquals(4000.0, (float) $stockInicial->valor_total); // 10*100 + 15*200
        $this->assertCount(2, $this->capasDe($empresa, $producto, $bodega));
    }

    /**
     * Simula una secuencia larga de movimientos con cantidades/costos que no dividen limpio y
     * compara el valor final acumulado (redondeado a 4 decimales en cada paso, como hace
     * producción) contra un cálculo de referencia con precisión completa (bcmath, sin redondear
     * en cada paso), para detectar deriva por acumulación de error de redondeo.
     */
    public function test_secuencia_larga_de_movimientos_fifo_no_deriva_por_redondeo_acumulado(): void
    {
        $empresa = $this->crearEmpresa();
        $producto = $this->crearProductoFifo($empresa);
        $bodega = $this->crearBodega($empresa);
        $stock = $this->crearStockVacio($empresa, $producto, $bodega);

        // [tipo, cantidad, costo_o_null]; cantidades de salida siempre <= stock acumulado.
        $movimientos = [
            ['E', 7, 33.3333],
            ['E', 11, 47.7777],
            ['S', 5, null],
            ['E', 13, 19.9999],
            ['S', 9, null],
            ['E', 3, 101.0101],
            ['S', 4, null],
            ['E', 17, 8.1234],
            ['S', 12, null],
            ['E', 6, 59.5959],
            ['S', 3, null],
            ['E', 9, 14.1414],
            ['S', 8, null],
            ['E', 21, 5.5555],
            ['S', 15, null],
            ['E', 4, 77.7777],
        ];

        // Referencia de precisión completa: capas FIFO propias, consumidas con bcmath (scale 20).
        $capasRef = [];
        $valorRef = '0';
        $stockRef = '0';

        foreach ($movimientos as [$tipoMov, $cantidad, $costo]) {
            if ($tipoMov === 'E') {
                $this->fifo->calcularEntrada($stock->refresh(), $producto, $cantidad, $costo);

                $capasRef[] = ['cantidad' => (string) $cantidad, 'costo' => (string) $costo];
                $valorRef = bcadd($valorRef, bcmul((string) $cantidad, (string) $costo, 20), 20);
                $stockRef = bcadd($stockRef, (string) $cantidad, 20);

                continue;
            }

            $this->fifo->calcularSalida($stock->refresh(), $producto, $cantidad);

            $pendiente = (string) $cantidad;
            foreach ($capasRef as $indice => $capaRef) {
                if (bccomp($pendiente, '0', 20) <= 0) {
                    break;
                }

                $disponible = $capaRef['cantidad'];
                if (bccomp($disponible, '0', 20) <= 0) {
                    continue;
                }

                $consumir = bccomp($pendiente, $disponible, 20) < 0 ? $pendiente : $disponible;
                $valorConsumido = bcmul($consumir, $capaRef['costo'], 20);

                $valorRef = bcsub($valorRef, $valorConsumido, 20);
                $stockRef = bcsub($stockRef, $consumir, 20);
                $capasRef[$indice]['cantidad'] = bcsub($disponible, $consumir, 20);
                $pendiente = bcsub($pendiente, $consumir, 20);
            }
        }

        $stock->refresh();

        $valorRefFloat = (float) $valorRef;
        $stockRefFloat = (float) $stockRef;

        $this->assertEqualsWithDelta($stockRefFloat, (float) $stock->stock_actual, 0.0001);
        $this->assertEqualsWithDelta($valorRefFloat, (float) $stock->valor_total, 0.01);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function capasDe(Empresa $empresa, Producto $producto, Bodega $bodega)
    {
        return InventarioValorizacionCapa::query()
            ->where('empresa_id', $empresa->id)
            ->where('producto_id', $producto->id)
            ->where('bodega_id', $bodega->id)
            ->orderBy('fecha_entrada')
            ->orderBy('id')
            ->get();
    }

    private function crearEmpresa(): Empresa
    {
        return Empresa::create([
            'rut' => $this->rutUnico(),
            'razon_social' => 'Empresa FIFO Test '.uniqid(),
        ]);
    }

    private function crearBodega(Empresa $empresa, array $overrides = []): Bodega
    {
        return Bodega::create(array_merge([
            'empresa_id' => $empresa->id,
            'codigo' => 'BOD-FIFO-'.strtoupper(substr(uniqid(), -6)),
            'nombre' => 'Bodega FIFO Test',
            'direccion' => 'Santiago, Chile',
            'estado' => 'ACTIVA',
        ], $overrides));
    }

    private function crearProductoFifo(Empresa $empresa, array $overrides = []): Producto
    {
        $unidad = $this->obtenerUnidadBase();

        return Producto::create(array_merge([
            'empresa_id' => $empresa->id,
            'sku' => 'PROD-FIFO-'.strtoupper(substr(uniqid(), -8)),
            'nombre' => 'Producto FIFO Test',
            'descripcion' => 'Producto para pruebas de borde de valorización FIFO',
            'tipo_producto' => 'BIEN',
            'unidad_medida_id' => $unidad->id,
            'metodo_valorizacion' => 'FIFO',
            'costo_promedio' => 0,
            'precio_venta_neto' => 1000,
            'afecto_iva' => true,
            'codigo_barra' => '780'.random_int(1000000000, 9999999999),
            'stock_minimo' => 0,
            'bodega_defecto_id' => null,
            'permite_merma' => true,
            'activo' => true,
        ], $overrides));
    }

    private function crearStockVacio(Empresa $empresa, Producto $producto, Bodega $bodega): StockProducto
    {
        return StockProducto::create([
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'stock_actual' => 0,
            'costo_promedio' => 0,
            'valor_total' => 0,
        ]);
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
        return (string) random_int(70000000, 99999999).'-'.random_int(0, 9);
    }
}
