<?php

namespace Tests\Feature\Activos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Activos\Models\ActivoFijo;
use App\Domains\Activos\Services\ActivoFijoService;
use App\Domains\Contabilidad\Models\PlanCuenta;


class CodigoActivoCorrelativoPorEmpresaTest extends TestCase
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

        // Cuentas necesarias
        foreach ([$this->empresaA->id, $this->empresaB->id] as $empresaId) {
            PlanCuenta::create([
                'empresa_id' => $empresaId, 'codigo' => '120101',
                'nombre' => 'Equipos', 'tipo' => 'ACTIVO',
                'imputable' => true, 'activo' => true,
            ]);
            PlanCuenta::create([
                'empresa_id' => $empresaId, 'codigo' => '120151',
                'nombre' => 'Dep Ac Equipos', 'tipo' => 'ACTIVO',
                'imputable' => true, 'activo' => true,
            ]);
            PlanCuenta::create([
                'empresa_id' => $empresaId, 'codigo' => '510101',
                'nombre' => 'Gasto Dep', 'tipo' => 'GASTO',
                'imputable' => true, 'activo' => true,
            ]);
        }
    }

    private function crearActivoYDevolverCodigo(int $empresaId, string $nombre = 'Test'): string
    {
        $service = app(ActivoFijoService::class);
        $activo = $service->registrarActivo([
            'empresa_id' => $empresaId,
            'nombre' => $nombre,
            'cuenta_activo_codigo' => '120101',
            'cuenta_depreciacion_codigo' => '120151',
            'cuenta_gasto_codigo' => '510101',
            'valor_adquisicion' => 100000,
            'fecha_adquisicion' => '2026-04-01',
            'vida_util_meses' => 60,
            'valor_residual' => 1,
        ]);
        return $activo->fresh()->codigo;
    }

    public function test_primer_activo_de_empresa_es_af_00001()
    {
        $codigo = $this->crearActivoYDevolverCodigo($this->empresaA->id, 'Computadora');
        $this->assertEquals('AF-00001', $codigo);
    }

    public function test_secuencia_correlativa_no_se_mezcla_entre_empresas()
    {
        $a1 = $this->crearActivoYDevolverCodigo($this->empresaA->id, 'A1');
        $b1 = $this->crearActivoYDevolverCodigo($this->empresaB->id, 'B1');
        $a2 = $this->crearActivoYDevolverCodigo($this->empresaA->id, 'A2');
        $b2 = $this->crearActivoYDevolverCodigo($this->empresaB->id, 'B2');
        $a3 = $this->crearActivoYDevolverCodigo($this->empresaA->id, 'A3');

        $this->assertEquals('AF-00001', $a1);
        $this->assertEquals('AF-00002', $a2);
        $this->assertEquals('AF-00003', $a3);

        $this->assertEquals('AF-00001', $b1);
        $this->assertEquals('AF-00002', $b2);
    }

    public function test_dos_empresas_pueden_tener_mismo_codigo_af_00001()
    {
        $codA = $this->crearActivoYDevolverCodigo($this->empresaA->id, 'A');
        $codB = $this->crearActivoYDevolverCodigo($this->empresaB->id, 'B');

        $this->assertEquals('AF-00001', $codA);
        $this->assertEquals('AF-00001', $codB);

        $cantidad = ActivoFijo::where('codigo', 'AF-00001')->count();
        $this->assertEquals(2, $cantidad,
            "Deben existir 2 activos con codigo AF-00001 (uno por empresa)");
    }

    public function test_unique_compuesto_bloquea_duplicados_en_misma_empresa()
    {
        $this->crearActivoYDevolverCodigo($this->empresaA->id, 'Original');
        $this->expectException(\Illuminate\Database\QueryException::class);

        ActivoFijo::create([
            'empresa_id' => $this->empresaA->id,
            'codigo' => 'AF-00001', // duplicado
            'nombre' => 'Duplicado',
            'cuenta_activo_codigo' => '120101',
            'cuenta_depreciacion_codigo' => '120151',
            'cuenta_gasto_codigo' => '510101',
            'valor_adquisicion' => 50000,
            'fecha_adquisicion' => '2026-04-01',
            'vida_util_meses' => 36,
            'valor_residual' => 1,
            'estado' => 'ACTIVO',
        ]);
    }
}
