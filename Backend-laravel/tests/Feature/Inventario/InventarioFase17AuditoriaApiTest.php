<?php

namespace Tests\Feature\Inventario;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\InventarioAuditoriaEvento;
use App\Domains\Inventario\Models\InventarioDevolucionOrden;
use App\Domains\Inventario\Models\InventarioDespachoDetalle;
use App\Domains\Inventario\Models\InventarioUbicacion;
use App\Domains\Inventario\Models\MovimientoInventario;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\StockProducto;
use App\Domains\Inventario\Models\StockUbicacionInventario;
use App\Domains\Inventario\Models\UnidadMedida;
use App\Domains\Inventario\Services\InventarioAuditoriaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaInventarioTrait;
use Tests\TestCase;

class InventarioFase17AuditoriaApiTest extends TestCase
{
    use RefreshDatabase;
    use PreparaInventarioTrait;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararUsuariosInventarioDemo();
    }

    public function test_registra_eventos_criticos_al_confirmar_despacho_y_devolucion(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosInventarioOperador());
        [$producto, $bodega, $ubicacion] = $this->crearStockDisponible($empresa, 10);
        Sanctum::actingAs($usuario);

        $despacho = $this->crearDespachoConfirmado($producto, $bodega, 4);

        $this->assertDatabaseHas('inventario_auditoria_eventos', [
            'empresa_id' => $empresa->id,
            'usuario_id' => $usuario->id,
            'accion' => InventarioAuditoriaEvento::ACCION_DESPACHO_CONFIRMADO,
            'entidad_tipo' => 'App\\Domains\\Inventario\\Models\\InventarioDespachoOrden',
            'entidad_id' => $despacho['id'],
            'severidad' => InventarioAuditoriaEvento::SEVERIDAD_CRITICAL,
        ]);

        $detalleId = $despacho['detalles'][0]['id'];
        $devolucionId = $this->postJson('/api/inventario/devoluciones', [
            'despacho_orden_id' => $despacho['id'],
            'tipo' => InventarioDevolucionOrden::TIPO_DEVOLUCION,
            'motivo' => 'devolucion_auditoria_f17',
            'detalles' => [[
                'despacho_detalle_id' => $detalleId,
                'cantidad_devolver' => 2,
                'ubicacion_destino_id' => $ubicacion->id,
            ]],
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('inventario_auditoria_eventos', [
            'empresa_id' => $empresa->id,
            'usuario_id' => $usuario->id,
            'accion' => InventarioAuditoriaEvento::ACCION_DEVOLUCION_CREADA,
            'entidad_id' => $devolucionId,
        ]);

        $this->postJson("/api/inventario/devoluciones/{$devolucionId}/confirmar")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('inventario_auditoria_eventos', [
            'empresa_id' => $empresa->id,
            'usuario_id' => $usuario->id,
            'accion' => InventarioAuditoriaEvento::ACCION_DEVOLUCION_CONFIRMADA,
            'entidad_id' => $devolucionId,
            'severidad' => InventarioAuditoriaEvento::SEVERIDAD_CRITICAL,
        ]);

        $this->assertGreaterThanOrEqual(
            2,
            InventarioAuditoriaEvento::where('accion', InventarioAuditoriaEvento::ACCION_MOVIMIENTO_CREADO)->count()
        );
    }

    public function test_consulta_auditoria_con_permiso_filtros_detalle_y_resumen(): void
    {
        [$empresa, $usuario] = $this->usuarioAuditorConPermisos($this->permisosInventarioAuditor());
        $evento = InventarioAuditoriaEvento::create([
            'empresa_id' => $empresa->id,
            'usuario_id' => $usuario->id,
            'accion' => InventarioAuditoriaEvento::ACCION_OPERACION_BLOQUEADA,
            'entidad_tipo' => 'inventario_test',
            'entidad_id' => 123,
            'severidad' => InventarioAuditoriaEvento::SEVERIDAD_WARNING,
            'estado' => InventarioAuditoriaEvento::ESTADO_REGISTRADO,
            'descripcion' => 'Evento de prueba Fase 17.',
            'metadata_json' => ['motivo' => 'seguridad_funcional'],
        ]);

        Sanctum::actingAs($usuario);

        $this->getJson('/api/inventario/auditoria?accion=OPERACION_BLOQUEADA&entidad_tipo=inventario_test&entidad_id=123')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $evento->id);

        $this->getJson("/api/inventario/auditoria/{$evento->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.accion', InventarioAuditoriaEvento::ACCION_OPERACION_BLOQUEADA);

        $this->getJson('/api/inventario/auditoria/resumen?severidad=WARNING')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.por_severidad.WARNING', 1);
    }

    public function test_bloquea_consulta_de_auditoria_sin_permiso_y_valida_401(): void
    {
        $this->getJson('/api/inventario/auditoria')->assertUnauthorized();

        [, $usuario] = $this->usuarioAuditorConPermisos(['inventario.productos.ver']);
        Sanctum::actingAs($usuario);

        $this->getJson('/api/inventario/auditoria')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_respeta_multiempresa_en_listado_de_auditoria(): void
    {
        [$empresa, $usuario] = $this->usuarioAuditorConPermisos($this->permisosInventarioAuditor());
        $otraEmpresa = Empresa::create([
            'rut' => (string) random_int(70000000, 99999999) . '-' . random_int(0, 9),
            'razon_social' => 'Empresa Ajena Auditoria F17',
        ]);

        InventarioAuditoriaEvento::create([
            'empresa_id' => $empresa->id,
            'usuario_id' => $usuario->id,
            'accion' => InventarioAuditoriaEvento::ACCION_PRODUCTO_CREADO,
            'entidad_tipo' => Producto::class,
            'entidad_id' => 1,
            'descripcion' => 'Evento visible.',
        ]);

        InventarioAuditoriaEvento::create([
            'empresa_id' => $otraEmpresa->id,
            'usuario_id' => null,
            'accion' => InventarioAuditoriaEvento::ACCION_PRODUCTO_CREADO,
            'entidad_tipo' => Producto::class,
            'entidad_id' => 999,
            'descripcion' => 'Evento de otra empresa.',
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->getJson('/api/inventario/auditoria?accion=PRODUCTO_CREADO')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($empresa->id, $response->json('data.0.empresa_id'));
    }

    public function test_sanea_metadata_sensible_y_no_introduce_campos_dte_sii(): void
    {
        [$empresa, $usuario] = $this->usuarioAuditorConPermisos($this->permisosInventarioAuditor());

        app(InventarioAuditoriaService::class)->registrarEvento($usuario, [
            'empresa_id' => $empresa->id,
            'accion' => InventarioAuditoriaEvento::ACCION_OPERACION_BLOQUEADA,
            'entidad_tipo' => 'inventario_seguridad_test',
            'entidad_id' => 1,
            'severidad' => InventarioAuditoriaEvento::SEVERIDAD_WARNING,
            'descripcion' => 'Intento bloqueado de prueba.',
            'metadata_json' => [
                'Authorization' => 'Bearer token-secreto',
                'password' => 'password-secreta',
                'payload' => [
                    'access_token' => 'token-interno',
                    'campo_seguro' => 'visible',
                ],
            ],
        ]);

        $evento = InventarioAuditoriaEvento::where('entidad_tipo', 'inventario_seguridad_test')->firstOrFail();
        $metadata = $evento->metadata_json;

        $this->assertArrayNotHasKey('Authorization', $metadata);
        $this->assertArrayNotHasKey('password', $metadata);
        $this->assertArrayNotHasKey('access_token', $metadata['payload']);
        $this->assertEquals('visible', $metadata['payload']['campo_seguro']);

        foreach (['codigo_dte', 'codigo_sii', 'folio_dte', 'xml_dte', 'track_id_sii', 'estado_sii'] as $columnaTributaria) {
            $this->assertFalse(Schema::hasColumn('inventario_auditoria_eventos', $columnaTributaria));
        }
    }

    private function crearDespachoConfirmado(Producto $producto, Bodega $bodega, float $cantidad): array
    {
        $packingId = $this->crearPackingEmpacado($producto, $bodega, $cantidad);
        $response = $this->postJson('/api/inventario/despachos', ['packing_orden_id' => $packingId])->assertCreated();
        $despachoId = (int) $response->json('data.id');

        $this->postJson("/api/inventario/despachos/{$despachoId}/iniciar")->assertOk();
        $this->postJson("/api/inventario/despachos/{$despachoId}/confirmar")->assertOk();

        return $this->getJson("/api/inventario/despachos/{$despachoId}")->assertOk()->json('data');
    }

    private function crearPackingEmpacado(Producto $producto, Bodega $bodega, float $cantidad): int
    {
        $pickingId = $this->crearPickingCompleto($producto, $bodega, $cantidad);
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
            'referencia' => 'F17-AUDITORIA',
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
            'codigo' => 'BOD-F17-' . strtoupper(substr(uniqid(), -5)),
            'nombre' => 'Bodega Auditoria F17',
            'direccion' => 'Santiago, Chile',
            'estado' => 'ACTIVA',
        ]);
    }

    private function crearUbicacion(Empresa $empresa, Bodega $bodega): InventarioUbicacion
    {
        return InventarioUbicacion::create([
            'empresa_id' => $empresa->id,
            'bodega_id' => $bodega->id,
            'codigo' => 'UBI-F17-' . strtoupper(substr(uniqid(), -5)),
            'nombre' => 'Ubicación Auditoria F17',
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
            'sku' => 'F17-' . strtoupper(substr(uniqid(), -8)),
            'nombre' => 'Producto Auditoria F17',
            'descripcion' => 'Producto para pruebas de auditoría operativa',
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
