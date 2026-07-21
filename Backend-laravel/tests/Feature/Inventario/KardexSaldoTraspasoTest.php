<?php

namespace Tests\Feature\Inventario;

use App\Domains\Core\Models\Empresa;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\MovimientoInventario;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\StockProducto;
use App\Domains\Inventario\Models\UnidadMedida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaInventarioTrait;
use Tests\TestCase;

class KardexSaldoTraspasoTest extends TestCase
{
    use PreparaInventarioTrait;
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararUsuariosInventarioDemo();
    }

    /**
     * Traspaso completo (bodega origen queda en 0): el Kardex de la bodega DESTINO debe
     * mostrar el saldo real de destino, no el snapshot de origen (que quedo en 0).
     */
    public function test_kardex_de_bodega_destino_muestra_su_propio_saldo_no_el_de_origen(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosMovimientoCompleto());

        $producto = $this->crearProducto($empresa);
        $bodegaOrigen = $this->crearBodega($empresa, ['codigo' => 'BOD-ORI']);
        $bodegaDestino = $this->crearBodega($empresa, ['codigo' => 'BOD-DES']);

        $this->crearStock($empresa, $producto, $bodegaOrigen, 10, 250);
        $this->crearStock($empresa, $producto, $bodegaDestino, 0, 0);

        Sanctum::actingAs($usuario);

        $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_TRASPASO,
            'producto_id' => $producto->id,
            'bodega_origen_id' => $bodegaOrigen->id,
            'bodega_destino_id' => $bodegaDestino->id,
            'cantidad' => 10,
            'referencia' => 'TRASPASO-TOTAL-001',
            'motivo' => MovimientoInventario::MOTIVO_TRASPASO_BODEGA,
        ])->assertCreated();

        $kardexDestino = $this->getJson(
            "/api/inventario/productos/{$producto->id}/kardex?bodega_id={$bodegaDestino->id}"
        );
        $kardexDestino->assertOk();
        $this->assertEquals(10.0, (float) $kardexDestino->json('data.0.saldo'));

        $kardexOrigen = $this->getJson(
            "/api/inventario/productos/{$producto->id}/kardex?bodega_id={$bodegaOrigen->id}"
        );
        $kardexOrigen->assertOk();
        $this->assertEquals(0.0, (float) $kardexOrigen->json('data.0.saldo'));
    }

    private function permisosMovimientoCompleto(): array
    {
        return [
            'inventario.productos.ver',
            'inventario.bodegas.ver',
            'inventario.movimientos.ver',
            'inventario.movimientos.traspaso',
            'inventario.kardex.ver',
        ];
    }

    private function crearBodega(Empresa $empresa, array $overrides = []): Bodega
    {
        return Bodega::create(array_merge([
            'empresa_id' => $empresa->id,
            'codigo' => 'BOD-'.strtoupper(substr(uniqid(), -6)),
            'nombre' => 'Bodega Test',
            'direccion' => 'Santiago, Chile',
            'estado' => 'ACTIVA',
        ], $overrides));
    }

    private function crearProducto(Empresa $empresa, array $overrides = []): Producto
    {
        $unidad = UnidadMedida::firstOrCreate(
            ['codigo' => 'UN'],
            ['nombre' => 'Unidad', 'permite_decimal' => false, 'activo' => true]
        );

        return Producto::create(array_merge([
            'empresa_id' => $empresa->id,
            'sku' => 'PROD-'.strtoupper(substr(uniqid(), -8)),
            'nombre' => 'Producto Kardex Test',
            'descripcion' => 'Producto para pruebas de Kardex',
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
}
