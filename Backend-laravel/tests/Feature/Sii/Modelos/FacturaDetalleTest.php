<?php

namespace Tests\Feature\Sii\Modelos;

use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\FacturaDetalle;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Core\Models\Empresa;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\UnidadMedida;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class FacturaDetalleTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    private function crearProveedor(Empresa $empresa): Proveedor
    {
        return Proveedor::create([
            'empresa_id'     => $empresa->id,
            'rut'            => '8.8.8.8-' . random_int(0, 9),
            'razon_social'   => 'Proveedor Det',
            'codigo_interno' => 'PD-' . uniqid(),
            'pais_iso'       => 'CL',
            'moneda_defecto' => 'CLP',
        ]);
    }

    private function crearFactura(): Factura
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $proveedor = $this->crearProveedor($empresa);

        return Factura::create([
            'empresa_id'     => $empresa->id,
            'proveedor_id'   => $proveedor->id,
            'codigo_unico'   => Factura::generarCodigoUnico(),
            'numero_factura' => 'F-DET-' . uniqid(),
            'fecha_emision'  => '2026-05-23',
            'monto_neto'     => 0,
            'monto_iva'      => 0,
            'monto_bruto'    => 0,
        ]);
    }

    public function test_crear_detalle_valido_se_persiste(): void
    {
        $this->prepararEntornoBase();
        $factura = $this->crearFactura();

        $detalle = FacturaDetalle::create([
            'factura_id'      => $factura->id,
            'numero_linea'    => 1,
            'nombre_item'     => 'Producto X',
            'cantidad'        => 2.5,
            'precio_unitario' => 1000.0,
            'monto_item'      => 2500.0,
        ]);

        $persistido = FacturaDetalle::find($detalle->id);

        $this->assertNotNull($persistido);
        $this->assertSame('Producto X', $persistido->nombre_item);
        $this->assertSame($factura->id, $persistido->factura_id);
        $this->assertSame(1, $persistido->numero_linea);
    }

    public function test_detalle_se_borra_en_cascada_al_borrar_factura(): void
    {
        $this->prepararEntornoBase();
        $factura = $this->crearFactura();

        FacturaDetalle::create([
            'factura_id'      => $factura->id,
            'numero_linea'    => 1,
            'nombre_item'     => 'Item 1',
            'cantidad'        => 1,
            'precio_unitario' => 100,
            'monto_item'      => 100,
        ]);
        FacturaDetalle::create([
            'factura_id'      => $factura->id,
            'numero_linea'    => 2,
            'nombre_item'     => 'Item 2',
            'cantidad'        => 1,
            'precio_unitario' => 200,
            'monto_item'      => 200,
        ]);

        $this->assertSame(2, FacturaDetalle::where('factura_id', $factura->id)->count());

        $factura->delete();

        $this->assertSame(0, FacturaDetalle::where('factura_id', $factura->id)->count());
    }

    public function test_unique_compuesto_bloquea_duplicados_de_numero_linea_por_factura(): void
    {
        $this->prepararEntornoBase();
        $factura = $this->crearFactura();

        FacturaDetalle::create([
            'factura_id'      => $factura->id,
            'numero_linea'    => 1,
            'nombre_item'     => 'Linea 1',
            'cantidad'        => 1,
            'precio_unitario' => 100,
            'monto_item'      => 100,
        ]);

        $this->expectException(QueryException::class);

        FacturaDetalle::create([
            'factura_id'      => $factura->id,
            'numero_linea'    => 1, // duplicado para la misma factura
            'nombre_item'     => 'Linea 1 duplicada',
            'cantidad'        => 1,
            'precio_unitario' => 200,
            'monto_item'      => 200,
        ]);
    }

    public function test_relacion_belongs_to_factura_funciona(): void
    {
        $this->prepararEntornoBase();
        $factura = $this->crearFactura();

        $detalle = FacturaDetalle::create([
            'factura_id'      => $factura->id,
            'numero_linea'    => 1,
            'nombre_item'     => 'Item',
            'cantidad'        => 1,
            'precio_unitario' => 100,
            'monto_item'      => 100,
        ]);

        $this->assertInstanceOf(Factura::class, $detalle->factura);
        $this->assertSame($factura->id, $detalle->factura->id);
    }

    public function test_relacion_belongs_to_producto_nullable_funciona(): void
    {
        $this->prepararEntornoBase();
        [$empresa] = $this->crearEmpresaConAdmin();
        $proveedor = $this->crearProveedor($empresa);

        $unidad = UnidadMedida::firstOrCreate(
            ['codigo' => 'UN'],
            ['nombre' => 'Unidad', 'permite_decimal' => false, 'activo' => true]
        );

        $producto = Producto::create([
            'empresa_id'        => $empresa->id,
            'sku'               => 'SKU-DET-' . uniqid(),
            'nombre'            => 'Producto referenciado',
            'tipo_producto'     => 'BIEN',
            'unidad_medida_id'  => $unidad->id,
            'precio_venta_neto' => 100,
        ]);

        $factura = Factura::create([
            'empresa_id'     => $empresa->id,
            'proveedor_id'   => $proveedor->id,
            'codigo_unico'   => Factura::generarCodigoUnico(),
            'numero_factura' => 'F-DET-PROD',
            'fecha_emision'  => '2026-05-23',
            'monto_neto'     => 0,
            'monto_iva'      => 0,
            'monto_bruto'    => 0,
        ]);

        // Detalle CON producto.
        $detalleConProducto = FacturaDetalle::create([
            'factura_id'      => $factura->id,
            'numero_linea'    => 1,
            'producto_id'     => $producto->id,
            'nombre_item'     => 'Item con producto',
            'cantidad'        => 1,
            'precio_unitario' => 100,
            'monto_item'      => 100,
        ]);

        $this->assertInstanceOf(Producto::class, $detalleConProducto->producto);
        $this->assertSame($producto->id, $detalleConProducto->producto->id);

        // Detalle SIN producto (snapshot only).
        $detalleSinProducto = FacturaDetalle::create([
            'factura_id'      => $factura->id,
            'numero_linea'    => 2,
            'nombre_item'     => 'Item sin producto referenciado',
            'cantidad'        => 1,
            'precio_unitario' => 50,
            'monto_item'      => 50,
        ]);

        $this->assertNull($detalleSinProducto->producto);
    }

    public function test_scope_por_factura_filtra_correctamente(): void
    {
        $this->prepararEntornoBase();
        $facturaA = $this->crearFactura();
        $facturaB = $this->crearFactura();

        FacturaDetalle::create([
            'factura_id'      => $facturaA->id,
            'numero_linea'    => 1,
            'nombre_item'     => 'A1',
            'cantidad'        => 1,
            'precio_unitario' => 100,
            'monto_item'      => 100,
        ]);
        FacturaDetalle::create([
            'factura_id'      => $facturaB->id,
            'numero_linea'    => 1,
            'nombre_item'     => 'B1',
            'cantidad'        => 1,
            'precio_unitario' => 200,
            'monto_item'      => 200,
        ]);

        $detallesA = FacturaDetalle::porFactura($facturaA->id)->get();

        $this->assertCount(1, $detallesA);
        $this->assertSame('A1', $detallesA->first()->nombre_item);
    }
}
