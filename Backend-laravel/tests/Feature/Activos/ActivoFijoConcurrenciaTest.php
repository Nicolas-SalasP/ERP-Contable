<?php

namespace Tests\Feature\Activos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Activos\Models\ActivoFijo;
use App\Domains\Contabilidad\Models\PlanCuenta;
use Illuminate\Support\Facades\DB;

class ActivoFijoConcurrenciaTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario1;
    protected $usuario2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empresa = Empresa::create(['rut' => '11.111.111-1', 'razon_social' => 'Empresa Concurrente', 'regimen_tributario' => '14_D3', 'tasa_impuesto' => 25.00]);

        $this->usuario1 = User::create(['nombre' => 'User1', 'email' => 'u1@erp.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $this->rolSuperAdmin->id, 'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id]);
        $this->usuario2 = User::create(['nombre' => 'User2', 'email' => 'u2@erp.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $this->rolSuperAdmin->id, 'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id]);

        PlanCuenta::create(['empresa_id' => $this->empresa->id, 'codigo' => '112105', 'nombre' => 'Activo', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);
        PlanCuenta::create(['empresa_id' => $this->empresa->id, 'codigo' => '112106', 'nombre' => 'Dep', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);
        PlanCuenta::create(['empresa_id' => $this->empresa->id, 'codigo' => '609102', 'nombre' => 'Gasto', 'tipo' => 'GASTO', 'imputable' => true, 'activo' => true]);
    }

    public function test_bloqueo_evita_doble_depreciacion_en_el_mismo_mes()
    {
        $activo = ActivoFijo::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'AF-CONC-1',
            'nombre' => 'Máquina Test',
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

        $mesADepreciar = now()->format('Y-m');

        $res1 = $this->actingAs($this->usuario1)->postJson('/api/activos/depreciar-mes', ['mes_anio' => $mesADepreciar]);
        $res1->assertStatus(200);

        $res2 = $this->actingAs($this->usuario2)->postJson('/api/activos/depreciar-mes', ['mes_anio' => $mesADepreciar]);
        $res2->assertStatus(422);

        $this->assertEquals(10000, $activo->fresh()->depreciacion_acumulada);
    }
}