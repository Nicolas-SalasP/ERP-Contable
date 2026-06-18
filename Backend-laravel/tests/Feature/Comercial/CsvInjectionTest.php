<?php

namespace Tests\Feature\Comercial;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Contabilidad\Models\PlanCuenta;

class CsvInjectionTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        [$this->empresa, $this->usuario] = $this->crearEmpresaConAdmin();

        PlanCuenta::firstOrCreate(
            ['empresa_id' => $this->empresa->id, 'codigo' => '410101'],
            ['nombre' => 'Gasto', 'tipo' => 'GASTO', 'imputable' => true, 'activo' => true]
        );
        PlanCuenta::firstOrCreate(
            ['empresa_id' => $this->empresa->id, 'codigo' => '353350'],
            ['nombre' => 'IVA CF', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]
        );
        PlanCuenta::firstOrCreate(
            ['empresa_id' => $this->empresa->id, 'codigo' => '352105'],
            ['nombre' => 'Proveedores', 'tipo' => 'PASIVO', 'imputable' => true, 'activo' => true]
        );
    }

    public function test_exportacion_csv_escapa_formulas(): void
    {
        $proveedor = Proveedor::create([
            'empresa_id'     => $this->empresa->id,
            'razon_social'   => '=HYPERLINK("http://evil.com","Click")',
            'rut'            => '11.111.111-1',
            'codigo_interno' => 'EVIL01',
            'pais_iso'       => 'CL',
            'moneda_defecto' => 'CLP',
        ]);

        $this->actingAs($this->usuario)->postJson('/api/facturas', [
            'empresa_id'      => $this->empresa->id,
            'proveedor_id'    => $proveedor->id,
            'numero_factura'  => 'F-CSV-001',
            'fecha_emision'   => now()->format('Y-m-d'),
            'monto_neto'      => 10000,
            'monto_iva'       => 1900,
            'monto_bruto'     => 11900,
            'tipo_documento'  => 'FACTURA',
            'cuentaDestino'   => '410101',
            'cuentaIva'       => '353350',
            'cuentaProveedor' => '352105',
        ])->assertStatus(201);

        $res = $this->actingAs($this->usuario)
            ->get('/api/facturas/exportar/excel');

        $res->assertOk();
        $contenido = $res->getContent();

        $this->assertStringNotContainsString(',=HYPERLINK', $contenido);
        $this->assertStringContainsString("'=HYPERLINK", $contenido);
    }

    public function test_exportacion_csv_escapa_campo_con_formula_suma(): void
    {
        $proveedor = Proveedor::create([
            'empresa_id'     => $this->empresa->id,
            'razon_social'   => '+SUM(A1:A10)',
            'rut'            => '22.222.222-2',
            'codigo_interno' => 'SUM01',
            'pais_iso'       => 'CL',
            'moneda_defecto' => 'CLP',
        ]);

        $this->actingAs($this->usuario)->postJson('/api/facturas', [
            'empresa_id'      => $this->empresa->id,
            'proveedor_id'    => $proveedor->id,
            'numero_factura'  => 'F-CSV-002',
            'fecha_emision'   => now()->format('Y-m-d'),
            'monto_neto'      => 5000,
            'monto_iva'       => 950,
            'monto_bruto'     => 5950,
            'tipo_documento'  => 'FACTURA',
            'cuentaDestino'   => '410101',
            'cuentaIva'       => '353350',
            'cuentaProveedor' => '352105',
        ])->assertStatus(201);

        $res = $this->actingAs($this->usuario)
            ->get('/api/facturas/exportar/excel');

        $res->assertOk();
        $contenido = $res->getContent();

        $this->assertStringNotContainsString(',+SUM', $contenido);
        $this->assertStringContainsString("'+SUM", $contenido);
    }

    public function test_exportacion_csv_no_escapa_razon_social_normal(): void
    {
        $proveedor = Proveedor::create([
            'empresa_id'     => $this->empresa->id,
            'razon_social'   => 'Proveedor Normal SpA',
            'rut'            => '33.333.333-3',
            'codigo_interno' => 'NORM01',
            'pais_iso'       => 'CL',
            'moneda_defecto' => 'CLP',
        ]);

        $this->actingAs($this->usuario)->postJson('/api/facturas', [
            'empresa_id'      => $this->empresa->id,
            'proveedor_id'    => $proveedor->id,
            'numero_factura'  => 'F-CSV-003',
            'fecha_emision'   => now()->format('Y-m-d'),
            'monto_neto'      => 8000,
            'monto_iva'       => 1520,
            'monto_bruto'     => 9520,
            'tipo_documento'  => 'FACTURA',
            'cuentaDestino'   => '410101',
            'cuentaIva'       => '353350',
            'cuentaProveedor' => '352105',
        ])->assertStatus(201);

        $res = $this->actingAs($this->usuario)
            ->get('/api/facturas/exportar/excel');

        $res->assertOk();
        $contenido = $res->getContent();

        $this->assertStringContainsString('"Proveedor Normal SpA"', $contenido);
    }
}
