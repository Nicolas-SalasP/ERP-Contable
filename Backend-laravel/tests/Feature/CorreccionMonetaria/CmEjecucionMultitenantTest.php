<?php

namespace Tests\Feature\CorreccionMonetaria;

use App\Domains\Core\Models\Empresa;
use App\Domains\CorreccionMonetaria\Models\CmEjecucion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class CmEjecucionMultitenantTest extends TestCase
{
    use PreparaEntornoBase;
    use RefreshDatabase;

    public function test_cm_ejecucion_aisla_por_empresa_con_el_scope_global(): void
    {
        $this->prepararEntornoBase();
        [$empresaA, $usuarioA] = $this->crearEmpresaConAdmin();
        [$empresaB] = $this->crearEmpresaConAdmin();

        CmEjecucion::create([
            'empresa_id' => $empresaA->id,
            'periodo_mes' => 12,
            'periodo_anio' => 2025,
            'tipo' => 'anual',
            'estado' => 'ejecutada',
            'factor_ipc_utilizado' => 1.0,
            'variacion_porcentual' => 0,
            'total_ajuste_activos' => 0,
            'total_ajuste_depreciacion' => 0,
            'total_ajuste_patrimonio' => 0,
            'total_ajuste_existencias' => 0,
            'total_ajuste_pasivos' => 0,
            'total_cm_neto' => 0,
        ]);

        CmEjecucion::create([
            'empresa_id' => $empresaB->id,
            'periodo_mes' => 12,
            'periodo_anio' => 2025,
            'tipo' => 'anual',
            'estado' => 'ejecutada',
            'factor_ipc_utilizado' => 1.0,
            'variacion_porcentual' => 0,
            'total_ajuste_activos' => 0,
            'total_ajuste_depreciacion' => 0,
            'total_ajuste_patrimonio' => 0,
            'total_ajuste_existencias' => 0,
            'total_ajuste_pasivos' => 0,
            'total_cm_neto' => 0,
        ]);

        $this->actingAs($usuarioA);

        $this->assertSame(1, CmEjecucion::count(), 'El scope global debe ocultar las ejecuciones de otras empresas');
        $this->assertSame($empresaA->id, CmEjecucion::first()->empresa_id);
    }

    public function test_onboarding_de_empresa_nueva_sigue_creando_config_cm_pese_al_scope_de_cmejecucion(): void
    {
        $this->prepararEntornoBase();
        [, $usuarioStaff] = $this->crearEmpresaConAdmin();

        $this->actingAs($usuarioStaff);

        $empresaNueva = Empresa::create([
            'rut' => '99.999.999-9',
            'razon_social' => 'Onboarding Nueva SpA',
        ]);

        $this->assertDatabaseHas('cm_configuracion_empresa', [
            'empresa_id' => $empresaNueva->id,
        ]);
    }
}
