<?php

namespace Tests\Feature\Sii\Modelos;

use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\UnidadMedida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class ProductoSiiTraitTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    private function unidadBase(): UnidadMedida
    {
        return UnidadMedida::firstOrCreate(
            ['codigo' => 'UN'],
            ['nombre' => 'Unidad', 'permite_decimal' => false, 'activo' => true]
        );
    }

    public function test_producto_puede_guardar_codigo_sii_y_tipo(): void
    {
        $this->prepararEntornoBase();
        [$empresa] = $this->crearEmpresaConAdmin();
        $unidad = $this->unidadBase();

        $producto = Producto::create([
            'empresa_id'          => $empresa->id,
            'sku'                 => 'PROD-001',
            'nombre'              => 'Producto Test',
            'tipo_producto'       => 'BIEN',
            'unidad_medida_id'    => $unidad->id,
            'precio_venta_neto'   => 1000.00,
            'codigo_sii_producto' => '7891234567890',
            'codigo_sii_tipo'     => 'EAN13',
            'unidad_medida_sii'   => 'UN',
        ]);

        $persistido = Producto::find($producto->id);

        $this->assertSame('7891234567890', $persistido->codigo_sii_producto);
        $this->assertSame('EAN13', $persistido->codigo_sii_tipo);
        $this->assertSame('UN', $persistido->unidad_medida_sii);
    }

    public function test_unidad_medida_sii_nullable_acepta_null(): void
    {
        $this->prepararEntornoBase();
        [$empresa] = $this->crearEmpresaConAdmin();
        $unidad = $this->unidadBase();

        $producto = Producto::create([
            'empresa_id'        => $empresa->id,
            'sku'               => 'PROD-002',
            'nombre'            => 'Producto sin unidad SII',
            'tipo_producto'     => 'SERVICIO',
            'unidad_medida_id'  => $unidad->id,
            'precio_venta_neto' => 500.00,
        ]);

        $persistido = Producto::find($producto->id);

        $this->assertNull($persistido->codigo_sii_producto);
        $this->assertNull($persistido->codigo_sii_tipo);
        $this->assertNull($persistido->unidad_medida_sii);
    }

    public function test_fillable_de_producto_preserva_originales_y_agrega_sii(): void
    {
        $producto = new Producto();
        $fillable = $producto->getFillable();

        // Originales.
        $this->assertContains('sku', $fillable);
        $this->assertContains('precio_venta_neto', $fillable);
        $this->assertContains('afecto_iva', $fillable);

        // Trait SII.
        $this->assertContains('codigo_sii_producto', $fillable);
        $this->assertContains('codigo_sii_tipo', $fillable);
        $this->assertContains('unidad_medida_sii', $fillable);
    }
}
