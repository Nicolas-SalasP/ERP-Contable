<?php

namespace Tests\Feature\Comercial;

use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\ProveedorProducto;
use App\Domains\Core\Models\Empresa;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\UnidadMedida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class ImportarListaPreciosCommandTest extends TestCase
{
    use PreparaEntornoBase;
    use RefreshDatabase;

    private Empresa $empresa;

    private Proveedor $proveedor;

    private Producto $productoA;

    private Producto $productoB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        [$this->empresa] = $this->crearEmpresaConAdmin();

        $this->proveedor = Proveedor::create([
            'empresa_id' => $this->empresa->id,
            'razon_social' => 'Mayorista Test SpA',
            'rut' => '76.111.222-3',
            'codigo_interno' => 'P-IMP-01',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP',
        ]);

        $unidad = UnidadMedida::firstOrCreate(
            ['codigo' => 'UN'],
            ['nombre' => 'Unidad', 'permite_decimal' => false, 'activo' => true]
        );

        $this->productoA = Producto::create([
            'empresa_id' => $this->empresa->id,
            'sku' => 'SKU-AAA',
            'nombre' => 'Producto A',
            'unidad_medida_id' => $unidad->id,
            'metodo_valorizacion' => 'PMP',
            'costo_promedio' => 100,
            'precio_venta_neto' => 1000,
            'afecto_iva' => true,
            'codigo_barra' => '7801111111111',
            'stock_minimo' => 0,
            'activo' => true,
        ]);

        $this->productoB = Producto::create([
            'empresa_id' => $this->empresa->id,
            'sku' => 'SKU-BBB',
            'nombre' => 'Producto B',
            'unidad_medida_id' => $unidad->id,
            'metodo_valorizacion' => 'PMP',
            'costo_promedio' => 200,
            'precio_venta_neto' => 2000,
            'afecto_iva' => true,
            'codigo_barra' => '7802222222222',
            'stock_minimo' => 0,
            'activo' => true,
        ]);
    }

    private function crearCsv(array $filas): string
    {
        $spreadsheet = new Spreadsheet();
        $hoja = $spreadsheet->getActiveSheet();
        $hoja->fromArray($filas);

        $ruta = tempnam(sys_get_temp_dir(), 'lista_precios_').'.csv';
        (new CsvWriter($spreadsheet))->save($ruta);

        return $ruta;
    }

    public function test_importa_y_hace_upsert_de_proveedor_productos(): void
    {
        $ruta = $this->crearCsv([
            ['codigo_proveedor', 'sku_interno', 'codigo_barra', 'costo_neto'],
            ['PROV-A', 'SKU-AAA', '', '85.50'],
            ['PROV-B', '', '7802222222222', '150'],
        ]);

        $exit = Artisan::call('comercial:importar-lista-precios', [
            'proveedor_id' => $this->proveedor->id,
            'archivo' => $ruta,
        ]);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Importados/actualizados: 2', Artisan::output());

        $this->assertDatabaseHas('proveedor_productos', [
            'proveedor_id' => $this->proveedor->id,
            'producto_id' => $this->productoA->id,
            'codigo_proveedor' => 'PROV-A',
        ]);
        $this->assertDatabaseHas('proveedor_productos', [
            'proveedor_id' => $this->proveedor->id,
            'producto_id' => $this->productoB->id,
            'codigo_proveedor' => 'PROV-B',
        ]);

        $registroA = ProveedorProducto::where('producto_id', $this->productoA->id)->first();
        $this->assertEquals(85.50, (float) $registroA->costo_neto);

        unlink($ruta);
    }

    public function test_reimportar_actualiza_el_costo_en_vez_de_duplicar(): void
    {
        $ruta1 = $this->crearCsv([
            ['sku_interno', 'costo_neto'],
            ['SKU-AAA', '100'],
        ]);
        Artisan::call('comercial:importar-lista-precios', [
            'proveedor_id' => $this->proveedor->id,
            'archivo' => $ruta1,
        ]);
        unlink($ruta1);

        $ruta2 = $this->crearCsv([
            ['sku_interno', 'costo_neto'],
            ['SKU-AAA', '120'],
        ]);
        Artisan::call('comercial:importar-lista-precios', [
            'proveedor_id' => $this->proveedor->id,
            'archivo' => $ruta2,
        ]);
        unlink($ruta2);

        $this->assertDatabaseCount('proveedor_productos', 1);
        $registro = ProveedorProducto::where('producto_id', $this->productoA->id)->first();
        $this->assertEquals(120, (float) $registro->costo_neto);
    }

    public function test_filas_sin_producto_que_matchee_se_reportan_sin_fallar_el_import(): void
    {
        $ruta = $this->crearCsv([
            ['sku_interno', 'costo_neto'],
            ['SKU-AAA', '100'],
            ['SKU-INEXISTENTE', '50'],
        ]);

        $exit = Artisan::call('comercial:importar-lista-precios', [
            'proveedor_id' => $this->proveedor->id,
            'archivo' => $ruta,
        ]);

        $this->assertSame(0, $exit);
        $output = Artisan::output();
        $this->assertStringContainsString('Importados/actualizados: 1', $output);
        $this->assertStringContainsString('no matchea ningun producto', $output);

        $this->assertDatabaseCount('proveedor_productos', 1);

        unlink($ruta);
    }

    public function test_proveedor_inexistente_falla_sin_tocar_la_bd(): void
    {
        $ruta = $this->crearCsv([
            ['sku_interno', 'costo_neto'],
            ['SKU-AAA', '100'],
        ]);

        $exit = Artisan::call('comercial:importar-lista-precios', [
            'proveedor_id' => 999999,
            'archivo' => $ruta,
        ]);

        $this->assertSame(1, $exit);
        $this->assertDatabaseCount('proveedor_productos', 0);

        unlink($ruta);
    }
}
