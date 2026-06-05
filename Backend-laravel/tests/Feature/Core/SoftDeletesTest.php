<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Cotizacion;
use App\Domains\Comercial\Models\EstadoCotizacion;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Activos\Models\ActivoFijo;
use App\Domains\Contabilidad\Models\AsientoContable;

class SoftDeletesTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empresa = Empresa::create(['rut' => '77.123.456-7', 'razon_social' => 'SoftDelete SpA', 'regimen_tributario' => '14_D3']);
    }

    public function test_cliente_se_borra_de_forma_logica_y_es_recuperable()
    {
        $cliente = Cliente::create(['empresa_id' => $this->empresa->id, 'rut' => '1.1.1.1-1', 'razon_social' => 'Cliente Borrable', 'estado' => 'ACTIVO']);
        $id = $cliente->id;

        $cliente->delete();

        $this->assertNull(Cliente::find($id), 'No debe aparecer en consultas normales');
        $this->assertNotNull(Cliente::withTrashed()->find($id), 'Debe seguir existiendo con withTrashed()');
        $this->assertTrue(Cliente::withTrashed()->find($id)->trashed());
        $this->assertSoftDeleted('clientes', ['id' => $id]);
    }

    public function test_proveedor_se_borra_de_forma_logica_y_es_recuperable()
    {
        $proveedor = Proveedor::create(['empresa_id' => $this->empresa->id, 'razon_social' => 'Prov Borrable', 'rut' => '2.2.2.2-2', 'codigo_interno' => 'P-DEL', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        $id = $proveedor->id;

        $proveedor->delete();

        $this->assertNull(Proveedor::find($id));
        $this->assertNotNull(Proveedor::withTrashed()->find($id));
        $this->assertSoftDeleted('proveedores', ['id' => $id]);
    }

    public function test_factura_se_borra_de_forma_logica_y_es_recuperable()
    {
        $proveedor = Proveedor::create(['empresa_id' => $this->empresa->id, 'razon_social' => 'Prov F', 'rut' => '3.3.3.3-3', 'codigo_interno' => 'P-F', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        $factura = Factura::create(['empresa_id' => $this->empresa->id, 'proveedor_id' => $proveedor->id, 'numero_factura' => 'F-DEL', 'codigo_unico' => 70000001, 'fecha_emision' => now(), 'monto_neto' => 1000, 'monto_iva' => 190, 'monto_bruto' => 1190, 'tipo' => 'COMPRA']);
        $id = $factura->id;

        $factura->delete();

        $this->assertNull(Factura::find($id));
        $this->assertNotNull(Factura::withTrashed()->find($id));
        $this->assertSoftDeleted('facturas', ['id' => $id]);
    }

    public function test_activo_fijo_se_borra_de_forma_logica_y_es_recuperable()
    {
        $activo = ActivoFijo::create(['empresa_id' => $this->empresa->id, 'codigo' => 'AF-DEL', 'nombre' => 'Equipo Borrable', 'valor_adquisicion' => 100000, 'vida_util_meses' => 36, 'fecha_adquisicion' => now(), 'valor_residual' => 0, 'depreciacion_acumulada' => 0, 'estado' => 'ACTIVO', 'cuenta_activo_codigo' => '112105', 'cuenta_depreciacion_codigo' => '112106']);
        $id = $activo->id;

        $activo->delete();

        $this->assertNull(ActivoFijo::find($id));
        $this->assertNotNull(ActivoFijo::withTrashed()->find($id));
        $this->assertSoftDeleted('activos_fijos', ['id' => $id]);
    }

    public function test_asiento_contable_se_borra_de_forma_logica_y_es_recuperable()
    {
        $asiento = AsientoContable::create(['empresa_id' => $this->empresa->id, 'fecha' => now(), 'glosa' => 'Asiento Borrable', 'numero_comprobante' => 'AS-DEL', 'estado' => 'CONTABILIZADO', 'codigo_unico' => 80000001]);
        $id = $asiento->id;

        $asiento->delete();

        $this->assertNull(AsientoContable::find($id));
        $this->assertNotNull(AsientoContable::withTrashed()->find($id));
        $this->assertSoftDeleted('asientos_contables', ['id' => $id]);
    }

    public function test_cotizacion_se_borra_de_forma_logica_y_es_recuperable()
    {
        EstadoCotizacion::insert([['id' => 1, 'nombre' => 'Borrador']]);
        $cliente = Cliente::create(['empresa_id' => $this->empresa->id, 'rut' => '5.5.5.5-5', 'razon_social' => 'Cliente Cot', 'estado' => 'ACTIVO']);
        $cotizacion = Cotizacion::create([
            'empresa_id' => $this->empresa->id, 'cliente_id' => $cliente->id, 'nombre_cliente' => 'Cliente Cot',
            'estado_id' => 1, 'numero_cotizacion' => 'COT-DEL', 'subtotal' => 100, 'monto_neto' => 100,
            'monto_iva' => 19, 'monto_total' => 119, 'total' => 119, 'fecha_emision' => now(),
        ]);
        $id = $cotizacion->id;

        $cotizacion->delete();

        $this->assertNull(Cotizacion::find($id));
        $this->assertNotNull(Cotizacion::withTrashed()->find($id));
        $this->assertTrue(Cotizacion::withTrashed()->find($id)->trashed());
        $this->assertSoftDeleted('cotizaciones', ['id' => $id]);
    }
}
