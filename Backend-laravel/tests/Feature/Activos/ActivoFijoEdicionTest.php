<?php

namespace Tests\Feature\Activos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Activos\Models\ActivoFijo;
use App\Domains\Contabilidad\Models\PlanCuenta;

class ActivoFijoEdicionTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Edicion SPA']);
        $this->usuario = User::create([
            'nombre' => 'Editor',
            'email' => 'edit@erp.cl',
            'password' => bcrypt('123'),
            'empresa_id' => $this->empresa->id,
            'rol_id' => $this->rolSuperAdmin->id,
            'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id,
        ]);

        PlanCuenta::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => '120101', 'nombre' => 'Equipos', 'tipo' => 'ACTIVO',
            'imputable' => true, 'activo' => true,
        ]);
        PlanCuenta::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => '120151', 'nombre' => 'Dep Ac', 'tipo' => 'ACTIVO',
            'imputable' => true, 'activo' => true,
        ]);
        PlanCuenta::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => '510101', 'nombre' => 'Gasto Dep', 'tipo' => 'GASTO',
            'imputable' => true, 'activo' => true,
        ]);
    }

    private function crearActivo(array $overrides = []): ActivoFijo
    {
        return ActivoFijo::create(array_merge([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'AF-EDIT-' . uniqid(),
            'nombre' => 'Notebook Original',
            'descripcion' => 'Descripcion original',
            'cuenta_activo_codigo' => '120101',
            'cuenta_depreciacion_codigo' => '120151',
            'cuenta_gasto_codigo' => '510101',
            'valor_adquisicion' => 500000,
            'fecha_adquisicion' => '2026-01-15',
            'vida_util_meses' => 60,
            'valor_residual' => 1,
            'estado' => 'ACTIVO',
        ], $overrides));
    }

    public function test_puede_editar_nombre_y_descripcion_de_activo_operativo()
    {
        $activo = $this->crearActivo();

        $response = $this->actingAs($this->usuario)->putJson("/api/activos/{$activo->id}", [
            'nombre' => 'Notebook Actualizado',
            'descripcion' => 'Descripcion nueva',
        ]);

        $response->assertStatus(200);

        $activoFresh = $activo->fresh();
        $this->assertEquals('Notebook Actualizado', $activoFresh->nombre);
        $this->assertEquals('Descripcion nueva', $activoFresh->descripcion);
    }

    public function test_ignora_intentos_de_modificar_valores_contables_vaya_api_put()
    {
        $activo = $this->crearActivo([
            'valor_adquisicion' => 500000,
            'vida_util_meses' => 60,
        ]);

        $response = $this->actingAs($this->usuario)->putJson("/api/activos/{$activo->id}", [
            'nombre' => 'Solo cambia nombre',
            'valor_adquisicion' => 999999999,
            'vida_util_meses' => 1,
            'valor_residual' => 999999,
        ]);

        $response->assertStatus(200);

        $activoFresh = $activo->fresh();
        $this->assertEquals('Solo cambia nombre', $activoFresh->nombre);
        $this->assertEquals(500000, (float) $activoFresh->valor_adquisicion);
        $this->assertEquals(60, $activoFresh->vida_util_meses);
        $this->assertEquals(1, (float) $activoFresh->valor_residual);
    }

    public function test_no_puede_editar_activo_dado_de_baja()
    {
        $activo = $this->crearActivo(['estado' => 'DADO_DE_BAJA']);

        $response = $this->actingAs($this->usuario)->putJson("/api/activos/{$activo->id}", [
            'nombre' => 'Intento editar baja',
        ]);

        $response->assertStatus(400);
    }

    public function test_edicion_de_activo_ajeno_retorna_404()
    {
        $otraEmpresa = Empresa::create(['rut' => '88.888.888-8', 'razon_social' => 'Otra SPA']);
        $activoAjeno = ActivoFijo::create([
            'empresa_id' => $otraEmpresa->id,
            'codigo' => 'AF-AJENO-' . uniqid(),
            'nombre' => 'Activo Ajeno',
            'cuenta_activo_codigo' => '120101',
            'cuenta_depreciacion_codigo' => '120151',
            'cuenta_gasto_codigo' => '510101',
            'valor_adquisicion' => 100000,
            'fecha_adquisicion' => '2026-01-01',
            'vida_util_meses' => 60,
            'valor_residual' => 1,
            'estado' => 'ACTIVO',
        ]);

        $response = $this->actingAs($this->usuario)->putJson("/api/activos/{$activoAjeno->id}", [
            'nombre' => 'Intento IDOR',
        ]);

        $response->assertStatus(404);
        $this->assertEquals('Activo Ajeno', $activoAjeno->fresh()->nombre);
    }
}
