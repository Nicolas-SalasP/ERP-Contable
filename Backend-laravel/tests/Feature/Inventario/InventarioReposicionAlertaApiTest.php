<?php

namespace Tests\Feature\Inventario;

use App\Domains\Core\Models\Empresa;
use App\Domains\Inventario\Jobs\CalcularAlertasInventarioJob;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\LoteInventario;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\ReglaReposicion;
use App\Domains\Inventario\Models\StockLoteInventario;
use App\Domains\Inventario\Models\StockProducto;
use App\Domains\Inventario\Models\UnidadMedida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaInventarioTest;
use Tests\TestCase;

class InventarioReposicionAlertaApiTest extends TestCase
{
    use RefreshDatabase;
    use PreparaInventarioTest;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararUsuariosInventarioDemo();
    }

    public function test_usuario_sin_token_recibe_401_en_alertas(): void
    {
        $this->getJson('/api/inventario/alertas')
            ->assertUnauthorized();
    }

    public function test_usuario_sin_permiso_recibe_422_al_crear_regla(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos([
            'inventario.productos.ver',
            'inventario.bodegas.ver',
        ]);

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        Sanctum::actingAs($usuario);

        $this->postJson('/api/inventario/reglas-reposicion', [
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'stock_minimo' => 5,
            'stock_objetivo' => 20,
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_crea_regla_de_reposicion_valida(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosInventarioReposicionCompleto());

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        Sanctum::actingAs($usuario);

        $this->postJson('/api/inventario/reglas-reposicion', [
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'stock_minimo' => 5,
            'stock_objetivo' => 20,
            'punto_reorden' => 8,
            'dias_alerta_vencimiento' => 15,
            'activo' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.producto_id', $producto->id)
            ->assertJsonPath('data.bodega_id', $bodega->id);

        $this->assertDatabaseHas('inventario_reglas_reposicion', [
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
        ]);
    }

    public function test_rechaza_stock_minimo_negativo(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosInventarioReposicionCompleto());

        $producto = $this->crearProducto($empresa);

        Sanctum::actingAs($usuario);

        $this->postJson('/api/inventario/reglas-reposicion', [
            'producto_id' => $producto->id,
            'stock_minimo' => -1,
            'stock_objetivo' => 20,
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_rechaza_stock_objetivo_menor_que_stock_minimo(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosInventarioReposicionCompleto());

        $producto = $this->crearProducto($empresa);

        Sanctum::actingAs($usuario);

        $this->postJson('/api/inventario/reglas-reposicion', [
            'producto_id' => $producto->id,
            'stock_minimo' => 10,
            'stock_objetivo' => 5,
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['stock_objetivo']]);
    }

    public function test_rechaza_producto_de_otra_empresa(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosInventarioReposicionCompleto());
        $otraEmpresa = $this->crearEmpresa();
        $productoAjeno = $this->crearProducto($otraEmpresa);

        Sanctum::actingAs($usuario);

        $this->postJson('/api/inventario/reglas-reposicion', [
            'producto_id' => $productoAjeno->id,
            'stock_minimo' => 5,
            'stock_objetivo' => 20,
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['producto_id']]);
    }

    public function test_rechaza_bodega_de_otra_empresa(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosInventarioReposicionCompleto());
        $otraEmpresa = $this->crearEmpresa();

        $producto = $this->crearProducto($empresa);
        $bodegaAjena = $this->crearBodega($otraEmpresa);

        Sanctum::actingAs($usuario);

        $this->postJson('/api/inventario/reglas-reposicion', [
            'producto_id' => $producto->id,
            'bodega_id' => $bodegaAjena->id,
            'stock_minimo' => 5,
            'stock_objetivo' => 20,
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['bodega_id']]);
    }

    public function test_lista_reglas_solo_de_la_empresa_del_usuario(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosInventarioReposicionCompleto());
        $otraEmpresa = $this->crearEmpresa();

        $producto = $this->crearProducto($empresa);
        $productoAjeno = $this->crearProducto($otraEmpresa);

        ReglaReposicion::create([
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'stock_minimo' => 5,
            'stock_objetivo' => 20,
            'dias_alerta_vencimiento' => 30,
            'activo' => true,
        ]);

        ReglaReposicion::create([
            'empresa_id' => $otraEmpresa->id,
            'producto_id' => $productoAjeno->id,
            'stock_minimo' => 1,
            'stock_objetivo' => 2,
            'dias_alerta_vencimiento' => 30,
            'activo' => true,
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->getJson('/api/inventario/reglas-reposicion')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($producto->id, $response->json('data.0.producto_id'));
    }

    public function test_genera_alerta_de_stock_bajo_y_sugerencia_de_reposicion(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosInventarioReposicionCompleto());

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        StockProducto::create([
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'stock_actual' => 3,
            'costo_promedio' => 0,
            'valor_total' => 0,
        ]);

        ReglaReposicion::create([
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'stock_minimo' => 5,
            'stock_objetivo' => 20,
            'dias_alerta_vencimiento' => 30,
            'activo' => true,
        ]);

        CalcularAlertasInventarioJob::dispatchSync($empresa->id);

        Sanctum::actingAs($usuario);

        $alertas = $this->getJson('/api/inventario/alertas')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json('data');

        $this->assertTrue(
            collect($alertas)->contains('tipo', 'STOCK_BAJO'),
            'No se encontró la alerta persistida STOCK_BAJO.'
        );

        $this->assertTrue(
            collect($alertas)->contains('tipo', 'REPOSICION_SUGERIDA'),
            'No se encontró la alerta persistida REPOSICION_SUGERIDA.'
        );

        $this->getJson('/api/inventario/reposicion/sugerencias')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.cantidad_sugerida', 17);
    }

    public function test_genera_alerta_por_lote_por_vencer_y_lote_vencido(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosInventarioReposicionCompleto());

        $producto = $this->crearProducto($empresa, [
            'maneja_lotes' => true,
            'requiere_fecha_vencimiento' => true,
        ]);

        $bodega = $this->crearBodega($empresa);

        $lotePorVencer = $this->crearLote($empresa, $producto, [
            'codigo_lote' => 'LOT-POR-VENCER',
            'fecha_vencimiento' => now()->addDays(5)->toDateString(),
        ]);

        $loteVencido = $this->crearLote($empresa, $producto, [
            'codigo_lote' => 'LOT-VENCIDO',
            'fecha_vencimiento' => now()->subDay()->toDateString(),
        ]);

        foreach ([$lotePorVencer, $loteVencido] as $lote) {
            StockLoteInventario::create([
                'empresa_id' => $empresa->id,
                'producto_id' => $producto->id,
                'bodega_id' => $bodega->id,
                'lote_id' => $lote->id,
                'stock_actual' => 10,
            ]);
        }

        ReglaReposicion::create([
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'stock_minimo' => 1,
            'stock_objetivo' => 10,
            'dias_alerta_vencimiento' => 10,
            'activo' => true,
        ]);

        CalcularAlertasInventarioJob::dispatchSync($empresa->id);

        Sanctum::actingAs($usuario);

        $alertas = $this->getJson('/api/inventario/alertas')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json('data');

        $this->assertTrue(
            collect($alertas)->contains('tipo', 'LOTE_POR_VENCER'),
            'No se encontró la alerta persistida LOTE_POR_VENCER.'
        );

        $this->assertTrue(
            collect($alertas)->contains('tipo', 'LOTE_VENCIDO'),
            'No se encontró la alerta persistida LOTE_VENCIDO.'
        );
    }

    public function test_respuesta_no_incorpora_campos_dte_sii(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosInventarioReposicionCompleto());

        $producto = $this->crearProducto($empresa);

        ReglaReposicion::create([
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'stock_minimo' => 5,
            'stock_objetivo' => 20,
            'dias_alerta_vencimiento' => 30,
            'activo' => true,
        ]);

        CalcularAlertasInventarioJob::dispatchSync($empresa->id);

        Sanctum::actingAs($usuario);

        $payload = json_encode(
            $this->getJson('/api/inventario/alertas')->assertOk()->json(),
            JSON_THROW_ON_ERROR
        );

        $this->assertStringNotContainsString('codigo_dte', $payload);
        $this->assertStringNotContainsString('codigo_sii', $payload);
        $this->assertStringNotContainsString('folio_dte', $payload);
        $this->assertStringNotContainsString('xml_dte', $payload);
        $this->assertStringNotContainsString('emitir_dte', $payload);
    }

    private function crearEmpresa(): Empresa
    {
        return Empresa::create([
            'rut' => $this->rutUnico(),
            'razon_social' => 'Empresa Reposicion ' . uniqid(),
        ]);
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
            'sku' => 'REP-' . strtoupper(substr(uniqid(), -8)),
            'nombre' => 'Producto Reposición Test',
            'descripcion' => 'Producto para pruebas de reposición',
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
            'codigo' => 'REP-' . strtoupper(substr(uniqid(), -6)),
            'nombre' => 'Bodega Reposición Test',
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
            'observacion' => 'Lote creado por test de reposición',
            'estado_operativo' => 'DISPONIBLE',
            'activo' => true,
        ], $overrides));
    }

    private function rutUnico(): string
    {
        return (string) random_int(70000000, 99999999) . '-' . random_int(0, 9);
    }
}