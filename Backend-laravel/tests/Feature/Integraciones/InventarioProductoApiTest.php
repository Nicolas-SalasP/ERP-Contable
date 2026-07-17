<?php

namespace Tests\Feature\Integraciones;

use App\Domains\Core\Models\Empresa;
use App\Domains\Integraciones\Services\IntegracionApiKeyService;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\UnidadMedida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/** Cobertura de los endpoints publicos de inventario (Etapa 3): listado, detalle por SKU y toggle visible_web. */
class InventarioProductoApiTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    private function habilitarModuloYEmitirKey(Empresa $empresa, array $scopes): string
    {
        $this->crearUsuario($empresa, $this->rolAdministrador, [
            'module_keys' => ['integraciones.api'],
        ]);

        $emitida = app(IntegracionApiKeyService::class)->emitir($empresa->id, 'Test', $scopes);

        return $emitida['token'];
    }

    private function crearProducto(Empresa $empresa, array $overrides = []): Producto
    {
        $unidad = UnidadMedida::firstOrCreate(
            ['codigo' => 'UN'],
            ['nombre' => 'Unidad', 'permite_decimal' => false, 'activo' => true]
        );

        return Producto::create(array_merge([
            'empresa_id' => $empresa->id,
            'sku' => 'SKU-'.strtoupper(substr(uniqid(), -8)),
            'nombre' => 'Producto Test',
            'descripcion' => 'Descripcion de prueba',
            'tipo_producto' => 'BIEN',
            'unidad_medida_id' => $unidad->id,
            'metodo_valorizacion' => 'PMP',
            'costo_promedio' => 100,
            'precio_venta_neto' => 1000,
            'afecto_iva' => true,
            'codigo_barra' => '780'.random_int(1000000000, 9999999999),
            'stock_minimo' => 0,
            'permite_merma' => true,
            'activo' => true,
            'visible_web' => false,
        ], $overrides));
    }

    public function test_listar_productos_devuelve_solo_los_de_la_empresa_de_la_key(): void
    {
        $empresaA = $this->crearEmpresa();
        $empresaB = $this->crearEmpresa();
        $this->crearProducto($empresaA, ['sku' => 'A-001']);
        $this->crearProducto($empresaB, ['sku' => 'B-001']);
        $token = $this->habilitarModuloYEmitirKey($empresaA, ['inventario:leer']);

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/integraciones/v1/inventario/productos');

        $respuesta->assertOk();
        $skus = collect($respuesta->json('data'))->pluck('sku');
        $this->assertTrue($skus->contains('A-001'));
        $this->assertFalse($skus->contains('B-001'));
    }

    public function test_listar_no_expone_costo_promedio(): void
    {
        $empresa = $this->crearEmpresa();
        $this->crearProducto($empresa, ['sku' => 'C-001']);
        $token = $this->habilitarModuloYEmitirKey($empresa, ['inventario:leer']);

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/integraciones/v1/inventario/productos');

        $respuesta->assertOk();
        $this->assertArrayNotHasKey('costo_promedio', $respuesta->json('data.0'));
    }

    public function test_listar_filtra_por_visible_web(): void
    {
        $empresa = $this->crearEmpresa();
        $this->crearProducto($empresa, ['sku' => 'VIS-001', 'visible_web' => true]);
        $this->crearProducto($empresa, ['sku' => 'VIS-002', 'visible_web' => false]);
        $token = $this->habilitarModuloYEmitirKey($empresa, ['inventario:leer']);

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/integraciones/v1/inventario/productos?visible_web=1');

        $skus = collect($respuesta->json('data'))->pluck('sku');
        $this->assertTrue($skus->contains('VIS-001'));
        $this->assertFalse($skus->contains('VIS-002'));
    }

    public function test_index_sin_scope_leer_es_rechazado(): void
    {
        $empresa = $this->crearEmpresa();
        $token = $this->habilitarModuloYEmitirKey($empresa, ['inventario:escribir']);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/integraciones/v1/inventario/productos')
            ->assertForbidden();
    }

    public function test_show_por_sku_devuelve_el_producto(): void
    {
        $empresa = $this->crearEmpresa();
        $this->crearProducto($empresa, ['sku' => 'SHOW-001', 'nombre' => 'Producto Show']);
        $token = $this->habilitarModuloYEmitirKey($empresa, ['inventario:leer']);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/integraciones/v1/inventario/productos/SHOW-001')
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Producto Show');
    }

    public function test_show_no_encuentra_producto_de_otra_empresa(): void
    {
        $empresaA = $this->crearEmpresa();
        $empresaB = $this->crearEmpresa();
        $this->crearProducto($empresaB, ['sku' => 'AJENO-001']);
        $token = $this->habilitarModuloYEmitirKey($empresaA, ['inventario:leer']);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/integraciones/v1/inventario/productos/AJENO-001')
            ->assertNotFound();
    }

    public function test_toggle_visible_web_actualiza_el_flag(): void
    {
        $empresa = $this->crearEmpresa();
        $producto = $this->crearProducto($empresa, ['sku' => 'TOGGLE-001', 'visible_web' => false]);
        $token = $this->habilitarModuloYEmitirKey($empresa, ['inventario:escribir']);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->patchJson('/api/integraciones/v1/inventario/productos/TOGGLE-001/visible-web', ['visible_web' => true])
            ->assertOk()
            ->assertJsonPath('data.visible_web', true);

        $this->assertTrue($producto->fresh()->visible_web);
    }

    public function test_toggle_visible_web_sin_scope_escribir_es_rechazado(): void
    {
        $empresa = $this->crearEmpresa();
        $this->crearProducto($empresa, ['sku' => 'TOGGLE-002']);
        $token = $this->habilitarModuloYEmitirKey($empresa, ['inventario:leer']);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->patchJson('/api/integraciones/v1/inventario/productos/TOGGLE-002/visible-web', ['visible_web' => true])
            ->assertForbidden();
    }

    public function test_toggle_visible_web_no_afecta_producto_de_otra_empresa(): void
    {
        $empresaA = $this->crearEmpresa();
        $empresaB = $this->crearEmpresa();
        $productoB = $this->crearProducto($empresaB, ['sku' => 'CROSS-001', 'visible_web' => false]);
        $token = $this->habilitarModuloYEmitirKey($empresaA, ['inventario:escribir']);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->patchJson('/api/integraciones/v1/inventario/productos/CROSS-001/visible-web', ['visible_web' => true])
            ->assertNotFound();

        $this->assertFalse($productoB->fresh()->visible_web);
    }
}
