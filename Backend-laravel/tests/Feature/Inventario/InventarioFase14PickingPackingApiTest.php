<?php

namespace Tests\Feature\Inventario;

use App\Domains\Core\Models\Empresa;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\InventarioPackingOrden;
use App\Domains\Inventario\Models\InventarioPickingAsignacion;
use App\Domains\Inventario\Models\InventarioPickingOrden;
use App\Domains\Inventario\Models\InventarioUbicacion;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\StockUbicacionInventario;
use App\Domains\Inventario\Models\UnidadMedida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaInventarioTrait;
use Tests\TestCase;

class InventarioFase14PickingPackingApiTest extends TestCase
{
    use RefreshDatabase;
    use PreparaInventarioTrait;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararUsuariosInventarioDemo();
    }

    public function test_crea_asigna_inicia_y_confirma_picking_completo(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosFase14());
        [$producto, $bodega, $ubicacion] = $this->crearStockDisponible($empresa, 10);
        Sanctum::actingAs($usuario);

        $ordenId = $this->crearOrdenPicking($producto, $bodega, 4)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->json('data.id');

        $this->postJson("/api/inventario/picking/{$ordenId}/asignar")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.estado', InventarioPickingOrden::ESTADO_PENDIENTE)
            ->assertJsonPath('data.detalles.0.ubicacion_origen_id', $ubicacion->id)
            ->assertJsonPath('data.detalles.0.cantidad_asignada', '4.0000');

        $stockReservado = StockUbicacionInventario::where('ubicacion_id', $ubicacion->id)->firstOrFail();
        $this->assertEquals(4.0, (float) $stockReservado->stock_reservado);
        $this->assertEquals(10.0, (float) $stockReservado->stock_actual);

        $this->postJson("/api/inventario/picking/{$ordenId}/iniciar")
            ->assertOk()
            ->assertJsonPath('data.estado', InventarioPickingOrden::ESTADO_EN_PREPARACION);

        $this->postJson("/api/inventario/picking/{$ordenId}/confirmar")
            ->assertOk()
            ->assertJsonPath('data.estado', InventarioPickingOrden::ESTADO_PICKING_COMPLETO)
            ->assertJsonPath('data.detalles.0.estado', 'COMPLETO');

        $stockDespues = StockUbicacionInventario::where('ubicacion_id', $ubicacion->id)->firstOrFail();
        $this->assertEquals(4.0, (float) $stockDespues->stock_reservado, 'Picking compromete disponibilidad, pero no descuenta stock físico.');
        $this->assertEquals(10.0, (float) $stockDespues->stock_actual);
    }

    public function test_asigna_picking_multiubicacion_cuando_stock_esta_fragmentado(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosFase14());
        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);
        $ubicacionA = $this->crearUbicacion($empresa, $bodega, ['codigo' => 'A1-F14']);
        $ubicacionB = $this->crearUbicacion($empresa, $bodega, ['codigo' => 'B2-F14']);
        $ubicacionC = $this->crearUbicacion($empresa, $bodega, ['codigo' => 'C3-F14']);
        Sanctum::actingAs($usuario);

        $this->registrarEntradaUbicacion($producto, $bodega, $ubicacionA, 4, StockUbicacionInventario::ESTADO_DISPONIBLE);
        $this->registrarEntradaUbicacion($producto, $bodega, $ubicacionB, 3, StockUbicacionInventario::ESTADO_DISPONIBLE);
        $this->registrarEntradaUbicacion($producto, $bodega, $ubicacionC, 3, StockUbicacionInventario::ESTADO_DISPONIBLE);

        $ordenId = $this->crearOrdenPicking($producto, $bodega, 10)->json('data.id');

        $this->postJson("/api/inventario/picking/{$ordenId}/asignar")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.detalles.0.cantidad_asignada', '10.0000')
            ->assertJsonCount(3, 'data.detalles.0.asignaciones');

        $this->assertEquals(3, InventarioPickingAsignacion::where('picking_orden_id', $ordenId)->count());
        $this->assertEquals(4.0, (float) StockUbicacionInventario::where('ubicacion_id', $ubicacionA->id)->firstOrFail()->stock_reservado);
        $this->assertEquals(3.0, (float) StockUbicacionInventario::where('ubicacion_id', $ubicacionB->id)->firstOrFail()->stock_reservado);
        $this->assertEquals(3.0, (float) StockUbicacionInventario::where('ubicacion_id', $ubicacionC->id)->firstOrFail()->stock_reservado);
    }

    public function test_confirma_picking_parcial_con_diferencias(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosFase14());
        [$producto, $bodega] = $this->crearStockDisponible($empresa, 10);
        Sanctum::actingAs($usuario);

        $ordenId = $this->crearOrdenPicking($producto, $bodega, 6)->json('data.id');
        $orden = $this->postJson("/api/inventario/picking/{$ordenId}/asignar")->json('data');
        $this->postJson("/api/inventario/picking/{$ordenId}/iniciar")->assertOk();

        $detalleId = $orden['detalles'][0]['id'];
        $this->postJson("/api/inventario/picking/{$ordenId}/confirmar", [
            'detalles' => [[
                'id' => $detalleId,
                'cantidad_pickeada' => 3,
            ]],
        ])->assertOk()
            ->assertJsonPath('data.estado', InventarioPickingOrden::ESTADO_CON_DIFERENCIAS)
            ->assertJsonPath('data.detalles.0.estado', 'PARCIAL')
            ->assertJsonPath('data.detalles.0.cantidad_faltante', '3.0000');
    }

    public function test_rechaza_picking_sobre_stock_en_cuarentena(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosFase14());
        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);
        $ubicacion = $this->crearUbicacion($empresa, $bodega, ['codigo' => 'CUAR-F14']);
        Sanctum::actingAs($usuario);

        $this->registrarEntradaUbicacion($producto, $bodega, $ubicacion, 5, StockUbicacionInventario::ESTADO_CUARENTENA);

        $ordenId = $this->crearOrdenPicking($producto, $bodega, 1)->json('data.id');

        $this->postJson("/api/inventario/picking/{$ordenId}/asignar")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_impide_doble_confirmacion_de_picking(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosFase14());
        [$producto, $bodega] = $this->crearStockDisponible($empresa, 8);
        Sanctum::actingAs($usuario);

        $ordenId = $this->crearOrdenPicking($producto, $bodega, 2)->json('data.id');
        $this->postJson("/api/inventario/picking/{$ordenId}/asignar")->assertOk();
        $this->postJson("/api/inventario/picking/{$ordenId}/iniciar")->assertOk();
        $this->postJson("/api/inventario/picking/{$ordenId}/confirmar")->assertOk();

        $this->postJson("/api/inventario/picking/{$ordenId}/confirmar")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_packing_desde_picking_completo_y_confirma_empaque(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosFase14());
        [$producto, $bodega] = $this->crearStockDisponible($empresa, 12);
        Sanctum::actingAs($usuario);

        $pickingId = $this->crearPickingCompleto($producto, $bodega, 5);

        $packingId = $this->postJson('/api/inventario/packing', [
            'picking_orden_id' => $pickingId,
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.estado', InventarioPackingOrden::ESTADO_PENDIENTE)
            ->assertJsonPath('data.detalles.0.cantidad_pickeada', '5.0000')
            ->json('data.id');

        $this->postJson("/api/inventario/packing/{$packingId}/iniciar")
            ->assertOk()
            ->assertJsonPath('data.estado', InventarioPackingOrden::ESTADO_EN_EMPAQUE);

        $this->postJson("/api/inventario/packing/{$packingId}/confirmar")
            ->assertOk()
            ->assertJsonPath('data.estado', InventarioPackingOrden::ESTADO_EMPACADO)
            ->assertJsonPath('data.detalles.0.estado', 'EMPACADO');
    }

    public function test_rechaza_packing_desde_picking_cancelado(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosFase14());
        [$producto, $bodega] = $this->crearStockDisponible($empresa, 5);
        Sanctum::actingAs($usuario);

        $pickingId = $this->crearOrdenPicking($producto, $bodega, 2)->json('data.id');
        $this->postJson("/api/inventario/picking/{$pickingId}/cancelar")->assertOk();

        $this->postJson('/api/inventario/packing', [
            'picking_orden_id' => $pickingId,
        ])->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_impide_empacar_mas_de_lo_pickeado(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosFase14());
        [$producto, $bodega] = $this->crearStockDisponible($empresa, 10);
        Sanctum::actingAs($usuario);

        $pickingId = $this->crearPickingCompleto($producto, $bodega, 3);
        $packing = $this->postJson('/api/inventario/packing', ['picking_orden_id' => $pickingId])->json('data');
        $this->postJson("/api/inventario/packing/{$packing['id']}/iniciar")->assertOk();

        $this->postJson("/api/inventario/packing/{$packing['id']}/confirmar", [
            'detalles' => [[
                'id' => $packing['detalles'][0]['id'],
                'cantidad_empacada' => 4,
            ]],
        ])->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_picking_respeta_multiempresa(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosFase14());
        $otraEmpresa = Empresa::create([
            'rut' => (string) random_int(70000000, 99999999) . '-' . random_int(0, 9),
            'razon_social' => 'Empresa Ajena F14',
        ]);
        $productoAjeno = $this->crearProducto($otraEmpresa);
        $bodega = $this->crearBodega($empresa);
        Sanctum::actingAs($usuario);

        $this->postJson('/api/inventario/picking', [
            'bodega_id' => $bodega->id,
            'detalles' => [[
                'producto_id' => $productoAjeno->id,
                'cantidad' => 1,
            ]],
        ])->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    private function permisosFase14(): array
    {
        return $this->permisosInventarioOperador();
    }

    private function crearOrdenPicking(Producto $producto, Bodega $bodega, float $cantidad)
    {
        return $this->postJson('/api/inventario/picking', [
            'bodega_id' => $bodega->id,
            'prioridad' => 'NORMAL',
            'referencia' => 'F14-TEST',
            'detalles' => [[
                'producto_id' => $producto->id,
                'cantidad' => $cantidad,
            ]],
        ]);
    }

    private function crearPickingCompleto(Producto $producto, Bodega $bodega, float $cantidad): int
    {
        $pickingId = $this->crearOrdenPicking($producto, $bodega, $cantidad)->json('data.id');
        $this->postJson("/api/inventario/picking/{$pickingId}/asignar")->assertOk();
        $this->postJson("/api/inventario/picking/{$pickingId}/iniciar")->assertOk();
        $this->postJson("/api/inventario/picking/{$pickingId}/confirmar")->assertOk();

        return (int) $pickingId;
    }

    private function crearStockDisponible(Empresa $empresa, float $cantidad): array
    {
        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);
        $ubicacion = $this->crearUbicacion($empresa, $bodega);

        $this->registrarEntradaUbicacion($producto, $bodega, $ubicacion, $cantidad, StockUbicacionInventario::ESTADO_DISPONIBLE);

        return [$producto, $bodega, $ubicacion];
    }

    private function registrarEntradaUbicacion(Producto $producto, Bodega $bodega, InventarioUbicacion $ubicacion, float $cantidad, string $estado): void
    {
        StockUbicacionInventario::create([
            'empresa_id' => $producto->empresa_id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'ubicacion_id' => $ubicacion->id,
            'lote_id' => null,
            'lote_key' => 0,
            'stock_actual' => $cantidad,
            'stock_reservado' => 0,
            'stock_bloqueado' => $estado === StockUbicacionInventario::ESTADO_BLOQUEADO ? $cantidad : 0,
            'stock_cuarentena' => $estado === StockUbicacionInventario::ESTADO_CUARENTENA ? $cantidad : 0,
            'stock_en_transito' => in_array($estado, [
                StockUbicacionInventario::ESTADO_EN_RECEPCION,
                StockUbicacionInventario::ESTADO_EN_PUTAWAY,
                StockUbicacionInventario::ESTADO_EN_TRANSITO_INTERNO,
            ], true) ? $cantidad : 0,
        ]);
    }

    private function crearBodega(Empresa $empresa, array $overrides = []): Bodega
    {
        return Bodega::create(array_merge([
            'empresa_id' => $empresa->id,
            'codigo' => 'BOD-F14-' . strtoupper(substr(uniqid(), -5)),
            'nombre' => 'Bodega Fase 14',
            'direccion' => 'Santiago, Chile',
            'estado' => 'ACTIVA',
        ], $overrides));
    }

    private function crearUbicacion(Empresa $empresa, Bodega $bodega, array $overrides = []): InventarioUbicacion
    {
        return InventarioUbicacion::create(array_merge([
            'empresa_id' => $empresa->id,
            'bodega_id' => $bodega->id,
            'codigo' => 'UBI-F14-' . strtoupper(substr(uniqid(), -5)),
            'nombre' => 'Ubicación Picking F14',
            'tipo' => InventarioUbicacion::TIPO_UBICACION,
            'activo' => true,
        ], $overrides));
    }

    private function crearProducto(Empresa $empresa, array $overrides = []): Producto
    {
        $unidad = UnidadMedida::firstOrCreate(
            ['codigo' => 'UN'],
            [
                'nombre' => 'Unidad',
                'permite_decimal' => false,
                'activo' => true,
            ]
        );

        return Producto::create(array_merge([
            'empresa_id' => $empresa->id,
            'sku' => 'F14-' . strtoupper(substr(uniqid(), -8)),
            'nombre' => 'Producto Fase 14',
            'descripcion' => 'Producto para picking y packing',
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
            'maneja_lotes' => false,
            'activo' => true,
        ], $overrides));
    }
}
