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
use App\Domains\Contabilidad\Models\AsientoContable;
use Illuminate\Support\Facades\DB;

class ActivoFijoAsientosContablesTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Empresa Contable SpA', 'regimen_tributario' => '14_D3', 'tasa_impuesto' => 25.00]);
        $this->usuario = User::create(['nombre' => 'Contador', 'email' => 'conta@erp.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $this->rolSuperAdmin->id, 'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id]);

        PlanCuenta::create(['empresa_id' => $this->empresa->id, 'codigo' => '112105', 'nombre' => 'Activo', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);
        PlanCuenta::create(['empresa_id' => $this->empresa->id, 'codigo' => '112106', 'nombre' => 'Depreciacion', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);
        PlanCuenta::create(['empresa_id' => $this->empresa->id, 'codigo' => '609102', 'nombre' => 'Gasto', 'tipo' => 'GASTO', 'imputable' => true, 'activo' => true]);
        PlanCuenta::create(['empresa_id' => $this->empresa->id, 'codigo' => '999999', 'nombre' => 'Pérdidas', 'tipo' => 'GASTO', 'imputable' => true, 'activo' => true]);
    }

    public function test_depreciacion_mensual_genera_asiento_cuadrado_al_centavo()
    {
        ActivoFijo::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'AF-CONT-1',
            'nombre' => 'Vehículo',
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

        $asiento = AsientoContable::where('empresa_id', $this->empresa->id)->where('origen_modulo', 'activos')->latest()->first();

        $this->assertNotNull($asiento);
        $this->assertEquals('MAYORIZADO', $asiento->estado);

        $this->assertDatabaseHas('detalles_asiento', [
            'asiento_id' => $asiento->id,
            'cuenta_contable' => '609102',
            'debe' => 10000,
            'haber' => 0
        ]);

        $this->assertDatabaseHas('detalles_asiento', [
            'asiento_id' => $asiento->id,
            'cuenta_contable' => '112106',
            'debe' => 0,
            'haber' => 10000
        ]);
    }

    public function test_depreciacion_de_multiples_activos_genera_un_solo_asiento_consolidado()
    {
        for ($i = 1; $i <= 3; $i++) {
            ActivoFijo::create([
                'empresa_id' => $this->empresa->id,
                'codigo' => "AF-CONT-M{$i}",
                'nombre' => "Activo $i",
                'valor_adquisicion' => 12000,
                'vida_util_meses' => 12,
                'fecha_adquisicion' => now()->startOfMonth(),
                'valor_residual' => 0,
                'depreciacion_acumulada' => 0,
                'estado' => 'ACTIVO',
                'cuenta_activo_codigo' => '112105',
                'cuenta_depreciacion_codigo' => '112106',
                'cuenta_gasto_codigo' => '609102'
            ]);
        }

        $this->actingAs($this->usuario)->postJson('/api/activos/depreciar-mes', ['mes_anio' => now()->format('Y-m')]);

        $cantidadAsientos = AsientoContable::where('empresa_id', $this->empresa->id)->where('origen_modulo', 'activos')->count();
        $this->assertEquals(1, $cantidadAsientos, "Se debió crear un único asiento consolidado, pero se crearon $cantidadAsientos.");

        $asiento = AsientoContable::where('empresa_id', $this->empresa->id)->first();
        $lineas = DB::table('detalles_asiento')->where('asiento_id', $asiento->id)->count();

        $this->assertEquals(6, $lineas, "El asiento consolidado debe tener 6 líneas (Gasto y Contra-Activo por cada AF).");
    }

    public function test_transaccion_rollback_si_falla_el_asiento_contable()
    {
        $activo = ActivoFijo::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'AF-FALLA-1',
            'nombre' => 'Falla',
            'valor_adquisicion' => 120000,
            'vida_util_meses' => 12,
            'fecha_adquisicion' => now()->startOfMonth(),
            'valor_residual' => 0,
            'depreciacion_acumulada' => 0,
            'estado' => 'ACTIVO',
            'cuenta_activo_codigo' => '112105',
            'cuenta_depreciacion_codigo' => '888888',
            'cuenta_gasto_codigo' => '609102'
        ]);

        $this->actingAs($this->usuario)->postJson('/api/activos/depreciar-mes', ['mes_anio' => now()->format('Y-m')]);
        $this->assertEquals(0, $activo->fresh()->depreciacion_acumulada, "Fallo el Rollback: La BD guardó el cálculo aunque el Asiento falló.");
        $this->assertEquals(0, AsientoContable::count());
    }
}