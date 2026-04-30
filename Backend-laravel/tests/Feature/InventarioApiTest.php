<?php

namespace Tests\Feature;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\User;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\StockProducto;
use App\Domains\Inventario\Models\UnidadMedida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventarioApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogos_de_inventario_responden_correctamente(): void
    {
        [$empresa, $usuario] = $this->crearUsuarioConPermisos([
            'inventario.productos.ver',
            'inventario.bodegas.ver',
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->getJson('/api/inventario/catalogos');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'unidades_medida',
                    'bodegas',
                    'tipos_producto',
                    'metodos_valorizacion',
                ],
            ]);

        $this->assertContains('PMP', $response->json('data.metodos_valorizacion'));
        $this->assertContains('FIFO', $response->json('data.metodos_valorizacion'));
    }

    public function test_contador_puede_crear_bodega_de_inventario(): void
    {
        [$empresa, $usuario] = $this->crearUsuarioConPermisos([
            'inventario.bodegas.ver',
            'inventario.bodegas.crear',
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/inventario/bodegas', [
            'codigo' => 'BOD-001',
            'nombre' => 'Bodega Central',
            'direccion' => 'Santiago, Chile',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.codigo', 'BOD-001')
            ->assertJsonPath('data.nombre', 'Bodega Central');

        $this->assertDatabaseHas('inventario_bodegas', [
            'empresa_id' => $empresa->id,
            'codigo' => 'BOD-001',
            'nombre' => 'Bodega Central',
        ]);
    }

    public function test_no_permite_crear_bodega_con_codigo_duplicado_en_misma_empresa(): void
    {
        [$empresa, $usuario] = $this->crearUsuarioConPermisos([
            'inventario.bodegas.ver',
            'inventario.bodegas.crear',
        ]);

        Bodega::create([
            'empresa_id' => $empresa->id,
            'codigo' => 'BOD-DUP',
            'nombre' => 'Bodega existente',
            'estado' => 'ACTIVA',
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/inventario/bodegas', [
            'codigo' => 'BOD-DUP',
            'nombre' => 'Bodega duplicada',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_contador_puede_crear_producto_de_inventario(): void
    {
        [$empresa, $usuario] = $this->crearUsuarioConPermisos([
            'inventario.productos.ver',
            'inventario.productos.crear',
            'inventario.bodegas.ver',
            'inventario.bodegas.crear',
        ]);

        $unidad = $this->obtenerUnidadBase();

        $bodega = Bodega::create([
            'empresa_id' => $empresa->id,
            'codigo' => 'BOD-PROD',
            'nombre' => 'Bodega Producto',
            'estado' => 'ACTIVA',
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/inventario/productos', $this->payloadProducto($unidad, [
            'sku' => 'PROD-001',
            'nombre' => 'Producto de prueba',
            'bodega_defecto_id' => $bodega->id,
        ]));

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sku', 'PROD-001')
            ->assertJsonPath('data.nombre', 'Producto de prueba')
            ->assertJsonPath('data.metodo_valorizacion', 'PMP');

        $this->assertDatabaseHas('inventario_productos', [
            'empresa_id' => $empresa->id,
            'sku' => 'PROD-001',
        ]);

        $productoId = $response->json('data.id');

        $this->assertDatabaseHas('inventario_stock', [
            'empresa_id' => $empresa->id,
            'producto_id' => $productoId,
            'bodega_id' => $bodega->id,
            'stock_actual' => 0,
            'valor_total' => 0,
        ]);
    }

    public function test_auditor_no_puede_crear_producto(): void
    {
        [$empresa, $usuario] = $this->crearUsuarioConPermisos([
            'inventario.productos.ver',
        ], 'Auditor');

        $unidad = $this->obtenerUnidadBase();

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/inventario/productos', $this->payloadProducto($unidad, [
            'sku' => 'PROD-AUDITOR',
            'nombre' => 'Producto bloqueado',
        ]));

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('inventario_productos', [
            'empresa_id' => $empresa->id,
            'sku' => 'PROD-AUDITOR',
        ]);
    }

    public function test_no_permite_crear_producto_con_valores_negativos(): void
    {
        [$empresa, $usuario] = $this->crearUsuarioConPermisos([
            'inventario.productos.ver',
            'inventario.productos.crear',
        ]);

        $unidad = $this->obtenerUnidadBase();

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/inventario/productos', $this->payloadProducto($unidad, [
            'sku' => 'PROD-NEG-001',
            'nombre' => 'Producto con valores negativos',
            'costo_promedio' => -100,
            'precio_venta_neto' => -1000,
            'stock_minimo' => -5,
        ]));

        $response->assertStatus(422);

        $this->assertDatabaseMissing('inventario_productos', [
            'empresa_id' => $empresa->id,
            'sku' => 'PROD-NEG-001',
        ]);
    }

    public function test_no_permite_crear_producto_con_sku_duplicado_en_misma_empresa(): void
    {
        [$empresa, $usuario] = $this->crearUsuarioConPermisos([
            'inventario.productos.ver',
            'inventario.productos.crear',
        ]);

        $unidad = $this->obtenerUnidadBase();

        Producto::create([
            'empresa_id' => $empresa->id,
            'sku' => 'SKU-DUP-001',
            'nombre' => 'Producto existente',
            'tipo_producto' => 'BIEN',
            'unidad_medida_id' => $unidad->id,
            'metodo_valorizacion' => 'PMP',
            'costo_promedio' => 0,
            'precio_venta_neto' => 10000,
            'afecto_iva' => true,
            'stock_minimo' => 0,
            'permite_merma' => false,
            'activo' => true,
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/inventario/productos', $this->payloadProducto($unidad, [
            'sku' => 'SKU-DUP-001',
            'nombre' => 'Producto duplicado',
        ]));

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_permite_mismo_sku_en_empresas_distintas(): void
    {
        [$empresa, $usuario] = $this->crearUsuarioConPermisos([
            'inventario.productos.ver',
            'inventario.productos.crear',
        ]);

        $unidad = $this->obtenerUnidadBase();

        $otraEmpresa = Empresa::create([
            'rut' => '77000000-0',
            'razon_social' => 'Otra Empresa SpA',
        ]);

        Producto::create([
            'empresa_id' => $otraEmpresa->id,
            'sku' => 'SKU-COMPARTIDO',
            'nombre' => 'Producto otra empresa',
            'tipo_producto' => 'BIEN',
            'unidad_medida_id' => $unidad->id,
            'metodo_valorizacion' => 'PMP',
            'costo_promedio' => 0,
            'precio_venta_neto' => 10000,
            'afecto_iva' => true,
            'stock_minimo' => 0,
            'permite_merma' => false,
            'activo' => true,
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/inventario/productos', $this->payloadProducto($unidad, [
            'sku' => 'SKU-COMPARTIDO',
            'nombre' => 'Producto empresa actual',
        ]));

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sku', 'SKU-COMPARTIDO');

        $this->assertDatabaseHas('inventario_productos', [
            'empresa_id' => $empresa->id,
            'sku' => 'SKU-COMPARTIDO',
        ]);

        $this->assertDatabaseHas('inventario_productos', [
            'empresa_id' => $otraEmpresa->id,
            'sku' => 'SKU-COMPARTIDO',
        ]);
    }

    public function test_no_permite_crear_producto_con_unidad_inexistente(): void
    {
        [$empresa, $usuario] = $this->crearUsuarioConPermisos([
            'inventario.productos.ver',
            'inventario.productos.crear',
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/inventario/productos', [
            'sku' => 'PROD-UM-404',
            'nombre' => 'Producto unidad inválida',
            'tipo_producto' => 'BIEN',
            'unidad_medida_id' => 999999,
            'metodo_valorizacion' => 'PMP',
            'precio_venta_neto' => 10000,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('inventario_productos', [
            'empresa_id' => $empresa->id,
            'sku' => 'PROD-UM-404',
        ]);
    }

    public function test_no_permite_crear_producto_con_bodega_de_otra_empresa(): void
    {
        [$empresa, $usuario] = $this->crearUsuarioConPermisos([
            'inventario.productos.ver',
            'inventario.productos.crear',
        ]);

        $unidad = $this->obtenerUnidadBase();

        $otraEmpresa = Empresa::create([
            'rut' => '78000000-0',
            'razon_social' => 'Empresa Externa SpA',
        ]);

        $bodegaOtraEmpresa = Bodega::create([
            'empresa_id' => $otraEmpresa->id,
            'codigo' => 'BOD-EXT',
            'nombre' => 'Bodega externa',
            'estado' => 'ACTIVA',
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/inventario/productos', $this->payloadProducto($unidad, [
            'sku' => 'PROD-BOD-EXT',
            'nombre' => 'Producto con bodega externa',
            'bodega_defecto_id' => $bodegaOtraEmpresa->id,
        ]));

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('inventario_productos', [
            'empresa_id' => $empresa->id,
            'sku' => 'PROD-BOD-EXT',
        ]);
    }

    public function test_listar_productos_solo_muestra_productos_de_la_empresa_autenticada(): void
    {
        [$empresa, $usuario] = $this->crearUsuarioConPermisos([
            'inventario.productos.ver',
        ]);

        $unidad = $this->obtenerUnidadBase();

        $otraEmpresa = Empresa::create([
            'rut' => '79000000-0',
            'razon_social' => 'Empresa Oculta SpA',
        ]);

        Producto::create([
            'empresa_id' => $empresa->id,
            'sku' => 'PROD-PROPIO',
            'nombre' => 'Producto propio',
            'tipo_producto' => 'BIEN',
            'unidad_medida_id' => $unidad->id,
            'metodo_valorizacion' => 'PMP',
            'costo_promedio' => 0,
            'precio_venta_neto' => 10000,
            'afecto_iva' => true,
            'stock_minimo' => 0,
            'permite_merma' => false,
            'activo' => true,
        ]);

        Producto::create([
            'empresa_id' => $otraEmpresa->id,
            'sku' => 'PROD-OCULTO',
            'nombre' => 'Producto de otra empresa',
            'tipo_producto' => 'BIEN',
            'unidad_medida_id' => $unidad->id,
            'metodo_valorizacion' => 'PMP',
            'costo_promedio' => 0,
            'precio_venta_neto' => 10000,
            'afecto_iva' => true,
            'stock_minimo' => 0,
            'permite_merma' => false,
            'activo' => true,
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->getJson('/api/inventario/productos');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $skus = collect($response->json('data'))->pluck('sku')->all();

        $this->assertContains('PROD-PROPIO', $skus);
        $this->assertNotContains('PROD-OCULTO', $skus);
    }

    public function test_ver_producto_no_permite_acceder_a_producto_de_otra_empresa(): void
    {
        [$empresa, $usuario] = $this->crearUsuarioConPermisos([
            'inventario.productos.ver',
        ]);

        $unidad = $this->obtenerUnidadBase();

        $otraEmpresa = Empresa::create([
            'rut' => '80000000-0',
            'razon_social' => 'Empresa Ajena SpA',
        ]);

        $productoAjeno = Producto::create([
            'empresa_id' => $otraEmpresa->id,
            'sku' => 'PROD-AJENO',
            'nombre' => 'Producto ajeno',
            'tipo_producto' => 'BIEN',
            'unidad_medida_id' => $unidad->id,
            'metodo_valorizacion' => 'PMP',
            'costo_promedio' => 0,
            'precio_venta_neto' => 10000,
            'afecto_iva' => true,
            'stock_minimo' => 0,
            'permite_merma' => false,
            'activo' => true,
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->getJson("/api/inventario/productos/{$productoAjeno->id}");

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_no_permite_acceder_a_productos_sin_token(): void
    {
        $response = $this->getJson('/api/inventario/productos');

        $response->assertStatus(401);
    }

    private function crearUsuarioConPermisos(array $permisos, string $nombreRol = 'Contador'): array
    {
        $estado = EstadoSuscripcion::firstOrCreate([
            'nombre' => 'Activa',
        ]);

        $empresa = Empresa::create([
            'rut' => $this->rutUnico(),
            'razon_social' => 'Empresa Demo ' . uniqid(),
        ]);

        $rol = Rol::create([
            'nombre' => $nombreRol,
            'permisos' => $permisos,
        ]);

        $usuario = User::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Usuario Demo',
            'email' => 'usuario_' . uniqid() . '@example.com',
            'password' => Hash::make('password'),
            'rol_id' => $rol->id,
            'estado_suscripcion_id' => $estado->id,
        ]);

        return [$empresa, $usuario, $rol];
    }

    private function obtenerUnidadBase(): UnidadMedida
    {
        return UnidadMedida::firstOrCreate(
            ['codigo' => 'UN'],
            [
                'nombre' => 'Unidad',
                'codigo_sii' => 'UN',
                'permite_decimal' => false,
                'activo' => true,
            ]
        );
    }

    private function payloadProducto(UnidadMedida $unidad, array $overrides = []): array
    {
        return array_merge([
            'sku' => 'PROD-' . uniqid(),
            'nombre' => 'Producto de prueba',
            'descripcion' => 'Producto inicial del módulo de inventario',
            'tipo_producto' => 'BIEN',
            'unidad_medida_id' => $unidad->id,
            'metodo_valorizacion' => 'PMP',
            'costo_promedio' => 0,
            'precio_venta_neto' => 10000,
            'afecto_iva' => true,
            'codigo_dte' => 'DTE-' . uniqid(),
            'codigo_barra' => '780' . random_int(1000000000, 9999999999),
            'stock_minimo' => 0,
            'bodega_defecto_id' => null,
            'permite_merma' => false,
            'activo' => true,
        ], $overrides);
    }

    private function rutUnico(): string
    {
        return (string) random_int(70000000, 99999999) . '-' . random_int(0, 9);
    }
}