<?php

namespace Tests\Feature\Contabilidad;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Contabilidad\Models\PlanCuenta;

class PlanCuentasFocalizadoTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        [$this->empresa, $this->usuario] = $this->crearEmpresaConAdmin();
    }

    public function test_codigo_de_cuenta_chileno_acepta_formato_estandar_de_seis_digitos()
    {
        $codigosValidos = ['110101', '352105', '410101', '610105', '999999'];

        foreach ($codigosValidos as $codigo) {
            $cuenta = PlanCuenta::create([
                'empresa_id' => $this->empresa->id,
                'codigo' => $codigo,
                'nombre' => 'Cuenta ' . $codigo,
                'tipo' => 'ACTIVO',
                'imputable' => true,
                'activo' => true,
            ]);

            $this->assertNotNull($cuenta->id);
            $this->assertEquals($codigo, $cuenta->codigo);
        }
    }

    public function test_codigo_de_cuenta_es_unico_por_empresa_pero_no_global()
    {
        $empresaB = $this->crearEmpresa(['razon_social' => 'Empresa B']);

        PlanCuenta::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => '110101',
            'nombre' => 'Caja A',
            'tipo' => 'ACTIVO',
            'imputable' => true,
            'activo' => true,
        ]);

        PlanCuenta::create([
            'empresa_id' => $empresaB->id,
            'codigo' => '110101',
            'nombre' => 'Caja B',
            'tipo' => 'ACTIVO',
            'imputable' => true,
            'activo' => true,
        ]);

        $cuentaA = PlanCuenta::where('empresa_id', $this->empresa->id)
            ->where('codigo', '110101')->first();
        $cuentaB = PlanCuenta::where('empresa_id', $empresaB->id)
            ->where('codigo', '110101')->first();

        $this->assertEquals('Caja A', $cuentaA->nombre);
        $this->assertEquals('Caja B', $cuentaB->nombre);
        $this->assertNotEquals($cuentaA->id, $cuentaB->id);
    }

    public function test_cuenta_inactiva_no_aparece_en_listado_imputables()
    {
        PlanCuenta::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => '110101',
            'nombre' => 'Activa',
            'tipo' => 'ACTIVO',
            'imputable' => true,
            'activo' => true,
        ]);

        PlanCuenta::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => '110102',
            'nombre' => 'Desactivada',
            'tipo' => 'ACTIVO',
            'imputable' => true,
            'activo' => false,
        ]);

        $imputables = PlanCuenta::where('empresa_id', $this->empresa->id)
            ->where('imputable', true)
            ->where('activo', true)
            ->get();

        $this->assertCount(1, $imputables);
        $this->assertEquals('Activa', $imputables->first()->nombre);
    }

    public function test_listado_imputables_excluye_cuentas_padre_no_imputables()
    {
        PlanCuenta::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => '11',
            'nombre' => 'Activos Corrientes',
            'tipo' => 'ACTIVO',
            'imputable' => false,
            'activo' => true,
        ]);

        PlanCuenta::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => '110101',
            'nombre' => 'Caja',
            'tipo' => 'ACTIVO',
            'imputable' => true,
            'activo' => true,
        ]);

        $imputables = PlanCuenta::where('empresa_id', $this->empresa->id)
            ->where('imputable', true)
            ->where('activo', true)
            ->get();

        $this->assertCount(1, $imputables);
        $this->assertEquals('Caja', $imputables->first()->nombre);
    }
}
