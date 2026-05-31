<?php

namespace Tests\Feature\Activos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Core\Models\Pais;
use App\Domains\Activos\Models\ActivoFijo;
use App\Domains\Contabilidad\Models\PlanCuenta;

class ActivoFijoDepreciacionExtremaTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        // Fecha fija a mitad de mes para evitar overflow de Carbon en subMonths()
        // en ejecuciones realizadas en días 29/30/31.
        Carbon::setTestNow(Carbon::create(2026, 5, 15, 12, 0, 0));

        $this->prepararEntornoBase();
        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Empresa Extrema', 'regimen_tributario' => '14_A']);
        $this->usuario = User::create(['nombre' => 'Extremo', 'email' => 'ext@erp.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $this->rolSuperAdmin->id, 'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id]);

        PlanCuenta::create(['empresa_id' => $this->empresa->id, 'codigo' => '112105', 'nombre' => 'Activo', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);
        PlanCuenta::create(['empresa_id' => $this->empresa->id, 'codigo' => '112106', 'nombre' => 'Depreciacion', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);
        PlanCuenta::create(['empresa_id' => $this->empresa->id, 'codigo' => '609102', 'nombre' => 'Gasto', 'tipo' => 'GASTO', 'imputable' => true, 'activo' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);

        parent::tearDown();
    }

    public function test_depreciacion_es_funcional_en_el_mismo_mes_de_adquisicion()
    {
        $activo = ActivoFijo::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'AF-EXT-1',
            'nombre' => 'Silla',
            'valor_adquisicion' => 10000,
            'vida_util_meses' => 10,
            'fecha_adquisicion' => now(),
            'valor_residual' => 0,
            'depreciacion_acumulada' => 0,
            'estado' => 'ACTIVO',
            'cuenta_activo_codigo' => '112105',
            'cuenta_depreciacion_codigo' => '112106',
            'cuenta_gasto_codigo' => '609102'
        ]);

        $this->actingAs($this->usuario)->postJson('/api/activos/depreciar-mes', ['mes_anio' => now()->format('Y-m')]);

        $this->assertEquals(1000, $activo->fresh()->depreciacion_acumulada);
    }

    public function test_activo_de_muy_bajo_valor_se_deprecia_correctamente()
    {
        $activo = ActivoFijo::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'AF-EXT-2',
            'nombre' => 'Tornillo',
            'valor_adquisicion' => 2,
            'vida_util_meses' => 2,
            'fecha_adquisicion' => now(),
            'valor_residual' => 0,
            'depreciacion_acumulada' => 0,
            'estado' => 'ACTIVO',
            'cuenta_activo_codigo' => '112105',
            'cuenta_depreciacion_codigo' => '112106',
            'cuenta_gasto_codigo' => '609102'
        ]);

        $this->actingAs($this->usuario)->postJson('/api/activos/depreciar-mes', ['mes_anio' => now()->format('Y-m')]);

        $this->assertEquals(1, $activo->fresh()->depreciacion_acumulada);
    }

    public function test_multiples_meses_depreciados_consecutivamente()
    {
        $activo = ActivoFijo::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'AF-EXT-3',
            'nombre' => 'Mesa',
            'valor_adquisicion' => 10000,
            'vida_util_meses' => 10,
            'fecha_adquisicion' => now()->subMonths(3),
            'valor_residual' => 0,
            'depreciacion_acumulada' => 0,
            'estado' => 'ACTIVO',
            'cuenta_activo_codigo' => '112105',
            'cuenta_depreciacion_codigo' => '112106',
            'cuenta_gasto_codigo' => '609102'
        ]);

        // Ejecutar mes 1
        $this->actingAs($this->usuario)->postJson('/api/activos/depreciar-mes', ['mes_anio' => now()->subMonths(2)->format('Y-m')]);
        $this->assertEquals(1000, $activo->fresh()->depreciacion_acumulada);

        // Ejecutar mes 2
        $this->actingAs($this->usuario)->postJson('/api/activos/depreciar-mes', ['mes_anio' => now()->subMonths(1)->format('Y-m')]);
        $this->assertEquals(2000, $activo->fresh()->depreciacion_acumulada);

        // Ejecutar mes 3
        $this->actingAs($this->usuario)->postJson('/api/activos/depreciar-mes', ['mes_anio' => now()->format('Y-m')]);
        $this->assertEquals(3000, $activo->fresh()->depreciacion_acumulada);
    }
}