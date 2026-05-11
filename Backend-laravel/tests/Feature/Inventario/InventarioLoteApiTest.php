<?php

namespace Tests\Feature\Inventario;

use App\Domains\Core\Models\Empresa;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\LoteInventario;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\StockLoteInventario;
use App\Domains\Inventario\Models\UnidadMedida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaInventarioTrait;
use Tests\TestCase;

class InventarioLoteApiTest extends TestCase
{
    use RefreshDatabase;
    use PreparaInventarioTrait;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararUsuariosInventarioDemo();
    }

    public function test_contador_puede_crear_lote_valido(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosLotesCompleto());

        $producto = $this->crearProducto($empresa, [
            'maneja_lotes' => true,
            'requiere_fecha_vencimiento' => true,
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/inventario/lotes', [
            'producto_id' => $producto->id,
            'codigo_lote' => 'LOT-2026-001',
            'fecha_fabricacion' => '2026-01-01',
            'fecha_vencimiento' => '2026-12-31',
            'observacion' => 'Lote inicial de prueba',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.codigo_lote', 'LOT-2026-001')
            ->assertJsonPath('data.producto_id', $producto->id);

        $this->assertDatabaseHas('inventario_lotes', [
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'codigo_lote' => 'LOT-2026-001',
            'activo' => true,
        ]);
    }

    public function test_no_permite_crear_lote_duplicado_para_mismo_producto_empresa(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosLotesCompleto());

        $producto = $this->crearProducto($empresa, [
            'maneja_lotes' => true,
        ]);

        LoteInventario::create([
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'codigo_lote' => 'LOT-DUP',
            'fecha_fabricacion' => null,
            'fecha_vencimiento' => null,
            'observacion' => 'Lote existente',
            'activo' => true,
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/inventario/lotes', [
            'producto_id' => $producto->id,
            'codigo_lote' => 'LOT-DUP',
            'observacion' => 'Intento duplicado',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertEquals(1, LoteInventario::query()
            ->where('empresa_id', $empresa->id)
            ->where('producto_id', $producto->id)
            ->where('codigo_lote', 'LOT-DUP')
            ->count());
    }

    public function test_permite_mismo_codigo_lote_en_otra_empresa(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosLotesCompleto());

        $otraEmpresa = $this->crearEmpresa();

        $productoPropio = $this->crearProducto($empresa, [
            'maneja_lotes' => true,
        ]);

        $productoOtraEmpresa = $this->crearProducto($otraEmpresa, [
            'maneja_lotes' => true,
        ]);

        LoteInventario::create([
            'empresa_id' => $otraEmpresa->id,
            'producto_id' => $productoOtraEmpresa->id,
            'codigo_lote' => 'LOT-COMPARTIDO',
            'activo' => true,
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/inventario/lotes', [
            'producto_id' => $productoPropio->id,
            'codigo_lote' => 'LOT-COMPARTIDO',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.codigo_lote', 'LOT-COMPARTIDO');

        $this->assertDatabaseHas('inventario_lotes', [
            'empresa_id' => $empresa->id,
            'producto_id' => $productoPropio->id,
            'codigo_lote' => 'LOT-COMPARTIDO',
        ]);

        $this->assertDatabaseHas('inventario_lotes', [
            'empresa_id' => $otraEmpresa->id,
            'producto_id' => $productoOtraEmpresa->id,
            'codigo_lote' => 'LOT-COMPARTIDO',
        ]);
    }

    public function test_no_permite_crear_lote_para_producto_de_otra_empresa(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosLotesCompleto());

        $otraEmpresa = $this->crearEmpresa();

        $productoAjeno = $this->crearProducto($otraEmpresa, [
            'maneja_lotes' => true,
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/inventario/lotes', [
            'producto_id' => $productoAjeno->id,
            'codigo_lote' => 'LOT-AJENO',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('inventario_lotes', [
            'empresa_id' => $empresa->id,
            'producto_id' => $productoAjeno->id,
            'codigo_lote' => 'LOT-AJENO',
        ]);
    }

    public function test_no_permite_crear_lote_para_producto_inactivo(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosLotesCompleto());

        $producto = $this->crearProducto($empresa, [
            'maneja_lotes' => true,
            'activo' => false,
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/inventario/lotes', [
            'producto_id' => $producto->id,
            'codigo_lote' => 'LOT-PROD-INACTIVO',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('inventario_lotes', [
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'codigo_lote' => 'LOT-PROD-INACTIVO',
        ]);
    }

    public function test_producto_que_requiere_fecha_vencimiento_exige_fecha_en_lote(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosLotesCompleto());

        $producto = $this->crearProducto($empresa, [
            'maneja_lotes' => true,
            'requiere_fecha_vencimiento' => true,
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/inventario/lotes', [
            'producto_id' => $producto->id,
            'codigo_lote' => 'LOT-SIN-VENCIMIENTO',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('inventario_lotes', [
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'codigo_lote' => 'LOT-SIN-VENCIMIENTO',
        ]);
    }

    public function test_contador_puede_listar_lotes(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosLotesLectura());

        $producto = $this->crearProducto($empresa, [
            'maneja_lotes' => true,
        ]);

        $lote = $this->crearLote($empresa, $producto, [
            'codigo_lote' => 'LOT-LISTAR-001',
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->getJson('/api/inventario/lotes');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $codigos = collect($response->json('data'))->pluck('codigo_lote')->all();

        $this->assertContains($lote->codigo_lote, $codigos);
    }

    public function test_contador_puede_ver_detalle_de_lote(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosLotesLectura());

        $producto = $this->crearProducto($empresa, [
            'maneja_lotes' => true,
        ]);

        $lote = $this->crearLote($empresa, $producto, [
            'codigo_lote' => 'LOT-DETALLE-001',
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->getJson("/api/inventario/lotes/{$lote->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $lote->id)
            ->assertJsonPath('data.codigo_lote', 'LOT-DETALLE-001');
    }

    public function test_contador_puede_editar_lote(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosLotesCompleto());

        $producto = $this->crearProducto($empresa, [
            'maneja_lotes' => true,
        ]);

        $lote = $this->crearLote($empresa, $producto, [
            'codigo_lote' => 'LOT-EDITAR-001',
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->putJson("/api/inventario/lotes/{$lote->id}", [
            'codigo_lote' => 'LOT-EDITADO-001',
            'fecha_fabricacion' => '2026-01-15',
            'fecha_vencimiento' => '2026-11-30',
            'observacion' => 'Lote actualizado por test',
            'activo' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.codigo_lote', 'LOT-EDITADO-001')
            ->assertJsonPath('data.activo', false);

        $this->assertDatabaseHas('inventario_lotes', [
            'id' => $lote->id,
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'codigo_lote' => 'LOT-EDITADO-001',
            'activo' => false,
        ]);
    }

    public function test_contador_puede_listar_lotes_por_producto(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosLotesLectura());

        $productoA = $this->crearProducto($empresa, [
            'maneja_lotes' => true,
        ]);

        $productoB = $this->crearProducto($empresa, [
            'maneja_lotes' => true,
        ]);

        $loteA = $this->crearLote($empresa, $productoA, [
            'codigo_lote' => 'LOT-PRODUCTO-A',
        ]);

        $loteB = $this->crearLote($empresa, $productoB, [
            'codigo_lote' => 'LOT-PRODUCTO-B',
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->getJson("/api/inventario/productos/{$productoA->id}/lotes");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $codigos = collect($response->json('data'))->pluck('codigo_lote')->all();

        $this->assertContains($loteA->codigo_lote, $codigos);
        $this->assertNotContains($loteB->codigo_lote, $codigos);
    }

    public function test_contador_puede_consultar_stock_por_lote(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosLotesLectura());

        $producto = $this->crearProducto($empresa, [
            'maneja_lotes' => true,
        ]);

        $bodega = $this->crearBodega($empresa);

        $lote = $this->crearLote($empresa, $producto, [
            'codigo_lote' => 'LOT-STOCK-001',
        ]);

        StockLoteInventario::create([
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'lote_id' => $lote->id,
            'stock_actual' => 12,
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->getJson("/api/inventario/lotes/{$lote->id}/stock");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.lote.id', $lote->id);

        $this->assertEquals(12.0, (float) $response->json('data.stock_total'));

        $stockPorBodega = collect($response->json('data.stock_por_bodega'));

        $this->assertTrue(
            $stockPorBodega->contains(fn ($item) => (int) $item['bodega_id'] === (int) $bodega->id)
        );
    }

    public function test_auditor_puede_consultar_lotes_pero_no_crear_ni_editar(): void
    {
        [$empresa, $usuario] = $this->usuarioAuditorConPermisos($this->permisosLotesLectura());

        $producto = $this->crearProducto($empresa, [
            'maneja_lotes' => true,
        ]);

        $lote = $this->crearLote($empresa, $producto, [
            'codigo_lote' => 'LOT-AUDITOR-001',
        ]);

        Sanctum::actingAs($usuario);

        $this->getJson('/api/inventario/lotes')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson("/api/inventario/lotes/{$lote->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->postJson('/api/inventario/lotes', [
            'producto_id' => $producto->id,
            'codigo_lote' => 'LOT-AUDITOR-BLOQUEADO',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->putJson("/api/inventario/lotes/{$lote->id}", [
            'codigo_lote' => 'LOT-AUDITOR-EDITADO',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('inventario_lotes', [
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'codigo_lote' => 'LOT-AUDITOR-BLOQUEADO',
        ]);
    }

    public function test_lotes_respeta_multiempresa_en_listado_y_detalle(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosLotesLectura());

        $otraEmpresa = $this->crearEmpresa();

        $productoPropio = $this->crearProducto($empresa, [
            'maneja_lotes' => true,
        ]);

        $productoAjeno = $this->crearProducto($otraEmpresa, [
            'maneja_lotes' => true,
        ]);

        $lotePropio = $this->crearLote($empresa, $productoPropio, [
            'codigo_lote' => 'LOT-PROPIO',
        ]);

        $loteAjeno = $this->crearLote($otraEmpresa, $productoAjeno, [
            'codigo_lote' => 'LOT-AJENO',
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->getJson('/api/inventario/lotes');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $codigos = collect($response->json('data'))->pluck('codigo_lote')->all();

        $this->assertContains($lotePropio->codigo_lote, $codigos);
        $this->assertNotContains($loteAjeno->codigo_lote, $codigos);

        $this->getJson("/api/inventario/lotes/{$loteAjeno->id}")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_no_permite_acceder_a_lotes_sin_token(): void
    {
        $this->getJson('/api/inventario/lotes')
            ->assertStatus(401);

        $this->postJson('/api/inventario/lotes', [
            'producto_id' => 1,
            'codigo_lote' => 'LOT-SIN-TOKEN',
        ])
            ->assertStatus(401);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function permisosLotesCompleto(): array
    {
        return [
            'inventario.productos.ver',
            'inventario.productos.crear',
            'inventario.productos.editar',

            'inventario.bodegas.ver',
            'inventario.bodegas.crear',

            'inventario.lotes.ver',
            'inventario.lotes.crear',
            'inventario.lotes.editar',
        ];
    }

    private function permisosLotesLectura(): array
    {
        return [
            'inventario.productos.ver',
            'inventario.bodegas.ver',
            'inventario.lotes.ver',
        ];
    }

    private function crearEmpresa(): Empresa
    {
        return Empresa::create([
            'rut' => $this->rutUnico(),
            'razon_social' => 'Empresa Inventario ' . uniqid(),
        ]);
    }

    private function crearProducto(Empresa $empresa, array $overrides = []): Producto
    {
        $unidad = $this->obtenerUnidadBase();

        return Producto::create(array_merge([
            'empresa_id' => $empresa->id,
            'sku' => 'PROD-' . strtoupper(substr(uniqid(), -8)),
            'nombre' => 'Producto Lote Test',
            'descripcion' => 'Producto para pruebas de lotes',
            'tipo_producto' => 'BIEN',
            'unidad_medida_id' => $unidad->id,
            'metodo_valorizacion' => 'PMP',
            'costo_promedio' => 0,
            'precio_venta_neto' => 1000,
            'afecto_iva' => true,
            'codigo_barra' => '780' . random_int(1000000000, 9999999999),
            'stock_minimo' => 0,
            'bodega_defecto_id' => null,
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
            'codigo' => 'BOD-' . strtoupper(substr(uniqid(), -6)),
            'nombre' => 'Bodega Lote Test',
            'direccion' => 'Santiago, Chile',
            'estado' => 'ACTIVA',
        ], $overrides));
    }

    private function crearLote(Empresa $empresa, Producto $producto, array $overrides = []): LoteInventario
    {
        return LoteInventario::create(array_merge([
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'codigo_lote' => 'LOT-' . strtoupper(substr(uniqid(), -8)),
            'fecha_fabricacion' => null,
            'fecha_vencimiento' => null,
            'observacion' => 'Lote creado por test',
            'activo' => true,
        ], $overrides));
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