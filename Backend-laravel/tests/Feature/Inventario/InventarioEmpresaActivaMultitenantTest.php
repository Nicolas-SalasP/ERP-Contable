<?php

namespace Tests\Feature\Inventario;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\InventarioDespachoOrden;
use App\Domains\Inventario\Models\InventarioPackingOrden;
use App\Domains\Inventario\Models\InventarioPickingOrden;
use App\Domains\Inventario\Models\MovimientoInventario;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\ReservaInventario;
use App\Domains\Inventario\Models\StockProducto;
use App\Domains\Inventario\Models\UnidadMedida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaInventarioTrait;
use Tests\TestCase;

/**
 * Regresión: un usuario multiempresa que cambia su empresa_activa_id (vía EmpresaCambioController)
 * debe operar SIEMPRE sobre esa empresa activa en movimientos/kardex/valorización, nunca sobre
 * su empresa_id "hogar" (fuga/escritura cruzada multitenant).
 */
class InventarioEmpresaActivaMultitenantTest extends TestCase
{
    use RefreshDatabase;
    use PreparaInventarioTrait;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararUsuariosInventarioDemo();
    }

    /** Crea un usuario cuya empresa_id (hogar) es A pero cuya empresa_activa_id es B. */
    private function crearUsuarioMultiempresa(Empresa $empresaHogar, Empresa $empresaActiva): User
    {
        [, $usuario] = $this->usuarioContadorConPermisos($this->permisosCompletos());

        $usuario->update([
            'empresa_id' => $empresaHogar->id,
            'empresa_activa_id' => $empresaActiva->id,
        ]);
        $usuario->refresh();
        $usuario->load('rol');

        return $usuario;
    }

    public function test_listar_movimientos_usa_empresa_activa_no_empresa_hogar(): void
    {
        $empresaA = $this->crearEmpresa();
        $empresaB = $this->crearEmpresa();

        $usuario = $this->crearUsuarioMultiempresa($empresaA, $empresaB);

        $productoA = $this->crearProducto($empresaA, ['sku' => 'PROD-A-MOV']);
        $bodegaA = $this->crearBodega($empresaA, ['codigo' => 'BOD-A-MOV']);
        MovimientoInventario::create([
            'empresa_id' => $empresaA->id,
            'producto_id' => $productoA->id,
            'bodega_destino_id' => $bodegaA->id,
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'cantidad' => 5,
            'costo_unitario' => 100,
            'valor_total' => 500,
            'referencia' => 'MOV-EMPRESA-A',
        ]);

        $productoB = $this->crearProducto($empresaB, ['sku' => 'PROD-B-MOV']);
        $bodegaB = $this->crearBodega($empresaB, ['codigo' => 'BOD-B-MOV']);
        MovimientoInventario::create([
            'empresa_id' => $empresaB->id,
            'producto_id' => $productoB->id,
            'bodega_destino_id' => $bodegaB->id,
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'cantidad' => 8,
            'costo_unitario' => 200,
            'valor_total' => 1600,
            'referencia' => 'MOV-EMPRESA-B',
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->getJson('/api/inventario/movimientos');

        $response->assertOk()->assertJsonPath('pagination.total', 1);
        $this->assertSame('MOV-EMPRESA-B', $response->json('data.0.referencia'));
    }

    public function test_registrar_movimiento_se_guarda_en_empresa_activa_no_en_empresa_hogar(): void
    {
        $empresaA = $this->crearEmpresa();
        $empresaB = $this->crearEmpresa();

        $usuario = $this->crearUsuarioMultiempresa($empresaA, $empresaB);

        $productoB = $this->crearProducto($empresaB, ['sku' => 'PROD-B-REG']);
        $bodegaB = $this->crearBodega($empresaB, ['codigo' => 'BOD-B-REG']);

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $productoB->id,
            'bodega_destino_id' => $bodegaB->id,
            'cantidad' => 10,
            'costo_unitario' => 1000,
            'referencia' => 'INGRESO-EMPRESA-ACTIVA',
            'motivo' => MovimientoInventario::MOTIVO_INGRESO_MANUAL,
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('inventario_movimientos', [
            'empresa_id' => $empresaB->id,
            'referencia' => 'INGRESO-EMPRESA-ACTIVA',
        ]);
        $this->assertDatabaseMissing('inventario_movimientos', [
            'empresa_id' => $empresaA->id,
            'referencia' => 'INGRESO-EMPRESA-ACTIVA',
        ]);

        // El stock resultante debe quedar creado bajo la empresa activa (B), no la hogar (A).
        $this->assertDatabaseHas('inventario_stock', [
            'empresa_id' => $empresaB->id,
            'producto_id' => $productoB->id,
            'bodega_id' => $bodegaB->id,
        ]);
    }

    public function test_registrar_movimiento_rechaza_producto_de_la_empresa_hogar(): void
    {
        $empresaA = $this->crearEmpresa();
        $empresaB = $this->crearEmpresa();

        $usuario = $this->crearUsuarioMultiempresa($empresaA, $empresaB);

        // Producto y bodega pertenecen a la empresa hogar (A), no a la empresa activa (B).
        $productoA = $this->crearProducto($empresaA, ['sku' => 'PROD-A-RECHAZO']);
        $bodegaA = $this->crearBodega($empresaA, ['codigo' => 'BOD-A-RECHAZO']);

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $productoA->id,
            'bodega_destino_id' => $bodegaA->id,
            'cantidad' => 10,
            'costo_unitario' => 1000,
            'referencia' => 'NO-DEBE-CREARSE',
            'motivo' => MovimientoInventario::MOTIVO_INGRESO_MANUAL,
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseMissing('inventario_movimientos', [
            'referencia' => 'NO-DEBE-CREARSE',
        ]);
    }

    public function test_kardex_general_usa_empresa_activa_no_empresa_hogar(): void
    {
        $empresaA = $this->crearEmpresa();
        $empresaB = $this->crearEmpresa();

        $usuario = $this->crearUsuarioMultiempresa($empresaA, $empresaB);

        $productoA = $this->crearProducto($empresaA, ['sku' => 'PROD-A-KDX']);
        $bodegaA = $this->crearBodega($empresaA, ['codigo' => 'BOD-A-KDX']);
        MovimientoInventario::create([
            'empresa_id' => $empresaA->id,
            'producto_id' => $productoA->id,
            'bodega_destino_id' => $bodegaA->id,
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'cantidad' => 3,
            'costo_unitario' => 100,
            'valor_total' => 300,
            'referencia' => 'KDX-EMPRESA-A',
        ]);

        $productoB = $this->crearProducto($empresaB, ['sku' => 'PROD-B-KDX']);
        $bodegaB = $this->crearBodega($empresaB, ['codigo' => 'BOD-B-KDX']);
        MovimientoInventario::create([
            'empresa_id' => $empresaB->id,
            'producto_id' => $productoB->id,
            'bodega_destino_id' => $bodegaB->id,
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'cantidad' => 6,
            'costo_unitario' => 200,
            'valor_total' => 1200,
            'referencia' => 'KDX-EMPRESA-B',
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->getJson('/api/inventario/kardex');

        $response->assertOk()->assertJsonPath('pagination.total', 1);
        $this->assertSame('KDX-EMPRESA-B', $response->json('data.0.referencia'));
    }

    public function test_kardex_producto_usa_empresa_activa_no_empresa_hogar(): void
    {
        $empresaA = $this->crearEmpresa();
        $empresaB = $this->crearEmpresa();

        $usuario = $this->crearUsuarioMultiempresa($empresaA, $empresaB);

        // Mismo SKU intencionalmente para simular productos homónimos en cada empresa.
        $productoA = $this->crearProducto($empresaA, ['sku' => 'PROD-DUP', 'nombre' => 'Producto Empresa A']);
        $productoB = $this->crearProducto($empresaB, ['sku' => 'PROD-DUP', 'nombre' => 'Producto Empresa B']);

        Sanctum::actingAs($usuario);

        // El id del producto de la empresa activa (B) debe responder OK.
        $response = $this->getJson('/api/inventario/productos/' . $productoB->id . '/kardex');
        $response->assertOk();

        // El id del producto que pertenece a la empresa hogar (A) no debe ser accesible
        // aunque el usuario tenga esa empresa como "hogar".
        $responseCruzada = $this->getJson('/api/inventario/productos/' . $productoA->id . '/kardex');
        $responseCruzada->assertUnprocessable();
    }

    public function test_valorizacion_general_usa_empresa_activa_no_empresa_hogar(): void
    {
        $empresaA = $this->crearEmpresa();
        $empresaB = $this->crearEmpresa();

        $usuario = $this->crearUsuarioMultiempresa($empresaA, $empresaB);

        $productoA = $this->crearProducto($empresaA, ['sku' => 'PROD-A-VAL', 'costo_promedio' => 100]);
        $bodegaA = $this->crearBodega($empresaA, ['codigo' => 'BOD-A-VAL']);
        $this->crearStock($empresaA, $productoA, $bodegaA, 5, 100);

        $productoB = $this->crearProducto($empresaB, ['sku' => 'PROD-B-VAL', 'costo_promedio' => 200]);
        $bodegaB = $this->crearBodega($empresaB, ['codigo' => 'BOD-B-VAL']);
        $this->crearStock($empresaB, $productoB, $bodegaB, 9, 200);

        Sanctum::actingAs($usuario);

        $response = $this->getJson('/api/inventario/valorizacion');

        $response->assertOk()->assertJsonPath('pagination.total', 1);
        $this->assertSame('PROD-B-VAL', $response->json('data.0.producto.sku'));
    }

    public function test_valorizacion_producto_usa_empresa_activa_no_empresa_hogar(): void
    {
        $empresaA = $this->crearEmpresa();
        $empresaB = $this->crearEmpresa();

        $usuario = $this->crearUsuarioMultiempresa($empresaA, $empresaB);

        $productoA = $this->crearProducto($empresaA, ['sku' => 'PROD-A-VALP', 'costo_promedio' => 100]);
        $bodegaA = $this->crearBodega($empresaA, ['codigo' => 'BOD-A-VALP']);
        $this->crearStock($empresaA, $productoA, $bodegaA, 5, 100);

        $productoB = $this->crearProducto($empresaB, ['sku' => 'PROD-B-VALP', 'costo_promedio' => 200]);
        $bodegaB = $this->crearBodega($empresaB, ['codigo' => 'BOD-B-VALP']);
        $this->crearStock($empresaB, $productoB, $bodegaB, 9, 200);

        Sanctum::actingAs($usuario);

        // Consultar la valorización de un producto de la empresa hogar (A) no debe filtrar sus datos:
        // el filtro producto_id se combina con la empresa activa (B), por lo que no debe existir match.
        $responseCruzada = $this->getJson('/api/inventario/productos/' . $productoA->id . '/valorizacion');
        $responseCruzada->assertOk()->assertJsonPath('pagination.total', 0);

        $responseCorrecta = $this->getJson('/api/inventario/productos/' . $productoB->id . '/valorizacion');
        $responseCorrecta->assertOk()->assertJsonPath('pagination.total', 1);
    }

    public function test_crear_reserva_se_guarda_en_empresa_activa_no_en_empresa_hogar(): void
    {
        $empresaA = $this->crearEmpresa();
        $empresaB = $this->crearEmpresa();

        $usuario = $this->crearUsuarioMultiempresa($empresaA, $empresaB);

        $productoB = $this->crearProducto($empresaB, ['sku' => 'PROD-B-RES']);
        $bodegaB = $this->crearBodega($empresaB, ['codigo' => 'BOD-B-RES']);
        $this->crearStock($empresaB, $productoB, $bodegaB, 10, 100);

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/inventario/reservas', [
            'referencia' => 'PED-EMPRESA-ACTIVA',
            'motivo' => 'reserva_comercial',
            'detalles' => [
                [
                    'producto_id' => $productoB->id,
                    'bodega_id' => $bodegaB->id,
                    'cantidad' => 2,
                ],
            ],
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('inventario_reservas', [
            'empresa_id' => $empresaB->id,
            'referencia' => 'PED-EMPRESA-ACTIVA',
        ]);
        $this->assertDatabaseMissing('inventario_reservas', [
            'empresa_id' => $empresaA->id,
            'referencia' => 'PED-EMPRESA-ACTIVA',
        ]);
    }

    public function test_listar_despachos_usa_empresa_activa_no_empresa_hogar(): void
    {
        $empresaA = $this->crearEmpresa();
        $empresaB = $this->crearEmpresa();

        $usuario = $this->crearUsuarioMultiempresa($empresaA, $empresaB);

        $bodegaA = $this->crearBodega($empresaA, ['codigo' => 'BOD-A-DESP']);
        $pickingA = InventarioPickingOrden::create([
            'empresa_id' => $empresaA->id,
            'bodega_id' => $bodegaA->id,
            'codigo' => 'PICK-A-001',
            'estado' => InventarioPickingOrden::ESTADO_PENDIENTE,
            'prioridad' => InventarioPickingOrden::PRIORIDAD_NORMAL,
            'fecha_creacion' => now(),
        ]);
        $packingA = InventarioPackingOrden::create([
            'empresa_id' => $empresaA->id,
            'picking_orden_id' => $pickingA->id,
            'bodega_id' => $bodegaA->id,
            'codigo' => 'PACK-A-001',
            'estado' => InventarioPackingOrden::ESTADO_PENDIENTE,
            'fecha_creacion' => now(),
        ]);
        InventarioDespachoOrden::create([
            'empresa_id' => $empresaA->id,
            'packing_orden_id' => $packingA->id,
            'picking_orden_id' => $pickingA->id,
            'bodega_id' => $bodegaA->id,
            'codigo' => 'DESP-A-001',
            'estado' => InventarioDespachoOrden::ESTADO_PENDIENTE,
            'prioridad' => InventarioDespachoOrden::PRIORIDAD_NORMAL,
            'referencia' => 'DESPACHO-EMPRESA-A',
            'fecha_creacion' => now(),
        ]);

        $bodegaB = $this->crearBodega($empresaB, ['codigo' => 'BOD-B-DESP']);
        $pickingB = InventarioPickingOrden::create([
            'empresa_id' => $empresaB->id,
            'bodega_id' => $bodegaB->id,
            'codigo' => 'PICK-B-001',
            'estado' => InventarioPickingOrden::ESTADO_PENDIENTE,
            'prioridad' => InventarioPickingOrden::PRIORIDAD_NORMAL,
            'fecha_creacion' => now(),
        ]);
        $packingB = InventarioPackingOrden::create([
            'empresa_id' => $empresaB->id,
            'picking_orden_id' => $pickingB->id,
            'bodega_id' => $bodegaB->id,
            'codigo' => 'PACK-B-001',
            'estado' => InventarioPackingOrden::ESTADO_PENDIENTE,
            'fecha_creacion' => now(),
        ]);
        InventarioDespachoOrden::create([
            'empresa_id' => $empresaB->id,
            'packing_orden_id' => $packingB->id,
            'picking_orden_id' => $pickingB->id,
            'bodega_id' => $bodegaB->id,
            'codigo' => 'DESP-B-001',
            'estado' => InventarioDespachoOrden::ESTADO_PENDIENTE,
            'prioridad' => InventarioDespachoOrden::PRIORIDAD_NORMAL,
            'referencia' => 'DESPACHO-EMPRESA-B',
            'fecha_creacion' => now(),
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->getJson('/api/inventario/despachos');

        $response->assertOk()->assertJsonPath('pagination.total', 1);
        $this->assertSame('DESPACHO-EMPRESA-B', $response->json('data.0.referencia'));
    }

    public function test_crear_picking_se_guarda_en_empresa_activa_no_en_empresa_hogar(): void
    {
        $empresaA = $this->crearEmpresa();
        $empresaB = $this->crearEmpresa();

        $usuario = $this->crearUsuarioMultiempresa($empresaA, $empresaB);

        $productoB = $this->crearProducto($empresaB, ['sku' => 'PROD-B-PICK']);
        $bodegaB = $this->crearBodega($empresaB, ['codigo' => 'BOD-B-PICK']);

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/inventario/picking', [
            'bodega_id' => $bodegaB->id,
            'referencia' => 'PICKING-EMPRESA-ACTIVA',
            'detalles' => [
                [
                    'producto_id' => $productoB->id,
                    'cantidad' => 1,
                ],
            ],
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('inventario_picking_ordenes', [
            'empresa_id' => $empresaB->id,
            'referencia' => 'PICKING-EMPRESA-ACTIVA',
        ]);
        $this->assertDatabaseMissing('inventario_picking_ordenes', [
            'empresa_id' => $empresaA->id,
            'referencia' => 'PICKING-EMPRESA-ACTIVA',
        ]);
    }

    public function test_reporte_reservas_usa_empresa_activa_no_empresa_hogar(): void
    {
        $empresaA = $this->crearEmpresa();
        $empresaB = $this->crearEmpresa();

        $usuario = $this->crearUsuarioMultiempresa($empresaA, $empresaB);

        ReservaInventario::create([
            'empresa_id' => $empresaA->id,
            'codigo_reserva' => 'RES-A-REP',
            'estado' => ReservaInventario::ESTADO_ACTIVA,
            'referencia' => 'REPORTE-EMPRESA-A',
            'motivo' => 'reserva_comercial',
            'reservado_por' => $usuario->id,
            'fecha_reserva' => now(),
        ]);

        ReservaInventario::create([
            'empresa_id' => $empresaB->id,
            'codigo_reserva' => 'RES-B-REP',
            'estado' => ReservaInventario::ESTADO_ACTIVA,
            'referencia' => 'REPORTE-EMPRESA-B',
            'motivo' => 'reserva_comercial',
            'reservado_por' => $usuario->id,
            'fecha_reserva' => now(),
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->getJson('/api/inventario/reportes/reservas');

        $response->assertOk();
        $referencias = collect($response->json('data'))->pluck('referencia');

        $this->assertTrue($referencias->contains('REPORTE-EMPRESA-B'));
        $this->assertFalse($referencias->contains('REPORTE-EMPRESA-A'));
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function permisosCompletos(): array
    {
        return [
            'inventario.productos.ver',
            'inventario.productos.crear',
            'inventario.bodegas.ver',
            'inventario.bodegas.crear',
            'inventario.movimientos.ver',
            'inventario.movimientos.entrada',
            'inventario.movimientos.salida',
            'inventario.movimientos.traspaso',
            'inventario.movimientos.ajuste',
            'inventario.kardex.ver',
            'inventario.valorizacion.ver',
            'inventario.reservas.ver',
            'inventario.reservas.crear',
            'inventario.disponibilidad.ver',
            'inventario.despachos.ver',
            'inventario.picking.ver',
            'inventario.picking.crear',
            'inventario.reportes.ver',
        ];
    }

    private function crearEmpresa(): Empresa
    {
        return Empresa::create([
            'rut' => $this->rutUnico(),
            'razon_social' => 'Empresa Multitenant Inventario ' . uniqid(),
        ]);
    }

    private function crearBodega(Empresa $empresa, array $overrides = []): Bodega
    {
        return Bodega::create(array_merge([
            'empresa_id' => $empresa->id,
            'codigo' => 'BOD-' . strtoupper(substr(uniqid(), -6)),
            'nombre' => 'Bodega Multitenant Test',
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
            'nombre' => 'Producto Multitenant Test',
            'descripcion' => 'Producto para pruebas de aislamiento multiempresa',
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
