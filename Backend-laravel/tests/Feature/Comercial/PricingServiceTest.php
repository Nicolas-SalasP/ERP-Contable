<?php

namespace Tests\Feature\Comercial;

use App\Domains\Comercial\Exceptions\ComercialException;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\ProveedorProducto;
use App\Domains\Comercial\Services\PricingService;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\UnidadMedida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class PricingServiceTest extends TestCase
{
    use PreparaEntornoBase;
    use RefreshDatabase;

    private Empresa $empresa;

    private User $usuario;

    private PricingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        [$this->empresa, $this->usuario] = $this->crearEmpresaConAdmin();
        $this->service = app(PricingService::class);
    }

    private function crearProducto(array $overrides = []): Producto
    {
        $unidad = UnidadMedida::firstOrCreate(
            ['codigo' => 'UN'],
            ['nombre' => 'Unidad', 'permite_decimal' => false, 'activo' => true]
        );

        return Producto::create(array_merge([
            'empresa_id' => $this->empresa->id,
            'sku' => 'SKU-'.strtoupper(substr(uniqid(), -8)),
            'nombre' => 'Producto Test',
            'unidad_medida_id' => $unidad->id,
            'metodo_valorizacion' => 'PMP',
            'costo_promedio' => 0,
            'precio_venta_neto' => 0,
            'afecto_iva' => true,
            'codigo_barra' => '780'.random_int(1000000000, 9999999999),
            'stock_minimo' => 0,
            'activo' => true,
        ], $overrides));
    }

    private function crearProveedor(array $overrides = []): Proveedor
    {
        return Proveedor::create(array_merge([
            'empresa_id' => $this->empresa->id,
            'razon_social' => 'Proveedor '.uniqid(),
            'codigo_interno' => 'P-'.uniqid(),
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP',
        ], $overrides));
    }

    public function test_usa_el_costo_mas_barato_entre_proveedores_vigentes(): void
    {
        $producto = $this->crearProducto(['costo_promedio' => 999]);
        $proveedorCaro = $this->crearProveedor();
        $proveedorBarato = $this->crearProveedor();

        ProveedorProducto::create([
            'empresa_id' => $this->empresa->id,
            'proveedor_id' => $proveedorCaro->id,
            'producto_id' => $producto->id,
            'costo_neto' => 150,
            'moneda' => 'CLP',
            'vigente_desde' => now(),
            'activo' => true,
        ]);
        ProveedorProducto::create([
            'empresa_id' => $this->empresa->id,
            'proveedor_id' => $proveedorBarato->id,
            'producto_id' => $producto->id,
            'costo_neto' => 100,
            'moneda' => 'CLP',
            'vigente_desde' => now(),
            'activo' => true,
        ]);

        $sugerencia = $this->service->sugerirPrecio($producto);

        $this->assertEquals(100.0, $sugerencia['costo_usado']);
        $this->assertEquals($proveedorBarato->id, $sugerencia['proveedor_usado']);
    }

    public function test_ignora_ofertas_de_proveedor_inactivas(): void
    {
        $producto = $this->crearProducto(['costo_promedio' => 999]);
        $proveedor = $this->crearProveedor();

        ProveedorProducto::create([
            'empresa_id' => $this->empresa->id,
            'proveedor_id' => $proveedor->id,
            'producto_id' => $producto->id,
            'costo_neto' => 50,
            'moneda' => 'CLP',
            'vigente_desde' => now(),
            'activo' => false,
        ]);

        $sugerencia = $this->service->sugerirPrecio($producto);

        // Sin ofertas vigentes cae al fallback costo_promedio del producto.
        $this->assertEquals(999.0, $sugerencia['costo_usado']);
        $this->assertNull($sugerencia['proveedor_usado']);
    }

    public function test_usa_costo_promedio_como_fallback_sin_proveedores(): void
    {
        $producto = $this->crearProducto(['costo_promedio' => 200]);

        $sugerencia = $this->service->sugerirPrecio($producto);

        $this->assertEquals(200.0, $sugerencia['costo_usado']);
        $this->assertNull($sugerencia['proveedor_usado']);
    }

    public function test_aplica_el_margen_configurado_por_empresa(): void
    {
        $this->empresa->update(['margen_venta_pct' => 40, 'margen_minimo_pct' => 5]);
        $producto = $this->crearProducto(['costo_promedio' => 100]);

        $sugerencia = $this->service->sugerirPrecio($producto->fresh());

        $this->assertEquals(0.40, $sugerencia['margen_aplicado']);
        $this->assertEquals(140.0, $sugerencia['precio_sugerido']);
        $this->assertFalse($sugerencia['piso_aplicado']);
    }

    public function test_usa_margen_por_defecto_si_la_empresa_no_lo_tiene_configurado(): void
    {
        // Las columnas tienen default de migracion (30% / 5%); no seteamos nada explicito.
        $producto = $this->crearProducto(['costo_promedio' => 100]);

        $sugerencia = $this->service->sugerirPrecio($producto);

        $this->assertEquals(0.30, $sugerencia['margen_aplicado']);
        $this->assertEquals(130.0, $sugerencia['precio_sugerido']);
    }

    public function test_piso_dinamico_se_aplica_cuando_el_margen_configurado_queda_bajo_el_minimo(): void
    {
        $this->empresa->update(['margen_venta_pct' => 2, 'margen_minimo_pct' => 10]);
        $producto = $this->crearProducto(['costo_promedio' => 100]);

        $sugerencia = $this->service->sugerirPrecio($producto->fresh());

        $this->assertTrue($sugerencia['piso_aplicado']);
        $this->assertEquals(110.0, $sugerencia['precio_sugerido']);
    }

    public function test_sin_costo_disponible_lanza_excepcion(): void
    {
        $producto = $this->crearProducto(['costo_promedio' => 0]);

        $this->expectException(ComercialException::class);
        $this->service->sugerirPrecio($producto);
    }

    public function test_aplicar_sugerencia_actualiza_precio_venta_neto_del_producto(): void
    {
        $this->empresa->update(['margen_venta_pct' => 20, 'margen_minimo_pct' => 5]);
        $producto = $this->crearProducto(['costo_promedio' => 100, 'precio_venta_neto' => 1]);

        $actualizado = $this->service->aplicarSugerencia($producto->fresh());

        $this->assertEquals(120.0, (float) $actualizado->precio_venta_neto);
        $this->assertDatabaseHas('inventario_productos', [
            'id' => $producto->id,
            'precio_venta_neto' => 120,
        ]);
    }

    public function test_endpoint_sugerencia_retorna_json_correcto(): void
    {
        $this->empresa->update(['margen_venta_pct' => 25, 'margen_minimo_pct' => 5]);
        $producto = $this->crearProducto(['costo_promedio' => 80]);

        $response = $this->actingAs($this->usuario)
            ->getJson("/api/comercial/pricing/productos/{$producto->id}/sugerencia");

        $response->assertOk()
            ->assertJsonPath('data.costo_usado', 80)
            ->assertJsonPath('data.precio_sugerido', 100);
    }

    public function test_endpoint_aplicar_persiste_el_precio_sugerido(): void
    {
        $this->empresa->update(['margen_venta_pct' => 25, 'margen_minimo_pct' => 5]);
        $producto = $this->crearProducto(['costo_promedio' => 80, 'precio_venta_neto' => 1]);

        $response = $this->actingAs($this->usuario)
            ->postJson("/api/comercial/pricing/productos/{$producto->id}/aplicar");

        $response->assertOk()
            ->assertJsonPath('data.precio_venta_neto', '100.0000');

        $this->assertDatabaseHas('inventario_productos', [
            'id' => $producto->id,
            'precio_venta_neto' => 100,
        ]);
    }

    public function test_endpoint_producto_de_otra_empresa_retorna_404(): void
    {
        [$otraEmpresa] = $this->crearEmpresaConAdmin();
        $unidad = UnidadMedida::firstOrCreate(
            ['codigo' => 'UN'],
            ['nombre' => 'Unidad', 'permite_decimal' => false, 'activo' => true]
        );
        $productoAjeno = Producto::create([
            'empresa_id' => $otraEmpresa->id,
            'sku' => 'SKU-AJENO',
            'nombre' => 'Producto Ajeno',
            'unidad_medida_id' => $unidad->id,
            'metodo_valorizacion' => 'PMP',
            'costo_promedio' => 50,
            'precio_venta_neto' => 0,
            'afecto_iva' => true,
            'codigo_barra' => '7809999999999',
            'stock_minimo' => 0,
            'activo' => true,
        ]);

        $this->actingAs($this->usuario)
            ->getJson("/api/comercial/pricing/productos/{$productoAjeno->id}/sugerencia")
            ->assertStatus(404);
    }
}
