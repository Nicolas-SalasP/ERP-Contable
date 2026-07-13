<?php

namespace Tests\Feature\Inventario;

use App\Domains\Core\Models\Empresa;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\StockProducto;
use App\Domains\Inventario\Models\UnidadMedida;
use App\Domains\Inventario\Services\InventarioValorizacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Casos de borde de PMP (costo promedio ponderado) complementarios a InventarioValorizacionServiceTest,
 * que ya cubre el caso feliz de entrada/salida/traspaso y el recálculo básico del promedio.
 *
 * Cubre: entrada a costo cero, stock que llega a cero y vuelve a entrar (¿arrastra resabio?),
 * ajustes que dejan el stock exactamente en cero, lockForUpdate y precisión decimal acumulada.
 */
class PmpValorizacionEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    private InventarioValorizacionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(InventarioValorizacionService::class);
    }

    public function test_entrada_a_costo_cero_es_aceptada_y_diluye_el_promedio(): void
    {
        $empresa = $this->crearEmpresa();
        $producto = $this->crearProducto($empresa, ['costo_promedio' => 100]);
        $bodega = $this->crearBodega($empresa);
        $stock = $this->crearStock($empresa, $producto, $bodega, 10, 100);

        $resultado = $this->service->calcularEntradaPmp(
            stock: $stock,
            producto: $producto,
            cantidad: 10,
            costoUnitario: 0
        );

        $this->assertSame('0.0000', $resultado['costo_unitario']);
        $this->assertSame('0.0000', $resultado['costo_total']);
        // (1000 + 0) / 20 = 50: la entrada a costo 0 no se rechaza ni advierte, solo diluye.
        $this->assertSame('50.0000', $resultado['costo_promedio_despues']);

        $stock->refresh();
        $this->assertEquals(50.0, (float) $stock->costo_promedio);
    }

    public function test_entrada_con_costo_negativo_es_rechazada(): void
    {
        $empresa = $this->crearEmpresa();
        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);
        $stock = $this->crearStock($empresa, $producto, $bodega, 10, 100);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no puede ser negativo');

        $this->service->calcularEntradaPmp(
            stock: $stock,
            producto: $producto,
            cantidad: 5,
            costoUnitario: -1
        );
    }

    /**
     * Verifica empíricamente (no se asume) qué pasa cuando el stock de una bodega llega a cero y
     * luego vuelve a entrar: el costo_promedio de esa fila de stock SÍ se reinicia a 0 en
     * calcularSalidaPmp (línea: costo_promedio = stockDespues > 0 ? costoSalida : 0.0000), y el
     * costo_promedio consolidado del producto también queda en 0 si esa era la única bodega con
     * stock (se recalcula como SUM(valor_total)/SUM(stock_actual) = 0/0 -> 0).
     *
     * Consecuencia real (ver HALLAZGOS-COLATERALES.md): si la entrada siguiente no informa un
     * costo_unitario explícito, obtenerCostoUnitarioEntrada() no tiene ningún costo de referencia
     * al que recurrir (ni el de la bodega, ni el consolidado del producto, ambos en 0) y la
     * entrada queda valorizada en $0 de forma silenciosa, sin error ni advertencia.
     */
    public function test_stock_que_llega_a_cero_reinicia_el_promedio_sin_arrastrar_resabio(): void
    {
        $empresa = $this->crearEmpresa();
        $producto = $this->crearProducto($empresa, ['costo_promedio' => 500]);
        $bodega = $this->crearBodega($empresa);
        $stock = $this->crearStock($empresa, $producto, $bodega, 10, 500);

        // Agotamos el stock por completo.
        $this->service->calcularSalidaPmp(stock: $stock, producto: $producto, cantidad: 10);

        $stock->refresh();
        $producto->refresh();

        $this->assertEquals(0.0, (float) $stock->stock_actual);
        $this->assertEquals(0.0, (float) $stock->costo_promedio);
        $this->assertEquals(0.0, (float) $stock->valor_total);
        $this->assertEquals(0.0, (float) $producto->costo_promedio);

        // Entrada posterior SIN costo explícito: no hay resabio de los $500 anteriores, pero
        // tampoco hay ningún costo de referencia disponible, así que entra valorizada en $0.
        $resultado = $this->service->calcularEntradaPmp(
            stock: $stock,
            producto: $producto,
            cantidad: 8,
            costoUnitario: null
        );

        $this->assertSame('0.0000', $resultado['costo_unitario']);
        $this->assertSame('0.0000', $resultado['costo_promedio_despues']);

        $stock->refresh();
        $this->assertEquals(8.0, (float) $stock->stock_actual);
        $this->assertEquals(0.0, (float) $stock->valor_total);
    }

    public function test_salida_que_agota_exactamente_el_stock_no_lanza_excepcion(): void
    {
        $empresa = $this->crearEmpresa();
        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);
        $stock = $this->crearStock($empresa, $producto, $bodega, 6, 300);

        $resultado = $this->service->calcularSalidaPmp(stock: $stock, producto: $producto, cantidad: 6);

        $this->assertSame('0.0000', $resultado['stock_despues']);
        $this->assertSame('0.0000', $resultado['valor_despues']);

        $stock->refresh();
        $this->assertEquals(0.0, (float) $stock->stock_actual);
        $this->assertEquals(0.0, (float) $stock->costo_promedio);
    }

    public function test_salida_un_unidad_mas_alla_del_stock_disponible_es_rechazada(): void
    {
        $empresa = $this->crearEmpresa();
        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);
        $stock = $this->crearStock($empresa, $producto, $bodega, 6, 300);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Stock insuficiente');

        $this->service->calcularSalidaPmp(stock: $stock, producto: $producto, cantidad: 6.0001);
    }

    /**
     * Mismo patrón que en el dominio Activos (ActivoFijoConcurrenciaTest): PHPUnit corre en un
     * único proceso, por lo que no se puede forzar una condición de carrera real. Este test
     * ejercita el lockForUpdate() de InventarioValorizacionService::obtenerOCrearStock con dos
     * "solicitudes" secuenciales sobre el mismo stock: si el lock no sirviera para serializar
     * lecturas-actualizaciones, una de las dos perdería su incremento.
     */
    public function test_lockforupdate_serializa_dos_salidas_secuenciales_sin_perder_actualizaciones(): void
    {
        $empresa = $this->crearEmpresa();
        $producto = $this->crearProducto($empresa, ['costo_promedio' => 200]);
        $bodega = $this->crearBodega($empresa);
        $stockInicial = $this->crearStock($empresa, $producto, $bodega, 20, 200);

        DB::transaction(function () use ($empresa, $producto, $bodega) {
            $stockBloqueado = StockProducto::query()
                ->where('empresa_id', $empresa->id)
                ->where('producto_id', $producto->id)
                ->where('bodega_id', $bodega->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->service->calcularSalidaPmp(stock: $stockBloqueado, producto: $producto, cantidad: 5);
        });

        DB::transaction(function () use ($empresa, $producto, $bodega) {
            $stockBloqueado = StockProducto::query()
                ->where('empresa_id', $empresa->id)
                ->where('producto_id', $producto->id)
                ->where('bodega_id', $bodega->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->service->calcularSalidaPmp(stock: $stockBloqueado, producto: $producto, cantidad: 7);
        });

        $stockInicial->refresh();
        // Si alguna de las dos hubiese leído un stock_actual desactualizado, el resultado sería
        // distinto de 20 - 5 - 7 = 8.
        $this->assertEquals(8.0, (float) $stockInicial->stock_actual);
        $this->assertEquals(1600.0, (float) $stockInicial->valor_total); // 8 * 200
    }

    /**
     * Secuencia larga de entradas/salidas con cantidades/costos que no dividen limpio, comparada
     * contra una referencia de precisión completa (bcmath, sin redondear en cada paso) para
     * detectar deriva por acumulación de error de redondeo en el recálculo recursivo del PMP.
     */
    public function test_secuencia_larga_de_movimientos_pmp_no_deriva_por_redondeo_acumulado(): void
    {
        $empresa = $this->crearEmpresa();
        $producto = $this->crearProducto($empresa, ['costo_promedio' => 0]);
        $bodega = $this->crearBodega($empresa);
        $stock = $this->crearStock($empresa, $producto, $bodega, 0, 0);

        // [tipo, cantidad, costo_o_null]; las salidas siempre son <= al stock acumulado.
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
            ['S', 2, null],
            ['E', 8, 33.9999],
            ['S', 6, null],
            ['E', 5, 12.3456],
        ];

        $valorRef = '0';
        $stockRef = '0';
        $costoPromRef = '0';

        foreach ($movimientos as [$tipoMov, $cantidad, $costo]) {
            if ($tipoMov === 'E') {
                $this->service->calcularEntradaPmp(
                    stock: $stock->refresh(),
                    producto: $producto,
                    cantidad: $cantidad,
                    costoUnitario: $costo
                );

                $valorRef = bcadd($valorRef, bcmul((string) $cantidad, (string) $costo, 20), 20);
                $stockRef = bcadd($stockRef, (string) $cantidad, 20);
                $costoPromRef = bccomp($stockRef, '0', 20) > 0 ? bcdiv($valorRef, $stockRef, 20) : '0';

                continue;
            }

            $this->service->calcularSalidaPmp(stock: $stock->refresh(), producto: $producto, cantidad: $cantidad);

            $valorSalidaRef = bcmul((string) $cantidad, $costoPromRef, 20);
            $valorRef = bcsub($valorRef, $valorSalidaRef, 20);
            $stockRef = bcsub($stockRef, (string) $cantidad, 20);
            $costoPromRef = bccomp($stockRef, '0', 20) > 0 ? bcdiv($valorRef, $stockRef, 20) : '0';
        }

        $stock->refresh();

        $this->assertEqualsWithDelta((float) $stockRef, (float) $stock->stock_actual, 0.0001);
        $this->assertEqualsWithDelta((float) $valorRef, (float) $stock->valor_total, 0.01);
        $this->assertEqualsWithDelta((float) $costoPromRef, (float) $stock->costo_promedio, 0.001);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function crearEmpresa(): Empresa
    {
        return Empresa::create([
            'rut' => $this->rutUnico(),
            'razon_social' => 'Empresa PMP Edge Test '.uniqid(),
        ]);
    }

    private function crearBodega(Empresa $empresa, array $overrides = []): Bodega
    {
        return Bodega::create(array_merge([
            'empresa_id' => $empresa->id,
            'codigo' => 'BOD-PMP-'.strtoupper(substr(uniqid(), -6)),
            'nombre' => 'Bodega PMP Edge Test',
            'direccion' => 'Santiago, Chile',
            'estado' => 'ACTIVA',
        ], $overrides));
    }

    private function crearProducto(Empresa $empresa, array $overrides = []): Producto
    {
        $unidad = $this->obtenerUnidadBase();

        return Producto::create(array_merge([
            'empresa_id' => $empresa->id,
            'sku' => 'PROD-PMP-'.strtoupper(substr(uniqid(), -8)),
            'nombre' => 'Producto PMP Edge Test',
            'descripcion' => 'Producto para pruebas de borde de valorización PMP',
            'tipo_producto' => 'BIEN',
            'unidad_medida_id' => $unidad->id,
            'metodo_valorizacion' => 'PMP',
            'costo_promedio' => 100,
            'precio_venta_neto' => 1000,
            'afecto_iva' => true,
            'codigo_barra' => '780'.random_int(1000000000, 9999999999),
            'stock_minimo' => 0,
            'bodega_defecto_id' => null,
            'permite_merma' => true,
            'activo' => true,
        ], $overrides));
    }

    private function crearStock(
        Empresa $empresa,
        Producto $producto,
        Bodega $bodega,
        float $stockActual,
        float $costoPromedio
    ): StockProducto {
        return StockProducto::create([
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'stock_actual' => $stockActual,
            'costo_promedio' => $costoPromedio,
            'valor_total' => $stockActual * $costoPromedio,
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
