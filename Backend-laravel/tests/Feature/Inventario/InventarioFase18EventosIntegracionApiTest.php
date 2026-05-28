<?php

namespace Tests\Feature\Inventario;

use App\Domains\Core\Models\Empresa;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\InventarioDespachoOrden;
use App\Domains\Inventario\Models\InventarioDevolucionOrden;
use App\Domains\Inventario\Models\InventarioEventoIntegracion;
use App\Domains\Inventario\Models\InventarioUbicacion;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\StockProducto;
use App\Domains\Inventario\Models\StockUbicacionInventario;
use App\Domains\Inventario\Models\UnidadMedida;
use App\Domains\Inventario\Services\InventarioEventoIntegracionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaInventarioTrait;
use Tests\TestCase;

class InventarioFase18EventosIntegracionApiTest extends TestCase
{
    use RefreshDatabase;
    use PreparaInventarioTrait;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararUsuariosInventarioDemo();
    }

    public function test_publica_eventos_internos_en_flujo_logistico_critico(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosInventarioOperador());
        [$producto, $bodega, $ubicacion] = $this->crearStockDisponible($empresa, 10);
        Sanctum::actingAs($usuario);

        $pickingId = $this->crearPickingCompleto($producto, $bodega, 4);
        $this->assertDatabaseHas('inventario_eventos_integracion', [
            'empresa_id' => $empresa->id,
            'usuario_id' => $usuario->id,
            'evento' => InventarioEventoIntegracion::EVENTO_PICKING_CONFIRMADO,
            'entidad_id' => $pickingId,
            'estado' => InventarioEventoIntegracion::ESTADO_PENDIENTE,
        ]);

        $packingId = $this->crearPackingEmpacadoDesdePicking($pickingId);
        $this->assertDatabaseHas('inventario_eventos_integracion', [
            'empresa_id' => $empresa->id,
            'usuario_id' => $usuario->id,
            'evento' => InventarioEventoIntegracion::EVENTO_PACKING_CONFIRMADO,
            'entidad_id' => $packingId,
        ]);

        $despacho = $this->crearDespachoConfirmadoDesdePacking($packingId);
        $this->assertDatabaseHas('inventario_eventos_integracion', [
            'empresa_id' => $empresa->id,
            'usuario_id' => $usuario->id,
            'evento' => InventarioEventoIntegracion::EVENTO_DESPACHO_CONFIRMADO,
            'entidad_tipo' => InventarioDespachoOrden::class,
            'entidad_id' => $despacho['id'],
            'prioridad' => InventarioEventoIntegracion::PRIORIDAD_CRITICA,
        ]);

        $detalleId = $despacho['detalles'][0]['id'];
        $devolucionId = $this->postJson('/api/inventario/devoluciones', [
            'despacho_orden_id' => $despacho['id'],
            'tipo' => InventarioDevolucionOrden::TIPO_DEVOLUCION,
            'motivo' => 'devolucion_eventos_f18',
            'detalles' => [[
                'despacho_detalle_id' => $detalleId,
                'cantidad_devolver' => 2,
                'ubicacion_destino_id' => $ubicacion->id,
            ]],
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('inventario_eventos_integracion', [
            'empresa_id' => $empresa->id,
            'usuario_id' => $usuario->id,
            'evento' => InventarioEventoIntegracion::EVENTO_DEVOLUCION_CREADA,
            'entidad_id' => $devolucionId,
        ]);

        $this->postJson("/api/inventario/devoluciones/{$devolucionId}/confirmar")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('inventario_eventos_integracion', [
            'empresa_id' => $empresa->id,
            'usuario_id' => $usuario->id,
            'evento' => InventarioEventoIntegracion::EVENTO_DEVOLUCION_CONFIRMADA,
            'entidad_id' => $devolucionId,
            'prioridad' => InventarioEventoIntegracion::PRIORIDAD_CRITICA,
        ]);

        $this->assertGreaterThanOrEqual(
            2,
            InventarioEventoIntegracion::where('evento', InventarioEventoIntegracion::EVENTO_MOVIMIENTO_CREADO)->count()
        );
    }

    public function test_consulta_filtra_resume_y_gestiona_eventos_con_permisos(): void
    {
        [$empresa, $auditor] = $this->usuarioAuditorConPermisos($this->permisosInventarioAuditor());
        [, $operador] = $this->usuarioContadorConPermisos($this->permisosInventarioOperador());

        $evento = InventarioEventoIntegracion::create([
            'empresa_id' => $empresa->id,
            'usuario_id' => $auditor->id,
            'evento' => InventarioEventoIntegracion::EVENTO_DESPACHO_CONFIRMADO,
            'entidad_tipo' => InventarioDespachoOrden::class,
            'entidad_id' => 777,
            'estado' => InventarioEventoIntegracion::ESTADO_PENDIENTE,
            'prioridad' => InventarioEventoIntegracion::PRIORIDAD_CRITICA,
            'payload_json' => ['codigo' => 'DESP-F18'],
            'metadata_json' => ['motivo' => 'test_f18'],
            'correlacion_id' => 'corr-f18-001',
        ]);

        Sanctum::actingAs($auditor);

        $this->getJson('/api/inventario/eventos-integracion?evento=INVENTARIO_DESPACHO_CONFIRMADO&estado=PENDIENTE&prioridad=CRITICA&entidad_id=777')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $evento->id);

        $this->getJson("/api/inventario/eventos-integracion/{$evento->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.evento', InventarioEventoIntegracion::EVENTO_DESPACHO_CONFIRMADO);

        $this->getJson('/api/inventario/eventos-integracion/resumen?estado=PENDIENTE')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.por_estado.PENDIENTE', 1);

        $this->postJson("/api/inventario/eventos-integracion/{$evento->id}/procesar")
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        Sanctum::actingAs($operador);

        $this->postJson("/api/inventario/eventos-integracion/{$evento->id}/procesar")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.estado', InventarioEventoIntegracion::ESTADO_PROCESADO);

        $eventoIgnorar = InventarioEventoIntegracion::create([
            'empresa_id' => $empresa->id,
            'usuario_id' => $operador->id,
            'evento' => InventarioEventoIntegracion::EVENTO_PACKING_CONFIRMADO,
            'entidad_tipo' => 'packing_test',
            'entidad_id' => 10,
            'estado' => InventarioEventoIntegracion::ESTADO_PENDIENTE,
            'prioridad' => InventarioEventoIntegracion::PRIORIDAD_NORMAL,
        ]);

        $this->postJson("/api/inventario/eventos-integracion/{$eventoIgnorar->id}/ignorar", ['motivo' => 'sin accion externa'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.estado', InventarioEventoIntegracion::ESTADO_IGNORADO);

        $eventoError = InventarioEventoIntegracion::create([
            'empresa_id' => $empresa->id,
            'usuario_id' => $operador->id,
            'evento' => InventarioEventoIntegracion::EVENTO_PICKING_CONFIRMADO,
            'entidad_tipo' => 'picking_test',
            'entidad_id' => 11,
            'estado' => InventarioEventoIntegracion::ESTADO_PENDIENTE,
            'prioridad' => InventarioEventoIntegracion::PRIORIDAD_ALTA,
        ]);

        $this->postJson("/api/inventario/eventos-integracion/{$eventoError->id}/error", ['mensaje' => 'fallo de adaptador interno'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.estado', InventarioEventoIntegracion::ESTADO_ERROR);
    }

    public function test_bloquea_eventos_integracion_sin_permiso_valida_401_y_multiempresa(): void
    {
        $this->getJson('/api/inventario/eventos-integracion')->assertUnauthorized();

        [$empresa, $usuario] = $this->usuarioAuditorConPermisos(['inventario.productos.ver']);
        $otraEmpresa = Empresa::create([
            'rut' => (string) random_int(70000000, 99999999) . '-' . random_int(0, 9),
            'razon_social' => 'Empresa Ajena Eventos F18',
        ]);

        InventarioEventoIntegracion::create([
            'empresa_id' => $empresa->id,
            'usuario_id' => $usuario->id,
            'evento' => InventarioEventoIntegracion::EVENTO_MOVIMIENTO_CREADO,
            'entidad_tipo' => 'visible_test',
            'entidad_id' => 1,
        ]);

        InventarioEventoIntegracion::create([
            'empresa_id' => $otraEmpresa->id,
            'usuario_id' => null,
            'evento' => InventarioEventoIntegracion::EVENTO_MOVIMIENTO_CREADO,
            'entidad_tipo' => 'oculto_test',
            'entidad_id' => 2,
        ]);

        Sanctum::actingAs($usuario);

        $this->getJson('/api/inventario/eventos-integracion')
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        [$empresa, $auditor] = $this->usuarioAuditorConPermisos($this->permisosInventarioAuditor());
        Sanctum::actingAs($auditor);

        $response = $this->getJson('/api/inventario/eventos-integracion?evento=INVENTARIO_MOVIMIENTO_CREADO')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($empresa->id, $response->json('data.0.empresa_id'));
    }

    public function test_sanea_payload_sensible_y_no_introduce_campos_dte_sii(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosInventarioOperador());

        app(InventarioEventoIntegracionService::class)->publicarEvento($usuario, [
            'empresa_id' => $empresa->id,
            'evento' => InventarioEventoIntegracion::EVENTO_MOVIMIENTO_CREADO,
            'entidad_tipo' => 'inventario_seguridad_f18',
            'entidad_id' => 1,
            'payload_json' => [
                'Authorization' => 'Bearer token-secreto',
                'password' => 'password-secreta',
                'codigo_dte' => 'no-debe-guardarse',
                'payload' => [
                    'access_token' => 'token-interno',
                    'xml_dte' => '<xml/>',
                    'campo_seguro' => 'visible',
                ],
            ],
            'metadata_json' => [
                'track_id_sii' => 'no-debe-guardarse',
                'campo_seguro' => 'visible_metadata',
            ],
        ]);

        $evento = InventarioEventoIntegracion::where('entidad_tipo', 'inventario_seguridad_f18')->firstOrFail();
        $payload = $evento->payload_json;
        $metadata = $evento->metadata_json;

        $this->assertArrayNotHasKey('Authorization', $payload);
        $this->assertArrayNotHasKey('password', $payload);
        $this->assertArrayNotHasKey('codigo_dte', $payload);
        $this->assertArrayNotHasKey('access_token', $payload['payload']);
        $this->assertArrayNotHasKey('xml_dte', $payload['payload']);
        $this->assertEquals('visible', $payload['payload']['campo_seguro']);
        $this->assertArrayNotHasKey('track_id_sii', $metadata);
        $this->assertEquals('visible_metadata', $metadata['campo_seguro']);

        foreach (['codigo_dte', 'codigo_sii', 'folio_dte', 'xml_dte', 'track_id_sii', 'estado_sii'] as $columnaTributaria) {
            $this->assertFalse(Schema::hasColumn('inventario_eventos_integracion', $columnaTributaria));
        }
    }

    private function crearDespachoConfirmadoDesdePacking(int $packingId): array
    {
        $response = $this->postJson('/api/inventario/despachos', ['packing_orden_id' => $packingId])->assertCreated();
        $despachoId = (int) $response->json('data.id');

        $this->postJson("/api/inventario/despachos/{$despachoId}/iniciar")->assertOk();
        $this->postJson("/api/inventario/despachos/{$despachoId}/confirmar")->assertOk();

        return $this->getJson("/api/inventario/despachos/{$despachoId}")->assertOk()->json('data');
    }

    private function crearPackingEmpacadoDesdePicking(int $pickingId): int
    {
        $packingResponse = $this->postJson('/api/inventario/packing', ['picking_orden_id' => $pickingId])->assertCreated();
        $packingId = (int) $packingResponse->json('data.id');

        $this->postJson("/api/inventario/packing/{$packingId}/iniciar")->assertOk();
        $this->postJson("/api/inventario/packing/{$packingId}/confirmar")->assertOk();

        return $packingId;
    }

    private function crearPickingCompleto(Producto $producto, Bodega $bodega, float $cantidad): int
    {
        $response = $this->postJson('/api/inventario/picking', [
            'bodega_id' => $bodega->id,
            'prioridad' => 'NORMAL',
            'referencia' => 'F18-EVENTOS',
            'detalles' => [[
                'producto_id' => $producto->id,
                'cantidad' => $cantidad,
            ]],
        ])->assertCreated();

        $pickingId = (int) $response->json('data.id');
        $this->postJson("/api/inventario/picking/{$pickingId}/asignar")->assertOk();
        $this->postJson("/api/inventario/picking/{$pickingId}/iniciar")->assertOk();
        $this->postJson("/api/inventario/picking/{$pickingId}/confirmar")->assertOk();

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

    private function crearBodega(Empresa $empresa): Bodega
    {
        return Bodega::create([
            'empresa_id' => $empresa->id,
            'codigo' => 'BOD-F18-' . strtoupper(substr(uniqid(), -5)),
            'nombre' => 'Bodega Eventos F18',
            'direccion' => 'Santiago, Chile',
            'estado' => 'ACTIVA',
        ]);
    }

    private function crearUbicacion(Empresa $empresa, Bodega $bodega): InventarioUbicacion
    {
        return InventarioUbicacion::create([
            'empresa_id' => $empresa->id,
            'bodega_id' => $bodega->id,
            'codigo' => 'UBI-F18-' . strtoupper(substr(uniqid(), -5)),
            'nombre' => 'Ubicación Eventos F18',
            'tipo' => InventarioUbicacion::TIPO_UBICACION,
            'activo' => true,
        ]);
    }

    private function crearProducto(Empresa $empresa): Producto
    {
        $unidad = UnidadMedida::firstOrCreate(
            ['codigo' => 'UN'],
            ['nombre' => 'Unidad', 'permite_decimal' => false, 'activo' => true]
        );

        return Producto::create([
            'empresa_id' => $empresa->id,
            'sku' => 'F18-' . strtoupper(substr(uniqid(), -8)),
            'nombre' => 'Producto Eventos F18',
            'descripcion' => 'Producto para pruebas de eventos internos de integración',
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
        ]);
    }
}
