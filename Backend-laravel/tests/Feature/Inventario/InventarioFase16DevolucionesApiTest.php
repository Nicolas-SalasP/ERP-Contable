<?php

namespace Tests\Feature\Inventario;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\InventarioDevolucionDetalle;
use App\Domains\Inventario\Models\InventarioDevolucionOrden;
use App\Domains\Inventario\Models\InventarioDespachoDetalle;
use App\Domains\Inventario\Models\InventarioDespachoOrden;
use App\Domains\Inventario\Models\InventarioUbicacion;
use App\Domains\Inventario\Models\MovimientoInventario;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\StockProducto;
use App\Domains\Inventario\Models\StockUbicacionInventario;
use App\Domains\Inventario\Models\UnidadMedida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaInventarioTrait;
use Tests\TestCase;

class InventarioFase16DevolucionesApiTest extends TestCase
{
    use RefreshDatabase;
    use PreparaInventarioTrait;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararUsuariosInventarioDemo();
    }

    public function test_crea_y_confirma_devolucion_post_despacho_reingresando_stock(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosFase16());
        [$producto, $bodega, $ubicacion] = $this->crearStockDisponible($empresa, 10);
        Sanctum::actingAs($usuario);

        $despacho = $this->crearDespachoConfirmado($producto, $bodega, 4);
        $detalleId = $despacho['detalles'][0]['id'];

        $reversableResponse = $this->getJson("/api/inventario/despachos/{$despacho['id']}/reversable")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertEquals(4.0, (float) $reversableResponse->json('data.detalles.0.cantidad_reversable'));

        $devolucionId = $this->postJson('/api/inventario/devoluciones', [
            'despacho_orden_id' => $despacho['id'],
            'tipo' => InventarioDevolucionOrden::TIPO_DEVOLUCION,
            'motivo' => 'devolucion_error_operativo',
            'detalles' => [[
                'despacho_detalle_id' => $detalleId,
                'cantidad_devolver' => 2,
                'ubicacion_destino_id' => $ubicacion->id,
            ]],
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.estado', InventarioDevolucionOrden::ESTADO_PENDIENTE)
            ->json('data.id');

        $this->postJson("/api/inventario/devoluciones/{$devolucionId}/confirmar")
            ->assertOk()
            ->assertJsonPath('data.estado', InventarioDevolucionOrden::ESTADO_CONFIRMADA)
            ->assertJsonPath('data.detalles.0.estado', InventarioDevolucionDetalle::ESTADO_ACEPTADO);

        $stockUbicacion = StockUbicacionInventario::where('ubicacion_id', $ubicacion->id)->firstOrFail();
        $stockBodega = StockProducto::where('producto_id', $producto->id)->where('bodega_id', $bodega->id)->firstOrFail();

        $this->assertEquals(8.0, (float) $stockUbicacion->stock_actual);
        $this->assertEquals(8.0, (float) $stockBodega->stock_actual);
        $this->assertEquals(1, MovimientoInventario::where('tipo', MovimientoInventario::TIPO_ENTRADA)->where('motivo', MovimientoInventario::MOTIVO_DEVOLUCION)->count());
    }

    public function test_rechaza_devolucion_desde_despacho_pendiente_en_despacho_o_cancelado(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosFase16());
        [$producto, $bodega, $ubicacion] = $this->crearStockDisponible($empresa, 20);
        Sanctum::actingAs($usuario);

        $packingPendiente = $this->crearPackingEmpacado($producto, $bodega, 2);
        $despachoPendienteId = $this->postJson('/api/inventario/despachos', ['packing_orden_id' => $packingPendiente])->json('data.id');

        $packingEnDespacho = $this->crearPackingEmpacado($producto, $bodega, 2);
        $despachoEnDespacho = $this->crearDespachoIniciado($packingEnDespacho);

        $packingCancelado = $this->crearPackingEmpacado($producto, $bodega, 2);
        $despachoCanceladoId = $this->postJson('/api/inventario/despachos', ['packing_orden_id' => $packingCancelado])->json('data.id');
        $this->postJson("/api/inventario/despachos/{$despachoCanceladoId}/cancelar")->assertOk();

        foreach ([$despachoPendienteId, $despachoEnDespacho['id'], $despachoCanceladoId] as $despachoId) {
            $this->postJson('/api/inventario/devoluciones', [
                'despacho_orden_id' => $despachoId,
                'tipo' => InventarioDevolucionOrden::TIPO_DEVOLUCION,
                'detalles' => [[
                    'despacho_detalle_id' => 999,
                    'cantidad_devolver' => 1,
                    'ubicacion_destino_id' => $ubicacion->id,
                ]],
            ])->assertStatus(422)
                ->assertJsonPath('success', false);
        }
    }

    public function test_impide_devolver_mas_de_lo_despachado_y_mas_de_lo_pendiente_reversable(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosFase16());
        [$producto, $bodega, $ubicacion] = $this->crearStockDisponible($empresa, 10);
        Sanctum::actingAs($usuario);

        $despacho = $this->crearDespachoConfirmado($producto, $bodega, 3);
        $detalleId = $despacho['detalles'][0]['id'];

        $this->postJson('/api/inventario/devoluciones', [
            'despacho_orden_id' => $despacho['id'],
            'tipo' => InventarioDevolucionOrden::TIPO_DEVOLUCION,
            'detalles' => [[
                'despacho_detalle_id' => $detalleId,
                'cantidad_devolver' => 4,
                'ubicacion_destino_id' => $ubicacion->id,
            ]],
        ])->assertStatus(422)
            ->assertJsonPath('success', false);

        $devolucionId = $this->postJson('/api/inventario/devoluciones', [
            'despacho_orden_id' => $despacho['id'],
            'tipo' => InventarioDevolucionOrden::TIPO_DEVOLUCION,
            'detalles' => [[
                'despacho_detalle_id' => $detalleId,
                'cantidad_devolver' => 2,
                'ubicacion_destino_id' => $ubicacion->id,
            ]],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/inventario/devoluciones/{$devolucionId}/confirmar")->assertOk();

        $this->postJson('/api/inventario/devoluciones', [
            'despacho_orden_id' => $despacho['id'],
            'tipo' => InventarioDevolucionOrden::TIPO_REVERSA_PARCIAL,
            'detalles' => [[
                'despacho_detalle_id' => $detalleId,
                'cantidad_devolver' => 2,
                'ubicacion_destino_id' => $ubicacion->id,
            ]],
        ])->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_crea_reversa_total_e_impide_reversa_total_duplicada(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosFase16());
        [$producto, $bodega] = $this->crearStockDisponible($empresa, 10);
        Sanctum::actingAs($usuario);

        $despacho = $this->crearDespachoConfirmado($producto, $bodega, 2);

        $reversaId = $this->postJson('/api/inventario/devoluciones', [
            'despacho_orden_id' => $despacho['id'],
            'tipo' => InventarioDevolucionOrden::TIPO_REVERSA_TOTAL,
            'motivo' => 'reversa_total_test',
        ])->assertCreated()
            ->assertJsonPath('data.detalles.0.cantidad_devolver', '2.0000')
            ->json('data.id');

        $this->postJson("/api/inventario/devoluciones/{$reversaId}/confirmar")->assertOk();

        $this->postJson('/api/inventario/devoluciones', [
            'despacho_orden_id' => $despacho['id'],
            'tipo' => InventarioDevolucionOrden::TIPO_REVERSA_TOTAL,
        ])->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_cancela_devolucion_pendiente_sin_mover_stock(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosFase16());
        [$producto, $bodega, $ubicacion] = $this->crearStockDisponible($empresa, 10);
        Sanctum::actingAs($usuario);

        $despacho = $this->crearDespachoConfirmado($producto, $bodega, 2);
        $detalleId = $despacho['detalles'][0]['id'];

        $devolucionId = $this->postJson('/api/inventario/devoluciones', [
            'despacho_orden_id' => $despacho['id'],
            'tipo' => InventarioDevolucionOrden::TIPO_DEVOLUCION,
            'detalles' => [[
                'despacho_detalle_id' => $detalleId,
                'cantidad_devolver' => 1,
                'ubicacion_destino_id' => $ubicacion->id,
            ]],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/inventario/devoluciones/{$devolucionId}/cancelar", ['observacion' => 'Cancelación test'])
            ->assertOk()
            ->assertJsonPath('data.estado', InventarioDevolucionOrden::ESTADO_CANCELADA);

        $stockUbicacion = StockUbicacionInventario::where('ubicacion_id', $ubicacion->id)->firstOrFail();
        $this->assertEquals(8.0, (float) $stockUbicacion->stock_actual);
    }

    public function test_valida_401_y_permisos_en_devoluciones(): void
    {
        $this->getJson('/api/inventario/devoluciones')->assertUnauthorized();

        [$empresa, $usuario] = $this->usuarioAuditorConPermisos($this->permisosInventarioAuditor());
        Sanctum::actingAs($usuario);

        $this->postJson('/api/inventario/devoluciones', [
            'despacho_orden_id' => 999,
            'tipo' => InventarioDevolucionOrden::TIPO_DEVOLUCION,
        ])->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_respeta_multiempresa_y_no_crea_campos_dte_sii(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosFase16());
        $otraEmpresa = Empresa::create([
            'rut' => (string) random_int(70000000, 99999999) . '-' . random_int(0, 9),
            'razon_social' => 'Empresa Ajena F16',
        ]);
        [$productoAjeno, $bodegaAjena, $ubicacionAjena] = $this->crearStockDisponible($otraEmpresa, 10);
        $otroUsuario = User::create([
            'empresa_id' => $otraEmpresa->id,
            'email' => 'operador-f16-ajeno@example.com',
            'password' => 'password',
            'nombre' => 'Operador F16 Ajeno',
            'rol_id' => $usuario->rol_id,
        ]);

        Sanctum::actingAs($otroUsuario);
        $despachoAjeno = $this->crearDespachoConfirmado($productoAjeno, $bodegaAjena, 2);
        $detalleAjeno = InventarioDespachoDetalle::where('despacho_orden_id', $despachoAjeno['id'])->firstOrFail();

        Sanctum::actingAs($usuario);
        $this->postJson('/api/inventario/devoluciones', [
            'despacho_orden_id' => $despachoAjeno['id'],
            'tipo' => InventarioDevolucionOrden::TIPO_DEVOLUCION,
            'detalles' => [[
                'despacho_detalle_id' => $detalleAjeno->id,
                'cantidad_devolver' => 1,
                'ubicacion_destino_id' => $ubicacionAjena->id,
            ]],
        ])->assertStatus(422)
            ->assertJsonPath('success', false);

        foreach (['codigo_dte', 'codigo_sii', 'folio_dte', 'xml_dte', 'track_id_sii', 'estado_sii'] as $columnaTributaria) {
            $this->assertFalse(Schema::hasColumn('inventario_devolucion_ordenes', $columnaTributaria));
            $this->assertFalse(Schema::hasColumn('inventario_devolucion_detalles', $columnaTributaria));
        }
    }

    private function permisosFase16(): array
    {
        return $this->permisosInventarioOperador();
    }

    private function crearDespachoConfirmado(Producto $producto, Bodega $bodega, float $cantidad, bool $assert = true): array
    {
        $packingId = $this->crearPackingEmpacado($producto, $bodega, $cantidad, $assert);
        $despacho = $this->crearDespachoIniciado($packingId, $assert);
        $response = $this->postJson("/api/inventario/despachos/{$despacho['id']}/confirmar");
        if ($assert) {
            $response->assertOk();
        }

        return $this->getJson("/api/inventario/despachos/{$despacho['id']}")->json('data');
    }

    private function crearDespachoIniciado(int $packingId, bool $assert = true): array
    {
        $response = $this->postJson('/api/inventario/despachos', ['packing_orden_id' => $packingId]);
        if ($assert) {
            $response->assertCreated();
        }
        $despacho = $response->json('data');
        $this->postJson("/api/inventario/despachos/{$despacho['id']}/iniciar");

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
            'referencia' => 'F16-TEST',
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
            'codigo' => 'BOD-F16-' . strtoupper(substr(uniqid(), -5)),
            'nombre' => 'Bodega Fase 16',
            'direccion' => 'Santiago, Chile',
            'estado' => 'ACTIVA',
        ], $overrides));
    }

    private function crearUbicacion(Empresa $empresa, Bodega $bodega, array $overrides = []): InventarioUbicacion
    {
        return InventarioUbicacion::create(array_merge([
            'empresa_id' => $empresa->id,
            'bodega_id' => $bodega->id,
            'codigo' => 'UBI-F16-' . strtoupper(substr(uniqid(), -5)),
            'nombre' => 'Ubicación Devolución F16',
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
            'sku' => 'F16-' . strtoupper(substr(uniqid(), -8)),
            'nombre' => 'Producto Fase 16',
            'descripcion' => 'Producto para devolución/reversa post-despacho',
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
