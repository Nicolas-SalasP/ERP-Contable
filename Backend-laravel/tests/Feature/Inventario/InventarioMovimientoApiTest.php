<?php

namespace Tests\Feature\Inventario;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\MovimientoInventario;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\StockProducto;
use App\Domains\Inventario\Models\UnidadMedida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaInventarioTrait;
use Tests\TestCase;

class InventarioMovimientoApiTest extends TestCase
{
    use RefreshDatabase;
    use PreparaInventarioTrait;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararUsuariosInventarioDemo();
    }

    public function test_contador_puede_registrar_entrada_y_aumenta_stock(): void
    {
        [$empresa, $usuario] = $this->crearUsuarioConPermisos($this->permisosMovimientoCompleto());

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodega->id,
            'cantidad' => 10,
            'costo_unitario' => 1000,
            'referencia' => 'INGRESO-TEST-001',
            'motivo' => MovimientoInventario::MOTIVO_INGRESO_MANUAL,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tipo', MovimientoInventario::TIPO_ENTRADA)
            ->assertJsonPath('data.referencia', 'INGRESO-TEST-001');

        $stock = $this->stock($empresa, $producto, $bodega);

        $this->assertEquals(10.0, (float) $stock->stock_actual);
        $this->assertEquals(1000.0, (float) $stock->costo_promedio);
        $this->assertEquals(10000.0, (float) $stock->valor_total);

        $this->assertDatabaseHas('inventario_movimientos', [
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'bodega_destino_id' => $bodega->id,
            'referencia' => 'INGRESO-TEST-001',
        ]);
    }

    public function test_contador_puede_registrar_salida_y_descuenta_stock(): void
    {
        [$empresa, $usuario] = $this->crearUsuarioConPermisos($this->permisosMovimientoCompleto());

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        $this->crearStock($empresa, $producto, $bodega, 10, 500);

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_SALIDA,
            'producto_id' => $producto->id,
            'bodega_origen_id' => $bodega->id,
            'cantidad' => 3,
            'referencia' => 'SALIDA-TEST-001',
            'motivo' => MovimientoInventario::MOTIVO_EGRESO_MANUAL,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tipo', MovimientoInventario::TIPO_SALIDA);

        $stock = $this->stock($empresa, $producto, $bodega);

        $this->assertEquals(7.0, (float) $stock->stock_actual);
        $this->assertEquals(500.0, (float) $stock->costo_promedio);
        $this->assertEquals(3500.0, (float) $stock->valor_total);

        $this->assertDatabaseHas('inventario_movimientos', [
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'tipo' => MovimientoInventario::TIPO_SALIDA,
            'bodega_origen_id' => $bodega->id,
        ]);
    }

    public function test_no_permite_salida_con_stock_insuficiente(): void
    {
        [$empresa, $usuario] = $this->crearUsuarioConPermisos($this->permisosMovimientoCompleto());

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        $this->crearStock($empresa, $producto, $bodega, 2, 500);

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_SALIDA,
            'producto_id' => $producto->id,
            'bodega_origen_id' => $bodega->id,
            'cantidad' => 5,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $stock = $this->stock($empresa, $producto, $bodega);

        $this->assertEquals(2.0, (float) $stock->stock_actual);

        $this->assertDatabaseMissing('inventario_movimientos', [
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'tipo' => MovimientoInventario::TIPO_SALIDA,
        ]);
    }

    public function test_contador_puede_registrar_traspaso_y_mueve_stock_entre_bodegas(): void
    {
        [$empresa, $usuario] = $this->crearUsuarioConPermisos($this->permisosMovimientoCompleto());

        $producto = $this->crearProducto($empresa);
        $bodegaOrigen = $this->crearBodega($empresa, ['codigo' => 'BOD-ORI']);
        $bodegaDestino = $this->crearBodega($empresa, ['codigo' => 'BOD-DES']);

        $this->crearStock($empresa, $producto, $bodegaOrigen, 10, 250);
        $this->crearStock($empresa, $producto, $bodegaDestino, 2, 100);

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_TRASPASO,
            'producto_id' => $producto->id,
            'bodega_origen_id' => $bodegaOrigen->id,
            'bodega_destino_id' => $bodegaDestino->id,
            'cantidad' => 4,
            'referencia' => 'TRASPASO-TEST-001',
            'motivo' => MovimientoInventario::MOTIVO_TRASPASO_BODEGA,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tipo', MovimientoInventario::TIPO_TRASPASO);

        $stockOrigen = $this->stock($empresa, $producto, $bodegaOrigen);
        $stockDestino = $this->stock($empresa, $producto, $bodegaDestino);

        $this->assertEquals(6.0, (float) $stockOrigen->stock_actual);
        $this->assertEquals(6.0, (float) $stockDestino->stock_actual);

        $this->assertDatabaseHas('inventario_movimientos', [
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'tipo' => MovimientoInventario::TIPO_TRASPASO,
            'bodega_origen_id' => $bodegaOrigen->id,
            'bodega_destino_id' => $bodegaDestino->id,
        ]);
    }

    public function test_no_permite_traspaso_a_la_misma_bodega(): void
    {
        [$empresa, $usuario] = $this->crearUsuarioConPermisos($this->permisosMovimientoCompleto());

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        $this->crearStock($empresa, $producto, $bodega, 10, 250);

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_TRASPASO,
            'producto_id' => $producto->id,
            'bodega_origen_id' => $bodega->id,
            'bodega_destino_id' => $bodega->id,
            'cantidad' => 2,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $stock = $this->stock($empresa, $producto, $bodega);

        $this->assertEquals(10.0, (float) $stock->stock_actual);
    }

    public function test_ajuste_positivo_aumenta_stock(): void
    {
        [$empresa, $usuario] = $this->crearUsuarioConPermisos($this->permisosMovimientoCompleto());

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        $this->crearStock($empresa, $producto, $bodega, 5, 100);

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_AJUSTE_POSITIVO,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodega->id,
            'cantidad' => 2,
            'costo_unitario' => 150,
            'motivo' => MovimientoInventario::MOTIVO_CORRECCION_STOCK,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tipo', MovimientoInventario::TIPO_AJUSTE_POSITIVO);

        $stock = $this->stock($empresa, $producto, $bodega);

        $this->assertEquals(7.0, (float) $stock->stock_actual);

        $this->assertDatabaseHas('inventario_movimientos', [
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'tipo' => MovimientoInventario::TIPO_AJUSTE_POSITIVO,
            'motivo' => MovimientoInventario::MOTIVO_CORRECCION_STOCK,
        ]);
    }

    public function test_ajuste_negativo_descuenta_stock_y_puede_registrar_merma(): void
    {
        [$empresa, $usuario] = $this->crearUsuarioConPermisos($this->permisosMovimientoCompleto());

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        $this->crearStock($empresa, $producto, $bodega, 5, 100);

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_AJUSTE_NEGATIVO,
            'producto_id' => $producto->id,
            'bodega_origen_id' => $bodega->id,
            'cantidad' => 2,
            'motivo' => MovimientoInventario::MOTIVO_MERMA,
            'observacion' => 'Producto dañado en bodega',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tipo', MovimientoInventario::TIPO_AJUSTE_NEGATIVO)
            ->assertJsonPath('data.motivo', MovimientoInventario::MOTIVO_MERMA);

        $stock = $this->stock($empresa, $producto, $bodega);

        $this->assertEquals(3.0, (float) $stock->stock_actual);

        $this->assertDatabaseHas('inventario_movimientos', [
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'tipo' => MovimientoInventario::TIPO_AJUSTE_NEGATIVO,
            'motivo' => MovimientoInventario::MOTIVO_MERMA,
        ]);
    }

    public function test_no_permite_cantidad_cero_ni_negativa(): void
    {
        [$empresa, $usuario] = $this->crearUsuarioConPermisos($this->permisosMovimientoCompleto());

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        Sanctum::actingAs($usuario);

        $responseCero = $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodega->id,
            'cantidad' => 0,
        ]);

        $responseCero->assertStatus(422)
            ->assertJsonPath('success', false);

        $responseNegativa = $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodega->id,
            'cantidad' => -1,
        ]);

        $responseNegativa->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('inventario_movimientos', [
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
        ]);
    }

    public function test_no_permite_mover_producto_inactivo(): void
    {
        [$empresa, $usuario] = $this->crearUsuarioConPermisos($this->permisosMovimientoCompleto());

        $producto = $this->crearProducto($empresa, ['activo' => false]);
        $bodega = $this->crearBodega($empresa);

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodega->id,
            'cantidad' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('inventario_movimientos', [
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
        ]);
    }

    public function test_no_permite_mover_hacia_bodega_inactiva(): void
    {
        [$empresa, $usuario] = $this->crearUsuarioConPermisos($this->permisosMovimientoCompleto());

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa, ['estado' => 'INACTIVA']);

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodega->id,
            'cantidad' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('inventario_movimientos', [
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
        ]);
    }

    public function test_no_permite_mover_producto_de_otra_empresa(): void
    {
        [$empresa, $usuario] = $this->crearUsuarioConPermisos($this->permisosMovimientoCompleto());

        $otraEmpresa = $this->crearEmpresa();

        $productoOtraEmpresa = $this->crearProducto($otraEmpresa);
        $bodega = $this->crearBodega($empresa);

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $productoOtraEmpresa->id,
            'bodega_destino_id' => $bodega->id,
            'cantidad' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('inventario_movimientos', [
            'empresa_id' => $empresa->id,
            'producto_id' => $productoOtraEmpresa->id,
        ]);
    }

    public function test_no_permite_mover_en_bodega_de_otra_empresa(): void
    {
        [$empresa, $usuario] = $this->crearUsuarioConPermisos($this->permisosMovimientoCompleto());

        $otraEmpresa = $this->crearEmpresa();

        $producto = $this->crearProducto($empresa);
        $bodegaOtraEmpresa = $this->crearBodega($otraEmpresa);

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodegaOtraEmpresa->id,
            'cantidad' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('inventario_movimientos', [
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodegaOtraEmpresa->id,
        ]);
    }

    public function test_auditor_no_puede_registrar_movimientos(): void
    {
        [$empresa, $usuario] = $this->crearUsuarioConPermisos([
            'inventario.movimientos.ver',
            'inventario.kardex.ver',
        ], 'Auditor');

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodega->id,
            'cantidad' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('inventario_movimientos', [
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
        ]);
    }

    public function test_auditor_puede_listar_movimientos_y_ver_kardex(): void
    {
        [$empresa, $contador] = $this->crearUsuarioConPermisos($this->permisosMovimientoCompleto());
        $auditor = $this->crearUsuarioParaEmpresa($empresa, [
            'inventario.movimientos.ver',
            'inventario.kardex.ver',
        ], 'Auditor');

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        Sanctum::actingAs($contador);

        $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodega->id,
            'cantidad' => 3,
            'referencia' => 'AUDITOR-KARDEX-001',
        ])->assertCreated();

        Sanctum::actingAs($auditor);

        $movimientos = $this->getJson('/api/inventario/movimientos');

        $movimientos->assertOk()
            ->assertJsonPath('success', true);

        $referenciasMovimientos = collect($movimientos->json('data'))->pluck('referencia')->all();

        $this->assertContains('AUDITOR-KARDEX-001', $referenciasMovimientos);

        $kardex = $this->getJson("/api/inventario/productos/{$producto->id}/kardex");

        $kardex->assertOk()
            ->assertJsonPath('success', true);

        $referenciasKardex = collect($kardex->json('data'))->pluck('referencia')->all();

        $this->assertContains('AUDITOR-KARDEX-001', $referenciasKardex);
    }

    public function test_kardex_respeta_multiempresa(): void
    {
        [$empresa, $usuario] = $this->crearUsuarioConPermisos($this->permisosMovimientoCompleto());

        $otraEmpresa = $this->crearEmpresa();

        $productoPropio = $this->crearProducto($empresa, ['sku' => 'PROD-PROPIO-KARDEX']);
        $bodegaPropia = $this->crearBodega($empresa, ['codigo' => 'BOD-PROPIA-KARDEX']);

        $productoAjeno = $this->crearProducto($otraEmpresa, ['sku' => 'PROD-AJENO-KARDEX']);
        $bodegaAjena = $this->crearBodega($otraEmpresa, ['codigo' => 'BOD-AJENA-KARDEX']);

        MovimientoInventario::create([
            'empresa_id' => $empresa->id,
            'producto_id' => $productoPropio->id,
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'bodega_destino_id' => $bodegaPropia->id,
            'cantidad' => 5,
            'stock_destino_antes' => 0,
            'stock_destino_despues' => 5,
            'referencia' => 'MOV-PROPIO',
            'fecha_movimiento' => now(),
        ]);

        MovimientoInventario::create([
            'empresa_id' => $otraEmpresa->id,
            'producto_id' => $productoAjeno->id,
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'bodega_destino_id' => $bodegaAjena->id,
            'cantidad' => 10,
            'stock_destino_antes' => 0,
            'stock_destino_despues' => 10,
            'referencia' => 'MOV-AJENO',
            'fecha_movimiento' => now(),
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->getJson("/api/inventario/productos/{$productoPropio->id}/kardex");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $referencias = collect($response->json('data'))->pluck('referencia')->all();

        $this->assertContains('MOV-PROPIO', $referencias);
        $this->assertNotContains('MOV-AJENO', $referencias);

        $responseAjeno = $this->getJson("/api/inventario/productos/{$productoAjeno->id}/kardex");

        $responseAjeno->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_no_permite_acceder_a_movimientos_sin_token(): void
    {
        $response = $this->getJson('/api/inventario/movimientos');

        $response->assertStatus(401);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function permisosMovimientoCompleto(): array
    {
        return [
            'inventario.productos.ver',
            'inventario.productos.crear',
            'inventario.productos.editar',
            'inventario.bodegas.ver',
            'inventario.bodegas.crear',

            'inventario.movimientos.ver',
            'inventario.movimientos.entrada',
            'inventario.movimientos.salida',
            'inventario.movimientos.traspaso',
            'inventario.movimientos.ajuste',
            'inventario.kardex.ver',
        ];
    }

    private function crearUsuarioConPermisos(array $permisos, string $nombreRol = 'Contador'): array
    {
        if ($nombreRol === 'Auditor') {
            return $this->usuarioAuditorConPermisos($permisos);
        }

        if ($nombreRol === 'Administrador') {
            return $this->usuarioAdministradorSeeder();
        }

        return $this->usuarioContadorConPermisos($permisos);
    }

    private function crearUsuarioParaEmpresa(Empresa $empresa, array $permisos, string $nombreRol = 'Contador'): User
    {
        if ($nombreRol === 'Auditor') {
            [, $usuario] = $this->usuarioAuditorConPermisos($permisos);
        } elseif ($nombreRol === 'Administrador') {
            [, $usuario] = $this->usuarioAdministradorSeeder();
        } else {
            [, $usuario] = $this->usuarioContadorConPermisos($permisos);
        }

        if ((int) $usuario->empresa_id !== (int) $empresa->id) {
            $usuario->update([
                'empresa_id' => $empresa->id,
            ]);

            $usuario->refresh();
            $usuario->load('rol');
        }

        return $usuario;
    }

    private function crearEmpresa(): Empresa
    {
        return Empresa::create([
            'rut' => $this->rutUnico(),
            'razon_social' => 'Empresa Inventario ' . uniqid(),
        ]);
    }

    private function crearBodega(Empresa $empresa, array $overrides = []): Bodega
    {
        return Bodega::create(array_merge([
            'empresa_id' => $empresa->id,
            'codigo' => 'BOD-' . strtoupper(substr(uniqid(), -6)),
            'nombre' => 'Bodega Test',
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
            'nombre' => 'Producto Movimiento Test',
            'descripcion' => 'Producto para pruebas de movimientos',
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

    private function stock(Empresa $empresa, Producto $producto, Bodega $bodega): StockProducto
    {
        return StockProducto::query()
            ->where('empresa_id', $empresa->id)
            ->where('producto_id', $producto->id)
            ->where('bodega_id', $bodega->id)
            ->firstOrFail();
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