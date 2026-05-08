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
use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Activos\Models\ActivoFijo;

class ActivoFijoDepreciacionTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Empresa Test', 'regimen_tributario' => '14_D3', 'tasa_impuesto' => 25.00]);
        $this->usuario = User::create(['nombre' => 'QA', 'email' => 'qa@erp.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $this->rolSuperAdmin->id, 'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id]);
    }

    public function test_depreciacion_nunca_supera_valor_residual()
    {
        PlanCuenta::create(['empresa_id' => $this->empresa->id, 'codigo' => '410101', 'nombre' => 'Gasto Depreciacion', 'tipo' => 'GASTO', 'imputable' => true, 'activo' => true]);
        PlanCuenta::create(['empresa_id' => $this->empresa->id, 'codigo' => '120102', 'nombre' => 'Depreciacion Acum', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);

        $activo = ActivoFijo::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'AF-00002',
            'nombre' => 'Notebook CEO',
            'valor_adquisicion' => 100000,
            'fecha_adquisicion' => now()->subMonths(10),
            'vida_util_meses' => 10,
            'valor_residual' => 1,
            'depreciacion_acumulada' => 95000,
            'estado' => 'ACTIVO',
            'cuenta_gasto_codigo' => '410101',
            'cuenta_depreciacion_codigo' => '120102'
        ]);

        $response = $this->actingAs($this->usuario)->postJson('/api/activos/depreciar-mes', ['mes_anio' => now()->format('Y-m')]);

        $response->assertStatus(200);
        $this->assertEquals(99999, $activo->fresh()->depreciacion_acumulada, "Error crítico contable: La depreciación cruzó el límite.");
    }

    public function test_depreciacion_sin_activos_operativos_falla_con_gracia()
    {
        $response = $this->actingAs($this->usuario)->postJson('/api/activos/depreciar-mes', ['mes_anio' => now()->format('Y-m')]);
        $response->assertStatus(422)->assertSee('No hay activos fijos operativos');
    }

    public function test_depreciacion_ignora_activos_dados_de_baja_o_pendientes()
    {
        $activoDadoDeBaja = ActivoFijo::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'AF-0099',
            'nombre' => 'Vehículo Antiguo',
            'valor_adquisicion' => 5000000,
            'fecha_adquisicion' => now()->subMonths(5),
            'vida_util_meses' => 60,
            'valor_residual' => 1,
            'depreciacion_acumulada' => 100000,
            'estado' => 'DADO_DE_BAJA'
        ]);

        $this->actingAs($this->usuario)->postJson('/api/activos/depreciar-mes', ['mes_anio' => now()->format('Y-m')]);
        $this->assertEquals(100000, $activoDadoDeBaja->fresh()->depreciacion_acumulada);
    }

    public function test_dar_de_baja_un_activo_cambia_su_estado_correctamente()
    {
        PlanCuenta::create(['empresa_id' => $this->empresa->id, 'codigo' => '999999', 'nombre' => 'Pérdidas', 'tipo' => 'GASTO', 'imputable' => true, 'activo' => true]);
        PlanCuenta::create(['empresa_id' => $this->empresa->id, 'codigo' => '112105', 'nombre' => 'Activo', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);
        PlanCuenta::create(['empresa_id' => $this->empresa->id, 'codigo' => '112106', 'nombre' => 'Depreciacion', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);
        PlanCuenta::create(['empresa_id' => $this->empresa->id, 'codigo' => '609102', 'nombre' => 'Gasto', 'tipo' => 'GASTO', 'imputable' => true, 'activo' => true]);

        $activo = ActivoFijo::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'AF-VENDER',
            'nombre' => 'PC Roto',
            'valor_adquisicion' => 200000,
            'fecha_adquisicion' => now(),
            'vida_util_meses' => 36,
            'estado' => 'ACTIVO',
            'cuenta_activo_codigo' => '112105',
            'cuenta_depreciacion_codigo' => '112106',
            'cuenta_gasto_codigo' => '609102'
        ]);

        $response = $this->actingAs($this->usuario)->putJson("/api/activos/{$activo->id}/baja", [
            'motivo_baja' => 'Equipo dañado sin reparación.'
        ]);

        $response->assertStatus(200);
        $this->assertEquals('DADO_DE_BAJA', $activo->fresh()->estado);
    }
}