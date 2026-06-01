<?php

namespace Tests\Feature\Inventario;

use App\Domains\Core\Models\Empresa;
use App\Domains\Inventario\Jobs\CalcularAlertasInventarioJob;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\InventarioAlertaEstado;
use App\Domains\Inventario\Models\InventarioValorizacionCapa;
use App\Domains\Inventario\Models\LoteInventario;
use App\Domains\Inventario\Models\MovimientoInventario;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\ReglaReposicion;
use App\Domains\Inventario\Models\StockLoteInventario;
use App\Domains\Inventario\Models\StockProducto;
use App\Domains\Inventario\Models\UnidadMedida;
use App\Domains\Inventario\Services\InventarioAlertaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaInventarioTrait;
use Tests\TestCase;

class InventarioEnterpriseHardeningTest extends TestCase
{
    use RefreshDatabase;
    use PreparaInventarioTrait;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararUsuariosInventarioDemo();
    }

    public function test_job_persiste_alertas_y_dashboard_lee_alertas_precalculadas(): void
    {
        Event::fake();

        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosInventarioOperador());

        $producto = $this->crearProducto($empresa, [
            'sku' => 'SKU-ALERTA-EMP',
            'stock_minimo' => 5,
        ]);
        $bodega = $this->crearBodega($empresa, ['codigo' => 'BOD-ALERTA-EMP']);

        StockProducto::create([
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'stock_actual' => 3,
            'costo_promedio' => 100,
            'valor_total' => 300,
        ]);

        ReglaReposicion::create([
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'stock_minimo' => 5,
            'stock_objetivo' => 20,
            'dias_alerta_vencimiento' => 30,
            'activo' => true,
        ]);

        (new CalcularAlertasInventarioJob($empresa->id))
            ->handle(app(InventarioAlertaService::class));

        $this->assertDatabaseHas('inventario_alertas_estado', [
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'tipo' => 'STOCK_BAJO',
        ]);

        $this->assertDatabaseHas('inventario_alertas_estado', [
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'tipo' => 'REPOSICION_SUGERIDA',
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->getJson('/api/inventario/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertGreaterThanOrEqual(2, (int) $response->json('data.resumen.alertas_total'));
        $this->assertNotEmpty($response->json('data.sugerencias_reposicion'));
    }

    public function test_no_permite_salida_desde_lote_vencido(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosInventarioOperador());

        $producto = $this->crearProducto($empresa, [
            'sku' => 'SKU-LOTE-VENCIDO',
            'maneja_lotes' => true,
            'requiere_fecha_vencimiento' => true,
        ]);
        $bodega = $this->crearBodega($empresa, ['codigo' => 'BOD-LOTE-VENCIDO']);
        $lote = $this->crearLote($empresa, $producto, [
            'codigo_lote' => 'LOT-VENCIDO-EMP',
            'fecha_vencimiento' => now()->subDay()->toDateString(),
        ]);

        $this->crearStockConLote($empresa, $producto, $bodega, $lote, 10, 100);

        Sanctum::actingAs($usuario);

        $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_SALIDA,
            'producto_id' => $producto->id,
            'bodega_origen_id' => $bodega->id,
            'cantidad' => 1,
            'lote_id' => $lote->id,
            'motivo' => MovimientoInventario::MOTIVO_EGRESO_MANUAL,
        ])->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('inventario_movimientos', [
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'tipo' => MovimientoInventario::TIPO_SALIDA,
        ]);
    }

    public function test_no_permite_salida_desde_lote_en_cuarentena(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosInventarioOperador());

        $producto = $this->crearProducto($empresa, [
            'sku' => 'SKU-LOTE-CUARENTENA',
            'maneja_lotes' => true,
            'requiere_fecha_vencimiento' => true,
        ]);
        $bodega = $this->crearBodega($empresa, ['codigo' => 'BOD-LOTE-CUARENTENA']);
        $lote = $this->crearLote($empresa, $producto, [
            'codigo_lote' => 'LOT-CUARENTENA-EMP',
            'fecha_vencimiento' => now()->addMonth()->toDateString(),
            'estado_operativo' => LoteInventario::ESTADO_CUARENTENA,
        ]);

        $this->crearStockConLote($empresa, $producto, $bodega, $lote, 10, 100);

        Sanctum::actingAs($usuario);

        $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_SALIDA,
            'producto_id' => $producto->id,
            'bodega_origen_id' => $bodega->id,
            'cantidad' => 1,
            'lote_id' => $lote->id,
            'motivo' => MovimientoInventario::MOTIVO_EGRESO_MANUAL,
        ])->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_producto_fifo_consume_capas_en_orden_de_entrada(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosInventarioOperador());

        $producto = $this->crearProducto($empresa, [
            'sku' => 'SKU-FIFO-EMP',
            'metodo_valorizacion' => 'FIFO',
        ]);
        $bodega = $this->crearBodega($empresa, ['codigo' => 'BOD-FIFO-EMP']);

        Sanctum::actingAs($usuario);

        $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodega->id,
            'cantidad' => 10,
            'costo_unitario' => 100,
            'fecha_movimiento' => now()->subDays(2)->toDateString(),
            'referencia' => 'FIFO-ENT-1',
            'motivo' => MovimientoInventario::MOTIVO_INGRESO_MANUAL,
        ])->assertCreated();

        $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodega->id,
            'cantidad' => 5,
            'costo_unitario' => 200,
            'fecha_movimiento' => now()->subDay()->toDateString(),
            'referencia' => 'FIFO-ENT-2',
            'motivo' => MovimientoInventario::MOTIVO_INGRESO_MANUAL,
        ])->assertCreated();

        $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_SALIDA,
            'producto_id' => $producto->id,
            'bodega_origen_id' => $bodega->id,
            'cantidad' => 12,
            'referencia' => 'FIFO-SAL-1',
            'motivo' => MovimientoInventario::MOTIVO_EGRESO_MANUAL,
        ])->assertCreated();

        $capas = InventarioValorizacionCapa::query()
            ->where('empresa_id', $empresa->id)
            ->where('producto_id', $producto->id)
            ->where('bodega_id', $bodega->id)
            ->orderBy('fecha_entrada')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $capas);
        $this->assertEquals(0.0, (float) $capas[0]->cantidad_disponible);
        $this->assertEquals(InventarioValorizacionCapa::ESTADO_CONSUMIDA, $capas[0]->estado);
        $this->assertEquals(3.0, (float) $capas[1]->cantidad_disponible);
        $this->assertEquals(600.0, (float) $capas[1]->valor_disponible);

        $stock = StockProducto::query()
            ->where('empresa_id', $empresa->id)
            ->where('producto_id', $producto->id)
            ->where('bodega_id', $bodega->id)
            ->firstOrFail();

        $this->assertEquals(3.0, (float) $stock->stock_actual);
        $this->assertEquals(600.0, (float) $stock->valor_total);
        $this->assertEquals(200.0, (float) $stock->costo_promedio);
    }

    private function crearProducto(Empresa $empresa, array $overrides = []): Producto
    {
        $unidad = UnidadMedida::query()->firstOrFail();

        return Producto::create(array_merge([
            'empresa_id' => $empresa->id,
            'sku' => 'SKU-' . uniqid(),
            'nombre' => 'Producto enterprise test',
            'descripcion' => 'Producto para pruebas enterprise de inventario.',
            'tipo_producto' => 'BIEN',
            'unidad_medida_id' => $unidad->id,
            'metodo_valorizacion' => 'PMP',
            'costo_promedio' => 0,
            'precio_venta_neto' => 1000,
            'afecto_iva' => true,
            'stock_minimo' => 0,
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
            'codigo' => 'BOD-' . uniqid(),
            'nombre' => 'Bodega enterprise test',
            'direccion' => 'Dirección demo',
            'estado' => 'ACTIVA',
        ], $overrides));
    }

    private function crearLote(Empresa $empresa, Producto $producto, array $overrides = []): LoteInventario
    {
        return LoteInventario::create(array_merge([
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'codigo_lote' => 'LOT-' . uniqid(),
            'fecha_fabricacion' => now()->subMonth()->toDateString(),
            'fecha_vencimiento' => now()->addMonth()->toDateString(),
            'activo' => true,
            'estado_operativo' => LoteInventario::ESTADO_DISPONIBLE,
        ], $overrides));
    }

    private function crearStockConLote(
        Empresa $empresa,
        Producto $producto,
        Bodega $bodega,
        LoteInventario $lote,
        float $cantidad,
        float $costoUnitario
    ): void {
        StockProducto::create([
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'stock_actual' => $cantidad,
            'costo_promedio' => $costoUnitario,
            'valor_total' => $cantidad * $costoUnitario,
        ]);

        StockLoteInventario::create([
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'lote_id' => $lote->id,
            'stock_actual' => $cantidad,
        ]);
    }
}
