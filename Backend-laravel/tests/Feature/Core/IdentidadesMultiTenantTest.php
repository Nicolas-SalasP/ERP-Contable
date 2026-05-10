<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Tesoreria\Models\CuentaBancariaEmpresa;
use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Contabilidad\Models\CentroCosto;

class IdentidadesMultiTenantTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresaA;
    protected $empresaB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empresaA = $this->crearEmpresa();
        $this->empresaB = $this->crearEmpresa();
    }

    private function generarCodigoUnico(): int
    {
        return (int) (time() . rand(100000, 999999));
    }

    public function test_dos_empresas_pueden_registrar_el_mismo_rut_de_cliente()
    {
        $rutComun = '11.111.111-1';

        $clienteA = Cliente::create([
            'empresa_id' => $this->empresaA->id,
            'rut' => $rutComun,
            'razon_social' => 'Cliente Visto Por A',
        ]);

        $clienteB = Cliente::create([
            'empresa_id' => $this->empresaB->id,
            'rut' => $rutComun,
            'razon_social' => 'Cliente Visto Por B',
        ]);

        $this->assertNotEquals($clienteA->id, $clienteB->id);
        $this->assertEquals($rutComun, $clienteA->rut);
        $this->assertEquals($rutComun, $clienteB->rut);

        $clientesA = Cliente::where('empresa_id', $this->empresaA->id)->get();
        $this->assertCount(1, $clientesA);
        $this->assertEquals('Cliente Visto Por A', $clientesA->first()->razon_social);
    }

    public function test_dos_empresas_pueden_registrar_el_mismo_rut_de_proveedor()
    {
        $rutProveedor = '99.999.999-9';

        $provA = Proveedor::create([
            'empresa_id' => $this->empresaA->id,
            'rut' => $rutProveedor,
            'razon_social' => 'Prov Comun',
            'codigo_interno' => 'PA-1',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP',
        ]);

        $provB = Proveedor::create([
            'empresa_id' => $this->empresaB->id,
            'rut' => $rutProveedor,
            'razon_social' => 'Prov Comun (visto desde B)',
            'codigo_interno' => 'PB-1',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP',
        ]);

        $this->assertNotEquals($provA->id, $provB->id);
        $this->assertEquals($rutProveedor, $provA->rut);
        $this->assertEquals($rutProveedor, $provB->rut);
    }

    public function test_mismo_proveedor_puede_emitir_la_misma_factura_a_dos_empresas_distintas()
    {
        $rutProv = '76.111.222-3';

        $provA = Proveedor::create([
            'empresa_id' => $this->empresaA->id,
            'rut' => $rutProv,
            'razon_social' => 'ABC',
            'codigo_interno' => 'A1',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP',
        ]);
        $provB = Proveedor::create([
            'empresa_id' => $this->empresaB->id,
            'rut' => $rutProv,
            'razon_social' => 'ABC',
            'codigo_interno' => 'B1',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP',
        ]);

        $codigoA = $this->generarCodigoUnico();
        $codigoB = $codigoA + 1;

        $facturaA = Factura::create([
            'empresa_id' => $this->empresaA->id,
            'proveedor_id' => $provA->id,
            'tipo' => 'COMPRA',
            'tipo_documento' => 'FACTURA',
            'numero_factura' => '1234',
            'codigo_unico' => $codigoA,
            'fecha_emision' => '2026-04-15',
            'monto_neto' => 100000,
            'monto_iva' => 19000,
            'monto_bruto' => 119000,
            'estado' => 'REGISTRADA',
        ]);

        $facturaB = Factura::create([
            'empresa_id' => $this->empresaB->id,
            'proveedor_id' => $provB->id,
            'tipo' => 'COMPRA',
            'tipo_documento' => 'FACTURA',
            'numero_factura' => '1234',
            'codigo_unico' => $codigoB,
            'fecha_emision' => '2026-04-15',
            'monto_neto' => 200000,
            'monto_iva' => 38000,
            'monto_bruto' => 238000,
            'estado' => 'REGISTRADA',
        ]);

        $this->assertEquals('1234', $facturaA->numero_factura);
        $this->assertEquals('1234', $facturaB->numero_factura);
        $this->assertNotEquals($facturaA->id, $facturaB->id);
    }

    public function test_dos_empresas_pueden_tener_el_mismo_numero_de_cuenta_bancaria()
    {
        $cuentaA = CuentaBancariaEmpresa::create([
            'empresa_id' => $this->empresaA->id,
            'rut_titular' => $this->empresaA->rut,
            'titular' => 'A SpA',
            'banco' => 'Banco de Chile',
            'tipo_cuenta' => 'CORRIENTE',
            'numero_cuenta' => '12345678',
        ]);

        $cuentaB = CuentaBancariaEmpresa::create([
            'empresa_id' => $this->empresaB->id,
            'rut_titular' => $this->empresaB->rut,
            'titular' => 'B SpA',
            'banco' => 'BancoEstado',
            'tipo_cuenta' => 'CORRIENTE',
            'numero_cuenta' => '12345678',
        ]);

        $this->assertNotEquals($cuentaA->id, $cuentaB->id);
        $this->assertEquals('12345678', $cuentaA->numero_cuenta);
        $this->assertEquals('12345678', $cuentaB->numero_cuenta);
    }

    public function test_dos_empresas_pueden_tener_el_mismo_codigo_de_plan_cuenta()
    {
        $cuentaA = PlanCuenta::create([
            'empresa_id' => $this->empresaA->id,
            'codigo' => '110101',
            'nombre' => 'Caja',
            'tipo' => 'ACTIVO',
            'imputable' => true,
            'activo' => true,
        ]);

        $cuentaB = PlanCuenta::create([
            'empresa_id' => $this->empresaB->id,
            'codigo' => '110101',
            'nombre' => 'Caja',
            'tipo' => 'ACTIVO',
            'imputable' => true,
            'activo' => true,
        ]);

        $this->assertNotEquals($cuentaA->id, $cuentaB->id);
        $this->assertEquals('110101', $cuentaA->codigo);
        $this->assertEquals('110101', $cuentaB->codigo);
    }

    public function test_dos_empresas_pueden_tener_el_mismo_codigo_de_centro_costo()
    {
        $ccA = CentroCosto::create([
            'empresa_id' => $this->empresaA->id,
            'codigo' => 'OPERACIONES',
            'nombre' => 'Operaciones',
            'activo' => true,
        ]);

        $ccB = CentroCosto::create([
            'empresa_id' => $this->empresaB->id,
            'codigo' => 'OPERACIONES',
            'nombre' => 'Operaciones',
            'activo' => true,
        ]);

        $this->assertNotEquals($ccA->id, $ccB->id);
        $this->assertEquals('OPERACIONES', $ccA->codigo);
    }

    public function test_dentro_de_la_misma_empresa_no_se_permite_rut_cliente_duplicado()
    {
        Cliente::create([
            'empresa_id' => $this->empresaA->id,
            'rut' => '11.111.111-1',
            'razon_social' => 'Original',
        ]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        Cliente::create([
            'empresa_id' => $this->empresaA->id,
            'rut' => '11.111.111-1',
            'razon_social' => 'Duplicado en A',
        ]);
    }

    public function test_id_interno_de_proveedor_es_global_pero_aislado_por_query()
    {
        $rutProv = '76.999.111-2';

        $provA = Proveedor::create([
            'empresa_id' => $this->empresaA->id,
            'rut' => $rutProv,
            'razon_social' => 'Prov A',
            'codigo_interno' => 'PA1',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP',
        ]);
        $provB = Proveedor::create([
            'empresa_id' => $this->empresaB->id,
            'rut' => $rutProv,
            'razon_social' => 'Prov B',
            'codigo_interno' => 'PB1',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP',
        ]);

        $this->assertNotEquals($provA->id, $provB->id);

        $provsDeA = Proveedor::where('empresa_id', $this->empresaA->id)->get();
        $provsDeB = Proveedor::where('empresa_id', $this->empresaB->id)->get();

        $this->assertCount(1, $provsDeA);
        $this->assertCount(1, $provsDeB);
        $this->assertEquals($provA->id, $provsDeA->first()->id);
        $this->assertEquals($provB->id, $provsDeB->first()->id);
    }
}
