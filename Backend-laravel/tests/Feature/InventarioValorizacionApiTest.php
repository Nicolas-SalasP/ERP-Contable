<?php

namespace Tests\Feature;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\User;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\MovimientoInventario;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\StockProducto;
use App\Domains\Inventario\Models\UnidadMedida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventarioValorizacionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_contador_puede_listar_valorizacion_general(): void
    {
        [$empresa, $usuario] = $this->crearUsuarioConPermisos($this->permisosValorizacionCompleto());

        $producto = $this->crearProducto($empresa, [
            'sku' => 'PROD-VAL-GEN',
            'nombre' => 'Producto Valorizacion General',
            'costo_promedio' => 1000,
        ]);

        $bodega = $this->crearBodega($empresa, [
            'codigo' => 'BOD-VAL-GEN',
            'nombre' => 'Bodega Valorizacion General',
        ]);

        $this->crearStock($empresa, $producto, $bodega, 10, 1000);

        Sanctum::actingAs($usuario);

        $response = $this->getJson('/api/inventario/valorizacion');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('pagination.totalPages', 1)
            ->assertJsonPath('pagination.page', 1)
            ->assertJsonPath('resumen.stock_total', '10.0000')
            ->assertJsonPath('resumen.valor_total', '10000.0000')
            ->assertJsonPath('resumen.costo_promedio_global', '1000.0000')
            ->assertJsonPath('data.0.producto.sku', 'PROD-VAL-GEN')
            ->assertJsonPath('data.0.producto.nombre', 'Producto Valorizacion General')
            ->assertJsonPath('data.0.bodega.codigo', 'BOD-VAL-GEN')
            ->assertJsonPath('data.0.stock_actual', '10.0000')
            ->assertJsonPath('data.0.costo_promedio', '1000.0000')
            ->assertJsonPath('data.0.valor_total', '10000.0000');
    }

    public function test_contador_puede_consultar_valorizacion_de_un_producto(): void
    {
        [$empresa, $usuario] = $this->crearUsuarioConPermisos($this->permisosValorizacionCompleto());

        $producto = $this->crearProducto($empresa, [
            'sku' => 'PROD-VAL-PROD',
            'nombre' => 'Producto Valorizacion Producto',
            'costo_promedio' => 750,
        ]);

        $bodegaA = $this->crearBodega($empresa, [
            'codigo' => 'BOD-VAL-A',
            'nombre' => 'Bodega Valorizacion A',
        ]);

        $bodegaB = $this->crearBodega($empresa, [
            'codigo' => 'BOD-VAL-B',
            'nombre' => 'Bodega Valorizacion B',
        ]);

        $this->crearStock($empresa, $producto, $bodegaA, 4, 750);
        $this->crearStock($empresa, $producto, $bodegaB, 6, 1250);

        Sanctum::actingAs($usuario);

        $response = $this->getJson("/api/inventario/productos/{$producto->id}/valorizacion");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('pagination.total', 2)
            ->assertJsonPath('resumen.stock_total', '10.0000')
            ->assertJsonPath('resumen.valor_total', '10500.0000')
            ->assertJsonPath('resumen.costo_promedio_global', '1050.0000');

        $productos = collect($response->json('data'))
            ->pluck('producto.sku')
            ->unique()
            ->values()
            ->all();

        $this->assertSame(['PROD-VAL-PROD'], $productos);

        $bodegas = collect($response->json('data'))
            ->pluck('bodega.codigo')
            ->all();

        $this->assertContains('BOD-VAL-A', $bodegas);
        $this->assertContains('BOD-VAL-B', $bodegas);
    }

    public function test_valorizacion_filtra_por_bodega_y_search(): void
    {
        [$empresa, $usuario] = $this->crearUsuarioConPermisos($this->permisosValorizacionCompleto());

        $productoA = $this->crearProducto($empresa, [
            'sku' => 'SKU-FILTRO-A',
            'nombre' => 'Producto Filtro A',
        ]);

        $productoB = $this->crearProducto($empresa, [
            'sku' => 'SKU-FILTRO-B',
            'nombre' => 'Producto Filtro B',
        ]);

        $bodegaA = $this->crearBodega($empresa, [
            'codigo' => 'BOD-FILTRO-A',
            'nombre' => 'Bodega Filtro A',
        ]);

        $bodegaB = $this->crearBodega($empresa, [
            'codigo' => 'BOD-FILTRO-B',
            'nombre' => 'Bodega Filtro B',
        ]);

        $this->crearStock($empresa, $productoA, $bodegaA, 5, 200);
        $this->crearStock($empresa, $productoB, $bodegaB, 8, 300);

        Sanctum::actingAs($usuario);

        $response = $this->getJson("/api/inventario/valorizacion?bodega_id={$bodegaA->id}&search=SKU-FILTRO-A");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('resumen.stock_total', '5.0000')
            ->assertJsonPath('resumen.valor_total', '1000.0000')
            ->assertJsonPath('resumen.costo_promedio_global', '200.0000')
            ->assertJsonPath('data.0.producto.sku', 'SKU-FILTRO-A')
            ->assertJsonPath('data.0.bodega.codigo', 'BOD-FILTRO-A');
    }

    public function test_valorizacion_respeta_multiempresa(): void
    {
        [$empresa, $usuario] = $this->crearUsuarioConPermisos($this->permisosValorizacionCompleto());

        $otraEmpresa = $this->crearEmpresa();

        $productoPropio = $this->crearProducto($empresa, [
            'sku' => 'PROD-PROPIO-VAL',
            'nombre' => 'Producto Propio Valorizado',
        ]);

        $bodegaPropia = $this->crearBodega($empresa, [
            'codigo' => 'BOD-PROPIA-VAL',
            'nombre' => 'Bodega Propia Valorizada',
        ]);

        $productoAjeno = $this->crearProducto($otraEmpresa, [
            'sku' => 'PROD-AJENO-VAL',
            'nombre' => 'Producto Ajeno Valorizado',
        ]);

        $bodegaAjena = $this->crearBodega($otraEmpresa, [
            'codigo' => 'BOD-AJENA-VAL',
            'nombre' => 'Bodega Ajena Valorizada',
        ]);

        $this->crearStock($empresa, $productoPropio, $bodegaPropia, 10, 1000);
        $this->crearStock($otraEmpresa, $productoAjeno, $bodegaAjena, 99, 999);

        Sanctum::actingAs($usuario);

        $response = $this->getJson('/api/inventario/valorizacion');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('resumen.stock_total', '10.0000')
            ->assertJsonPath('resumen.valor_total', '10000.0000')
            ->assertJsonPath('data.0.producto.sku', 'PROD-PROPIO-VAL');

        $skus = collect($response->json('data'))
            ->pluck('producto.sku')
            ->all();

        $this->assertContains('PROD-PROPIO-VAL', $skus);
        $this->assertNotContains('PROD-AJENO-VAL', $skus);
    }

    public function test_auditor_puede_consultar_valorizacion(): void
    {
        [$empresa, $contador] = $this->crearUsuarioConPermisos($this->permisosValorizacionCompleto());

        $auditor = $this->crearUsuarioParaEmpresa($empresa, [
            'inventario.productos.ver',
            'inventario.bodegas.ver',
            'inventario.movimientos.ver',
            'inventario.kardex.ver',
            'inventario.valorizacion.ver',
        ], 'Auditor');

        $producto = $this->crearProducto($empresa, [
            'sku' => 'PROD-AUDITOR-VAL',
            'nombre' => 'Producto Auditor Valorizacion',
        ]);

        $bodega = $this->crearBodega($empresa, [
            'codigo' => 'BOD-AUDITOR-VAL',
        ]);

        Sanctum::actingAs($contador);

        $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodega->id,
            'cantidad' => 3,
            'costo_unitario' => 1500,
            'referencia' => 'VAL-AUDITOR-001',
            'motivo' => MovimientoInventario::MOTIVO_INGRESO_MANUAL,
        ])->assertCreated();

        Sanctum::actingAs($auditor);

        $response = $this->getJson('/api/inventario/valorizacion');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('resumen.stock_total', '3.0000')
            ->assertJsonPath('resumen.valor_total', '4500.0000')
            ->assertJsonPath('resumen.costo_promedio_global', '1500.0000')
            ->assertJsonPath('data.0.producto.sku', 'PROD-AUDITOR-VAL');
    }

    public function test_usuario_sin_permiso_no_puede_consultar_valorizacion(): void
    {
        [$empresa, $usuario] = $this->crearUsuarioConPermisos([
            'inventario.productos.ver',
            'inventario.bodegas.ver',
        ]);

        $producto = $this->crearProducto($empresa, [
            'sku' => 'PROD-SIN-PERMISO-VAL',
        ]);

        $bodega = $this->crearBodega($empresa);

        $this->crearStock($empresa, $producto, $bodega, 10, 100);

        Sanctum::actingAs($usuario);

        $response = $this->getJson('/api/inventario/valorizacion');

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_usuario_sin_token_no_puede_consultar_valorizacion(): void
    {
        $response = $this->getJson('/api/inventario/valorizacion');

        $response->assertStatus(401);
    }

    public function test_valorizacion_refleja_pmp_generado_por_movimientos(): void
    {
        [$empresa, $usuario] = $this->crearUsuarioConPermisos($this->permisosValorizacionCompleto());

        $producto = $this->crearProducto($empresa, [
            'sku' => 'PROD-PMP-MOV',
            'nombre' => 'Producto PMP Movimiento',
            'costo_promedio' => 0,
        ]);

        $bodega = $this->crearBodega($empresa, [
            'codigo' => 'BOD-PMP-MOV',
        ]);

        Sanctum::actingAs($usuario);

        $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodega->id,
            'cantidad' => 10,
            'costo_unitario' => 1000,
            'referencia' => 'PMP-MOV-001',
            'motivo' => MovimientoInventario::MOTIVO_INGRESO_MANUAL,
        ])->assertCreated();

        $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodega->id,
            'cantidad' => 5,
            'costo_unitario' => 1200,
            'referencia' => 'PMP-MOV-002',
            'motivo' => MovimientoInventario::MOTIVO_INGRESO_MANUAL,
        ])->assertCreated();

        $response = $this->getJson("/api/inventario/productos/{$producto->id}/valorizacion");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('resumen.stock_total', '15.0000')
            ->assertJsonPath('resumen.valor_total', '16000.0000')
            ->assertJsonPath('resumen.costo_promedio_global', '1066.6667')
            ->assertJsonPath('data.0.stock_actual', '15.0000')
            ->assertJsonPath('data.0.costo_promedio', '1066.6667')
            ->assertJsonPath('data.0.valor_total', '16000.0000');

        $producto->refresh();

        $this->assertEquals(1066.6667, (float) $producto->costo_promedio);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function permisosValorizacionCompleto(): array
    {
        return [
            /*
            |--------------------------------------------------------------------------
            | Fase 1
            |--------------------------------------------------------------------------
            */
            'inventario.productos.ver',
            'inventario.productos.crear',
            'inventario.productos.editar',
            'inventario.bodegas.ver',
            'inventario.bodegas.crear',

            /*
            |--------------------------------------------------------------------------
            | Fase 2
            |--------------------------------------------------------------------------
            */
            'inventario.movimientos.ver',
            'inventario.movimientos.entrada',
            'inventario.movimientos.salida',
            'inventario.movimientos.traspaso',
            'inventario.movimientos.ajuste',
            'inventario.kardex.ver',

            /*
            |--------------------------------------------------------------------------
            | Fase 3
            |--------------------------------------------------------------------------
            */
            'inventario.valorizacion.ver',
        ];
    }

    private function crearUsuarioConPermisos(array $permisos, string $nombreRol = 'Contador'): array
    {
        $empresa = $this->crearEmpresa();

        $usuario = $this->crearUsuarioParaEmpresa($empresa, $permisos, $nombreRol);

        return [$empresa, $usuario];
    }

    private function crearUsuarioParaEmpresa(Empresa $empresa, array $permisos, string $nombreRol = 'Contador'): User
    {
        $estado = EstadoSuscripcion::firstOrCreate([
            'nombre' => 'Activa',
        ]);

        $rol = Rol::create([
            'nombre' => $nombreRol,
            'permisos' => $permisos,
        ]);

        return User::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Usuario Inventario Valorizacion ' . uniqid(),
            'email' => 'inventario_valorizacion_' . uniqid() . '@example.com',
            'password' => Hash::make('password'),
            'rol_id' => $rol->id,
            'estado_suscripcion_id' => $estado->id,
        ]);
    }

    private function crearEmpresa(): Empresa
    {
        return Empresa::create([
            'rut' => $this->rutUnico(),
            'razon_social' => 'Empresa Inventario Valorizacion ' . uniqid(),
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
            'nombre' => 'Producto Valorizacion API Test',
            'descripcion' => 'Producto para pruebas API de valorización',
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