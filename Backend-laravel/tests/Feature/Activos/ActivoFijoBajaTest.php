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

class ActivoFijoBajaTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Empresa Bajas', 'regimen_tributario' => '14_D3', 'tasa_impuesto' => 25.00]);
        $this->usuario = User::create(['nombre' => 'Bodeguero', 'email' => 'bajas@erp.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $this->rolSuperAdmin->id, 'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id]);

        PlanCuenta::create(['empresa_id' => $this->empresa->id, 'codigo' => '112105', 'nombre' => 'Activo', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);
        PlanCuenta::create(['empresa_id' => $this->empresa->id, 'codigo' => '112106', 'nombre' => 'Depreciacion', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);
        PlanCuenta::create(['empresa_id' => $this->empresa->id, 'codigo' => '999999', 'nombre' => 'Pérdidas', 'tipo' => 'GASTO', 'imputable' => true, 'activo' => true]);
    }

    public function test_rechaza_baja_de_activo_ya_dado_de_baja()
    {
        $activo = ActivoFijo::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'AF-BAJA-1',
            'nombre' => 'Server Quemado',
            'valor_adquisicion' => 10000,
            'vida_util_meses' => 10,
            'fecha_adquisicion' => now(),
            'valor_residual' => 0,
            'depreciacion_acumulada' => 0,
            'estado' => 'DADO_DE_BAJA'
        ]);

        $response = $this->actingAs($this->usuario)->putJson("/api/activos/{$activo->id}/baja", ['motivo_baja' => 'Test']);

        $response->assertStatus(400)
            ->assertSee('Solo se pueden dar de baja activos que se encuentren operativos');
    }

    public function test_baja_de_activo_con_cero_depreciacion_envia_todo_a_perdida()
    {
        $activo = ActivoFijo::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'AF-BAJA-2',
            'nombre' => 'Notebook Robado',
            'valor_adquisicion' => 500000,
            'vida_util_meses' => 36,
            'fecha_adquisicion' => now(),
            'valor_residual' => 0,
            'depreciacion_acumulada' => 0,
            'estado' => 'ACTIVO',
            'cuenta_activo_codigo' => '112105',
            'cuenta_depreciacion_codigo' => '112106'
        ]);

        $this->actingAs($this->usuario)->putJson("/api/activos/{$activo->id}/baja", ['motivo_baja' => 'Robo']);

        $asiento = AsientoContable::where('empresa_id', $this->empresa->id)->first();

        $this->assertDatabaseHas('detalles_asiento', [
            'asiento_id' => $asiento->id,
            'cuenta_contable' => '999999',
            'debe' => 500000,
            'haber' => 0
        ]);
    }

    public function test_baja_de_activo_totalmente_depreciado_no_genera_perdida()
    {
        $activo = ActivoFijo::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'AF-BAJA-3',
            'nombre' => 'Mesa Vieja',
            'valor_adquisicion' => 100000,
            'vida_util_meses' => 36,
            'fecha_adquisicion' => now()->subYears(5),
            'valor_residual' => 0,
            'depreciacion_acumulada' => 100000,
            'estado' => 'ACTIVO',
            'cuenta_activo_codigo' => '112105',
            'cuenta_depreciacion_codigo' => '112106'
        ]);

        $this->actingAs($this->usuario)->putJson("/api/activos/{$activo->id}/baja", ['motivo_baja' => 'Basura']);

        $asiento = AsientoContable::where('empresa_id', $this->empresa->id)->first();

        $this->assertDatabaseMissing('detalles_asiento', [
            'asiento_id' => $asiento->id,
            'cuenta_contable' => '999999',
        ]);

        $this->assertDatabaseHas('detalles_asiento', [
            'asiento_id' => $asiento->id,
            'cuenta_contable' => '112106',
            'debe' => 100000,
            'haber' => 0
        ]);
    }
}