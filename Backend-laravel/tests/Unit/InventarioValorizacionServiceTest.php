<?php

namespace Tests\Unit;

use App\Domains\Core\Models\Empresa;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\StockProducto;
use App\Domains\Inventario\Models\UnidadMedida;
use App\Domains\Inventario\Services\InventarioValorizacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class InventarioValorizacionServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventarioValorizacionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(InventarioValorizacionService::class);
    }

    public function test_entrada_recalcula_pmp_por_bodega(): void
    {
        $empresa = $this->crearEmpresa();
        $producto = $this->crearProducto($empresa, [
            'costo_promedio' => 1000,
        ]);
        $bodega = $this->crearBodega($empresa);

        $stock = $this->crearStock($empresa, $producto, $bodega, 10, 1000);

        $resultado = $this->service->calcularEntradaPmp(
            stock: $stock,
            producto: $producto,
            cantidad: 5,
            costoUnitario: 1200
        );

        $stock->refresh();
        $producto->refresh();

        $this->assertSame('10.0000', $resultado['stock_antes']);
        $this->assertSame('15.0000', $resultado['stock_despues']);
        $this->assertSame('1000.0000', $resultado['costo_promedio_antes']);
        $this->assertSame('1066.6667', $resultado['costo_promedio_despues']);
        $this->assertSame('10000.0000', $resultado['valor_antes']);
        $this->assertSame('16000.0000', $resultado['valor_despues']);
        $this->assertSame('1200.0000', $resultado['costo_unitario']);
        $this->assertSame('6000.0000', $resultado['costo_total']);

        $this->assertEquals(15.0, (float) $stock->stock_actual);
        $this->assertEquals(1066.6667, (float) $stock->costo_promedio);
        $this->assertEquals(16000.0, (float) $stock->valor_total);

        $this->assertEquals(1066.6667, (float) $producto->costo_promedio);
    }

    public function test_salida_mantiene_pmp_y_descuenta_valor_total(): void
    {
        $empresa = $this->crearEmpresa();
        $producto = $this->crearProducto($empresa, [
            'costo_promedio' => 500,
        ]);
        $bodega = $this->crearBodega($empresa);

        $stock = $this->crearStock($empresa, $producto, $bodega, 10, 500);

        $resultado = $this->service->calcularSalidaPmp(
            stock: $stock,
            producto: $producto,
            cantidad: 3
        );

        $stock->refresh();
        $producto->refresh();

        $this->assertSame('10.0000', $resultado['stock_antes']);
        $this->assertSame('7.0000', $resultado['stock_despues']);
        $this->assertSame('500.0000', $resultado['costo_unitario']);
        $this->assertSame('1500.0000', $resultado['costo_total']);
        $this->assertSame('5000.0000', $resultado['valor_antes']);
        $this->assertSame('3500.0000', $resultado['valor_despues']);

        $this->assertEquals(7.0, (float) $stock->stock_actual);
        $this->assertEquals(500.0, (float) $stock->costo_promedio);
        $this->assertEquals(3500.0, (float) $stock->valor_total);

        $this->assertEquals(500.0, (float) $producto->costo_promedio);
    }

    public function test_salida_con_stock_insuficiente_lanza_excepcion(): void
    {
        $empresa = $this->crearEmpresa();
        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        $stock = $this->crearStock($empresa, $producto, $bodega, 2, 500);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Stock insuficiente');

        $this->service->calcularSalidaPmp(
            stock: $stock,
            producto: $producto,
            cantidad: 5
        );
    }

    public function test_traspaso_transfiere_valor_usando_pmp_de_origen(): void
    {
        $empresa = $this->crearEmpresa();
        $producto = $this->crearProducto($empresa);
        $bodegaOrigen = $this->crearBodega($empresa, [
            'codigo' => 'BOD-ORI',
            'nombre' => 'Bodega Origen',
        ]);
        $bodegaDestino = $this->crearBodega($empresa, [
            'codigo' => 'BOD-DES',
            'nombre' => 'Bodega Destino',
        ]);

        $stockOrigen = $this->crearStock($empresa, $producto, $bodegaOrigen, 10, 250);
        $stockDestino = $this->crearStock($empresa, $producto, $bodegaDestino, 2, 100);

        $resultado = $this->service->calcularTraspasoPmp(
            stockOrigen: $stockOrigen,
            stockDestino: $stockDestino,
            producto: $producto,
            cantidad: 4
        );

        $stockOrigen->refresh();
        $stockDestino->refresh();
        $producto->refresh();

        $this->assertSame('250.0000', $resultado['costo_unitario']);
        $this->assertSame('1000.0000', $resultado['costo_total']);

        $this->assertSame('10.0000', $resultado['origen']['stock_antes']);
        $this->assertSame('6.0000', $resultado['origen']['stock_despues']);
        $this->assertSame('2500.0000', $resultado['origen']['valor_antes']);
        $this->assertSame('1500.0000', $resultado['origen']['valor_despues']);

        $this->assertSame('2.0000', $resultado['destino']['stock_antes']);
        $this->assertSame('6.0000', $resultado['destino']['stock_despues']);
        $this->assertSame('200.0000', $resultado['destino']['valor_antes']);
        $this->assertSame('1200.0000', $resultado['destino']['valor_despues']);
        $this->assertSame('200.0000', $resultado['destino']['costo_promedio_despues']);

        $this->assertEquals(6.0, (float) $stockOrigen->stock_actual);
        $this->assertEquals(250.0, (float) $stockOrigen->costo_promedio);
        $this->assertEquals(1500.0, (float) $stockOrigen->valor_total);

        $this->assertEquals(6.0, (float) $stockDestino->stock_actual);
        $this->assertEquals(200.0, (float) $stockDestino->costo_promedio);
        $this->assertEquals(1200.0, (float) $stockDestino->valor_total);

        $this->assertEquals(225.0, (float) $producto->costo_promedio);
    }

    public function test_actualiza_costo_promedio_consolidado_del_producto(): void
    {
        $empresa = $this->crearEmpresa();
        $producto = $this->crearProducto($empresa, [
            'costo_promedio' => 0,
        ]);

        $bodegaA = $this->crearBodega($empresa, [
            'codigo' => 'BOD-A',
        ]);

        $bodegaB = $this->crearBodega($empresa, [
            'codigo' => 'BOD-B',
        ]);

        $this->crearStock($empresa, $producto, $bodegaA, 10, 1000);
        $this->crearStock($empresa, $producto, $bodegaB, 5, 1200);

        $costoPromedio = $this->service->actualizarCostoPromedioProducto(
            empresaId: $empresa->id,
            productoId: $producto->id
        );

        $producto->refresh();

        $this->assertEquals(1066.6667, $costoPromedio);
        $this->assertEquals(1066.6667, (float) $producto->costo_promedio);
    }

    public function test_listar_y_resumir_valorizacion_respeta_empresa_y_filtros(): void
    {
        $empresa = $this->crearEmpresa();
        $otraEmpresa = $this->crearEmpresa();

        $producto = $this->crearProducto($empresa, [
            'sku' => 'PROD-VAL-001',
            'nombre' => 'Producto Valorizado',
        ]);

        $productoAjeno = $this->crearProducto($otraEmpresa, [
            'sku' => 'PROD-AJENO-VAL',
            'nombre' => 'Producto Ajeno Valorizado',
        ]);

        $bodega = $this->crearBodega($empresa, [
            'codigo' => 'BOD-VAL',
            'nombre' => 'Bodega Valorizada',
        ]);

        $bodegaAjena = $this->crearBodega($otraEmpresa, [
            'codigo' => 'BOD-AJENA-VAL',
            'nombre' => 'Bodega Ajena Valorizada',
        ]);

        $this->crearStock($empresa, $producto, $bodega, 10, 1000);
        $this->crearStock($otraEmpresa, $productoAjeno, $bodegaAjena, 99, 999);

        $paginador = $this->service->listarValorizacion($empresa->id, [
            'search' => 'Valorizado',
            'per_page' => 15,
        ]);

        $resumen = $this->service->resumenValorizacion($empresa->id, [
            'search' => 'Valorizado',
        ]);

        $this->assertEquals(1, $paginador->total());
        $this->assertCount(1, $paginador->items());

        $item = $paginador->items()[0];

        $this->assertSame($producto->id, $item['producto']['id']);
        $this->assertSame('PROD-VAL-001', $item['producto']['sku']);
        $this->assertSame($bodega->id, $item['bodega']['id']);
        $this->assertSame('10.0000', $item['stock_actual']);
        $this->assertSame('1000.0000', $item['costo_promedio']);
        $this->assertSame('10000.0000', $item['valor_total']);

        $this->assertSame('10.0000', $resumen['stock_total']);
        $this->assertSame('10000.0000', $resumen['valor_total']);
        $this->assertSame('1000.0000', $resumen['costo_promedio_global']);
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
            'razon_social' => 'Empresa Valorizacion ' . uniqid(),
        ]);
    }

    private function crearBodega(Empresa $empresa, array $overrides = []): Bodega
    {
        return Bodega::create(array_merge([
            'empresa_id' => $empresa->id,
            'codigo' => 'BOD-' . strtoupper(substr(uniqid(), -6)),
            'nombre' => 'Bodega Valorizacion Test',
            'direccion' => 'Santiago, Chile',
            'estado' => 'ACTIVA',
        ], $overrides));
    }

    private function crearProducto(Empresa $empresa, array $overrides = []): Producto
    {
        $unidad = $this->obtenerUnidadBase();

        return Producto::create(array_merge([
            'empresa_id' => $empresa->id,
            'sku' => 'PROD-' . strtoupper(substr(uniqid(), -8)),
            'nombre' => 'Producto Valorizacion Test',
            'descripcion' => 'Producto para pruebas unitarias de valorización',
            'tipo_producto' => 'BIEN',
            'unidad_medida_id' => $unidad->id,
            'metodo_valorizacion' => 'PMP',
            'costo_promedio' => 100,
            'precio_venta_neto' => 1000,
            'afecto_iva' => true,
            'codigo_barra' => '780' . random_int(1000000000, 9999999999),
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
        return (string) random_int(70000000, 99999999) . '-' . random_int(0, 9);
    }
}