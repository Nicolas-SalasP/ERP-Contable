<?php

namespace Tests\Feature\Inventario;

use App\Domains\Core\Models\Empresa;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\InventarioDespachoDetalle;
use App\Domains\Inventario\Models\InventarioDespachoOrden;
use App\Domains\Inventario\Models\InventarioPackingOrden;
use App\Domains\Inventario\Models\InventarioPickingOrden;
use App\Domains\Inventario\Models\InventarioUbicacion;
use App\Domains\Inventario\Models\MovimientoInventario;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\ReservaInventario;
use App\Domains\Inventario\Models\StockProducto;
use App\Domains\Inventario\Models\StockUbicacionInventario;
use App\Domains\Inventario\Models\UnidadMedida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaInventarioTrait;
use Tests\TestCase;

class InventarioFase15DespachoApiTest extends TestCase
{
    use RefreshDatabase;
    use PreparaInventarioTrait;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararUsuariosInventarioDemo();
    }

    public function test_crea_inicia_y_confirma_despacho_completo_desde_packing_empacado(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosFase15());
        [$producto, $bodega, $ubicacion] = $this->crearStockDisponible($empresa, 10);
        Sanctum::actingAs($usuario);

        $packingId = $this->crearPackingEmpacado($producto, $bodega, 4);

        $despachoId = $this->postJson('/api/inventario/despachos', [
            'packing_orden_id' => $packingId,
            'referencia' => 'PEDIDO-F15-001',
            'motivo' => 'despacho_interno',
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.estado', InventarioDespachoOrden::ESTADO_PENDIENTE)
            ->assertJsonPath('data.detalles.0.cantidad_empacada', '4.0000')
            ->json('data.id');

        $this->postJson("/api/inventario/despachos/{$despachoId}/iniciar")
            ->assertOk()
            ->assertJsonPath('data.estado', InventarioDespachoOrden::ESTADO_EN_DESPACHO);

        $this->postJson("/api/inventario/despachos/{$despachoId}/confirmar")
            ->assertOk()
            ->assertJsonPath('data.estado', InventarioDespachoOrden::ESTADO_DESPACHADO)
            ->assertJsonPath('data.detalles.0.estado', InventarioDespachoDetalle::ESTADO_DESPACHADO);

        $stockUbicacion = StockUbicacionInventario::where('ubicacion_id', $ubicacion->id)->firstOrFail();
        $stockBodega = StockProducto::where('producto_id', $producto->id)->where('bodega_id', $bodega->id)->firstOrFail();

        $this->assertEquals(6.0, (float) $stockUbicacion->stock_actual);
        $this->assertEquals(0.0, (float) $stockUbicacion->stock_reservado);
        $this->assertEquals(6.0, (float) $stockBodega->stock_actual);
        $this->assertEquals(1, MovimientoInventario::where('tipo', MovimientoInventario::TIPO_SALIDA)->where('producto_id', $producto->id)->count());
        $this->assertEquals(ReservaInventario::ESTADO_CONSUMIDA, ReservaInventario::firstOrFail()->estado);
    }

    public function test_rechaza_despacho_desde_packing_pendiente(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosFase15());
        [$producto, $bodega] = $this->crearStockDisponible($empresa, 8);
        Sanctum::actingAs($usuario);

        $pickingId = $this->crearPickingCompleto($producto, $bodega, 2);
        $packingId = $this->postJson('/api/inventario/packing', ['picking_orden_id' => $pickingId])->json('data.id');

        $this->postJson('/api/inventario/despachos', ['packing_orden_id' => $packingId])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_impide_despachar_mas_de_lo_empacado(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosFase15());
        [$producto, $bodega] = $this->crearStockDisponible($empresa, 10);
        Sanctum::actingAs($usuario);

        $packingId = $this->crearPackingEmpacado($producto, $bodega, 3);
        $despacho = $this->crearDespachoIniciado($packingId);
        $detalleId = $despacho['detalles'][0]['id'];

        $this->postJson("/api/inventario/despachos/{$despacho['id']}/confirmar", [
            'detalles' => [[
                'id' => $detalleId,
                'cantidad_despachada' => 4,
            ]],
        ])->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_confirma_despacho_parcial_y_libera_reserva_faltante(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosFase15());
        [$producto, $bodega, $ubicacion] = $this->crearStockDisponible($empresa, 10);
        Sanctum::actingAs($usuario);

        $packingId = $this->crearPackingEmpacado($producto, $bodega, 5);
        $despacho = $this->crearDespachoIniciado($packingId);
        $detalleId = $despacho['detalles'][0]['id'];

        $this->postJson("/api/inventario/despachos/{$despacho['id']}/confirmar", [
            'detalles' => [[
                'id' => $detalleId,
                'cantidad_despachada' => 3,
            ]],
        ])->assertOk()
            ->assertJsonPath('data.estado', InventarioDespachoOrden::ESTADO_CON_DIFERENCIAS)
            ->assertJsonPath('data.detalles.0.cantidad_faltante', '2.0000');

        $stockUbicacion = StockUbicacionInventario::where('ubicacion_id', $ubicacion->id)->firstOrFail();
        $this->assertEquals(7.0, (float) $stockUbicacion->stock_actual);
        $this->assertEquals(0.0, (float) $stockUbicacion->stock_reservado);
        $this->assertEquals(ReservaInventario::ESTADO_CONSUMIDA, ReservaInventario::firstOrFail()->estado);
    }

    public function test_impide_doble_confirmacion_de_despacho(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosFase15());
        [$producto, $bodega] = $this->crearStockDisponible($empresa, 10);
        Sanctum::actingAs($usuario);

        $packingId = $this->crearPackingEmpacado($producto, $bodega, 2);
        $despacho = $this->crearDespachoIniciado($packingId);

        $this->postJson("/api/inventario/despachos/{$despacho['id']}/confirmar")->assertOk();
        $this->postJson("/api/inventario/despachos/{$despacho['id']}/confirmar")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_cancela_despacho_pendiente_sin_liberar_reserva_de_picking(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosFase15());
        [$producto, $bodega, $ubicacion] = $this->crearStockDisponible($empresa, 10);
        Sanctum::actingAs($usuario);

        $packingId = $this->crearPackingEmpacado($producto, $bodega, 2);
        $despachoId = $this->postJson('/api/inventario/despachos', ['packing_orden_id' => $packingId])->json('data.id');

        $this->postJson("/api/inventario/despachos/{$despachoId}/cancelar", ['observacion' => 'Cancelación test'])
            ->assertOk()
            ->assertJsonPath('data.estado', InventarioDespachoOrden::ESTADO_CANCELADO);

        $stockUbicacion = StockUbicacionInventario::where('ubicacion_id', $ubicacion->id)->firstOrFail();
        $this->assertEquals(10.0, (float) $stockUbicacion->stock_actual);
        $this->assertEquals(2.0, (float) $stockUbicacion->stock_reservado, 'Cancelar despacho no revierte picking/packing; eso queda para cancelar picking o Fase 16.');
    }

    public function test_valida_401_sin_token(): void
    {
        $this->getJson('/api/inventario/despachos')->assertUnauthorized();
    }

    public function test_valida_permisos_para_crear_despacho(): void
    {
        [$empresa, $usuario] = $this->usuarioAuditorConPermisos($this->permisosInventarioAuditor());
        Sanctum::actingAs($usuario);

        $this->postJson('/api/inventario/despachos', ['packing_orden_id' => 999])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_respeta_multiempresa_al_generar_despacho(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosFase15());
        $otraEmpresa = Empresa::create([
            'rut' => (string) random_int(70000000, 99999999) . '-' . random_int(0, 9),
            'razon_social' => 'Empresa Ajena F15',
        ]);
        [$productoAjeno, $bodegaAjena] = $this->crearStockDisponible($otraEmpresa, 10);
        Sanctum::actingAs($usuario);

        $packingAjeno = $this->crearPackingEmpacado($productoAjeno, $bodegaAjena, 2, false);

        $this->postJson('/api/inventario/despachos', ['packing_orden_id' => $packingAjeno])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    private function permisosFase15(): array
    {
        return $this->permisosInventarioOperador();
    }

    private function crearDespachoIniciado(int $packingId): array
    {
        $despacho = $this->postJson('/api/inventario/despachos', ['packing_orden_id' => $packingId])
            ->assertCreated()
            ->json('data');

        $this->postJson("/api/inventario/despachos/{$despacho['id']}/iniciar")->assertOk();

        return $this->getJson("/api/inventario/despachos/{$despacho['id']}")->json('data');
    }

    private function crearPackingEmpacado(Producto $producto, Bodega $bodega, float $cantidad, bool $assert = true): int
    {
        $pickingId = $this->crearPickingCompleto($producto, $bodega, $cantidad, $assert);
        $packingResponse = $this->postJson('/api/inventario/packing', ['picking_orden_id' => $pickingId]);
        if ($assert) {
            $packingResponse->assertCreated();
        }
        $packingId = (int) $packingResponse->json('data.id');
        $this->postJson("/api/inventario/packing/{$packingId}/iniciar");
        $this->postJson("/api/inventario/packing/{$packingId}/confirmar");

        return $packingId;
    }

    private function crearPickingCompleto(Producto $producto, Bodega $bodega, float $cantidad, bool $assert = true): int
    {
        $response = $this->postJson('/api/inventario/picking', [
            'bodega_id' => $bodega->id,
            'prioridad' => 'NORMAL',
            'referencia' => 'F15-TEST',
            'detalles' => [[
                'producto_id' => $producto->id,
                'cantidad' => $cantidad,
            ]],
        ]);
        if ($assert) {
            $response->assertCreated();
        }
        $pickingId = (int) $response->json('data.id');
        $this->postJson("/api/inventario/picking/{$pickingId}/asignar");
        $this->postJson("/api/inventario/picking/{$pickingId}/iniciar");
        $this->postJson("/api/inventario/picking/{$pickingId}/confirmar");

        return $pickingId;
    }

    private function crearStockDisponible(Empresa $empresa, float $cantidad): array
    {
        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);
        $ubicacion = $this->crearUbicacion($empresa, $bodega);

        StockProducto::create([
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'stock_actual' => $cantidad,
            'costo_promedio' => 100,
            'valor_total' => $cantidad * 100,
        ]);

        StockUbicacionInventario::create([
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'ubicacion_id' => $ubicacion->id,
            'lote_id' => null,
            'lote_key' => 0,
            'stock_actual' => $cantidad,
            'stock_reservado' => 0,
            'stock_bloqueado' => 0,
            'stock_cuarentena' => 0,
            'stock_en_transito' => 0,
        ]);

        return [$producto, $bodega, $ubicacion];
    }

    private function crearBodega(Empresa $empresa, array $overrides = []): Bodega
    {
        return Bodega::create(array_merge([
            'empresa_id' => $empresa->id,
            'codigo' => 'BOD-F15-' . strtoupper(substr(uniqid(), -5)),
            'nombre' => 'Bodega Fase 15',
            'direccion' => 'Santiago, Chile',
            'estado' => 'ACTIVA',
        ], $overrides));
    }

    private function crearUbicacion(Empresa $empresa, Bodega $bodega, array $overrides = []): InventarioUbicacion
    {
        return InventarioUbicacion::create(array_merge([
            'empresa_id' => $empresa->id,
            'bodega_id' => $bodega->id,
            'codigo' => 'UBI-F15-' . strtoupper(substr(uniqid(), -5)),
            'nombre' => 'Ubicación Despacho F15',
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
            'sku' => 'F15-' . strtoupper(substr(uniqid(), -8)),
            'nombre' => 'Producto Fase 15',
            'descripcion' => 'Producto para despacho interno',
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
