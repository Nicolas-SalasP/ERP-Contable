<?php

namespace Tests\Feature\Activos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Core\Models\Pais;
use App\Domains\Activos\Models\ActivoFijo;
use App\Domains\Contabilidad\Models\PlanCuenta;

class ActivoFijoDepreciacionMatematicaTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Empresa Matemática', 'regimen_tributario' => '14_D3', 'tasa_impuesto' => 25.00]);
        $this->usuario = User::create(['nombre' => 'Matemático', 'email' => 'math@erp.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $this->rolSuperAdmin->id, 'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id]);

        PlanCuenta::create(['empresa_id' => $this->empresa->id, 'codigo' => '112105', 'nombre' => 'Activo', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);
        PlanCuenta::create(['empresa_id' => $this->empresa->id, 'codigo' => '112106', 'nombre' => 'Depreciacion', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);
        PlanCuenta::create(['empresa_id' => $this->empresa->id, 'codigo' => '609102', 'nombre' => 'Gasto', 'tipo' => 'GASTO', 'imputable' => true, 'activo' => true]);
    }

    public function test_depreciacion_exacta_primer_mes_sin_residual()
    {
        $activo = ActivoFijo::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'AF-MATH-1',
            'nombre' => 'Activo Prueba 1',
            'valor_adquisicion' => 120000,
            'vida_util_meses' => 12,
            'fecha_adquisicion' => now()->startOfMonth(),
            'valor_residual' => 0,
            'depreciacion_acumulada' => 0,
            'estado' => 'ACTIVO',
            'cuenta_activo_codigo' => '112105',
            'cuenta_depreciacion_codigo' => '112106',
            'cuenta_gasto_codigo' => '609102'
        ]);

        $this->actingAs($this->usuario)->postJson('/api/activos/depreciar-mes', ['mes_anio' => now()->format('Y-m')]);

        $this->assertEquals(10000, $activo->fresh()->depreciacion_acumulada);
    }

    public function test_depreciacion_con_decimales_se_redondea_correctamente()
    {
        $activo = ActivoFijo::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'AF-MATH-2',
            'nombre' => 'Activo Decimal',
            'valor_adquisicion' => 10000,
            'vida_util_meses' => 3,
            'fecha_adquisicion' => now()->startOfMonth(),
            'valor_residual' => 0,
            'depreciacion_acumulada' => 0,
            'estado' => 'ACTIVO',
            'cuenta_activo_codigo' => '112105',
            'cuenta_depreciacion_codigo' => '112106',
            'cuenta_gasto_codigo' => '609102'
        ]);

        $this->actingAs($this->usuario)->postJson('/api/activos/depreciar-mes', ['mes_anio' => now()->format('Y-m')]);

        $this->assertEquals(3333, $activo->fresh()->depreciacion_acumulada);
    }

    public function test_no_deprecia_en_meses_anteriores_a_la_adquisicion()
    {
        $activo = ActivoFijo::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'AF-MATH-3',
            'nombre' => 'Futuro',
            'valor_adquisicion' => 500000,
            'vida_util_meses' => 10,
            'fecha_adquisicion' => now()->addMonths(2)->format('Y-m-d'), // Comprado en el futuro
            'valor_residual' => 1,
            'depreciacion_acumulada' => 0,
            'estado' => 'ACTIVO',
            'cuenta_activo_codigo' => '112105',
            'cuenta_depreciacion_codigo' => '112106',
            'cuenta_gasto_codigo' => '609102'
        ]);

        $this->actingAs($this->usuario)->postJson('/api/activos/depreciar-mes', ['mes_anio' => now()->format('Y-m')]);

        $this->assertEquals(0, $activo->fresh()->depreciacion_acumulada);
    }

    public function test_activo_totalmente_depreciado_no_genera_nuevos_cargos()
    {
        $activo = ActivoFijo::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'AF-MATH-4',
            'nombre' => 'Viejo',
            'valor_adquisicion' => 100000,
            'vida_util_meses' => 10,
            'fecha_adquisicion' => now()->subYears(2),
            'valor_residual' => 1,
            'depreciacion_acumulada' => 99999,
            'estado' => 'ACTIVO',
            'cuenta_activo_codigo' => '112105',
            'cuenta_depreciacion_codigo' => '112106',
            'cuenta_gasto_codigo' => '609102'
        ]);

        $this->actingAs($this->usuario)->postJson('/api/activos/depreciar-mes', ['mes_anio' => now()->format('Y-m')]);

        $this->assertEquals(99999, $activo->fresh()->depreciacion_acumulada);
    }

    public function test_ultimo_mes_de_depreciacion_ajusta_cuadratura_exacta()
    {
        $activo = ActivoFijo::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'AF-MATH-5',
            'nombre' => 'Cuadratura',
            'valor_adquisicion' => 10000,
            'vida_util_meses' => 3,
            'fecha_adquisicion' => now()->subMonths(2),
            'valor_residual' => 0,
            'depreciacion_acumulada' => 6666,
            'estado' => 'ACTIVO',
            'cuenta_activo_codigo' => '112105',
            'cuenta_depreciacion_codigo' => '112106',
            'cuenta_gasto_codigo' => '609102'
        ]);

        $this->actingAs($this->usuario)->postJson('/api/activos/depreciar-mes', ['mes_anio' => now()->format('Y-m')]);

        $this->assertEquals(10000, $activo->fresh()->depreciacion_acumulada);
    }
}