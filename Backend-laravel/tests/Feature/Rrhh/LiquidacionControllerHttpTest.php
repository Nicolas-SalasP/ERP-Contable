<?php

namespace Tests\Feature\Rrhh;

use App\Domains\Rrhh\Models\Contrato;
use App\Domains\Rrhh\Models\Empleado;
use App\Domains\Rrhh\Models\IndicadorMensual;
use App\Domains\Rrhh\Models\Liquidacion;
use App\Domains\Rrhh\Models\ParametroPrevisional;
use App\Domains\Rrhh\Models\TablaImpuestoUnico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * LiquidacionCalculoTest/LiquidacionSueldoTest cubren el motor de cálculo llamando
 * a LiquidacionService directo. Ningún test hasta ahora atraviesa las rutas HTTP
 * reales de LiquidacionController (index/show/calcular/emitir/anular): validación,
 * permiso, multitenant y los códigos de estado de RrhhException nunca se ejercitaron.
 */
class LiquidacionControllerHttpTest extends TestCase
{
    use PreparaEntornoBase, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->cargarParametrosLegales();
    }

    private function cargarParametrosLegales(): void
    {
        ParametroPrevisional::create([
            'vigente_desde' => '2026-01-01', 'vigente_hasta' => null,
            'afp_cotizacion_pct' => 10.0,
            'afp_comisiones_json' => ['Habitat' => 1.27],
            'afp_sis_pct' => 1.62, 'tope_imponible_uf' => 90.0, 'salud_fonasa_pct' => 7.0,
            'afc_indefinido_trabajador_pct' => 0.6, 'afc_indefinido_empleador_pct' => 2.4,
            'afc_plazo_fijo_trabajador_pct' => 0.0, 'afc_plazo_fijo_empleador_pct' => 3.0,
            'afc_tope_imponible_uf' => 135.2, 'imm' => 539000,
            'gratificacion_tope_mensual_factor' => 4.75, 'cotizacion_adicional_empleador_pct' => 1.0,
            'mutual_cotizacion_basica_pct' => 0.9,
            'asignacion_familiar_tramos_json' => [
                ['hasta_pesos' => 620251, 'monto_por_carga' => 22007],
                ['hasta_pesos' => null, 'monto_por_carga' => 0],
            ],
            'fuente' => 'test',
        ]);

        IndicadorMensual::create(['anio' => 2026, 'mes' => 6, 'uf_valor' => 39850, 'utm_valor' => 71506, 'uta_valor' => 71506 * 12]);

        foreach ([
            [1, 0.0, 13.5, 0.0, 0.0],
            [2, 13.5, 30.0, 0.04, 0.54],
            [3, 30.0, 50.0, 0.08, 1.74],
            [4, 50.0, 70.0, 0.135, 4.49],
            [5, 70.0, 90.0, 0.23, 11.14],
            [6, 90.0, 120.0, 0.304, 17.80],
            [7, 120.0, 310.0, 0.35, 23.80],
            [8, 310.0, null, 0.40, 39.30],
        ] as [$o, $d, $h, $t, $f]) {
            TablaImpuestoUnico::create([
                'anio' => 2026, 'orden' => $o, 'desde_utm' => $d, 'hasta_utm' => $h,
                'tasa' => $t, 'factor_deduccion_utm' => $f,
            ]);
        }
    }

    private function empleadoConContrato(int $empresaId, float $sueldoBase = 900000): Empleado
    {
        $rut = '12.'.rand(100, 999).'.'.rand(100, 999).'-'.rand(0, 9);
        $empleado = Empleado::create([
            'empresa_id' => $empresaId, 'rut' => $rut,
            'nombres' => 'Trabajador HTTP', 'apellido_paterno' => 'Apellido',
            'afp' => 'Habitat', 'tipo_salud' => 'FONASA',
        ]);

        Contrato::create([
            'empresa_id' => $empresaId, 'empleado_id' => $empleado->id,
            'tipo' => 'INDEFINIDO', 'fecha_inicio' => '2024-01-01',
            'sueldo_base' => $sueldoBase, 'horas_semana' => 42,
            'estado' => 'VIGENTE', 'es_contrato_activo' => true,
        ]);

        return $empleado;
    }

    // ── calcular (POST /liquidaciones/calcular) ──────────────────────────────

    public function test_calcular_via_http_devuelve_201_y_persiste_en_borrador()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $empleado = $this->empleadoConContrato($empresa->id);

        $response = $this->actingAs($admin)->postJson('/api/rrhh/liquidaciones/calcular', [
            'empleado_id' => $empleado->id,
            'anio' => 2026,
            'mes' => 6,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.estado', Liquidacion::ESTADO_BORRADOR)
            ->assertJsonPath('data.empleado_id', $empleado->id);
        $this->assertDatabaseHas('liquidaciones', [
            'empresa_id' => $empresa->id, 'empleado_id' => $empleado->id, 'anio' => 2026, 'mes' => 6,
        ]);
    }

    public function test_calcular_valida_campos_requeridos_con_422()
    {
        [, $admin] = $this->crearEmpresaConAdmin();

        $response = $this->actingAs($admin)->postJson('/api/rrhh/liquidaciones/calcular', []);

        $response->assertStatus(422)->assertJsonValidationErrors(['empleado_id', 'anio', 'mes']);
    }

    public function test_calcular_de_empleado_de_otra_empresa_falla()
    {
        [$empresaA, $adminA] = $this->crearEmpresaConAdmin();
        [$empresaB] = $this->crearEmpresaConAdmin();
        $empleadoB = $this->empleadoConContrato($empresaB->id);

        $response = $this->actingAs($adminA)->postJson('/api/rrhh/liquidaciones/calcular', [
            'empleado_id' => $empleadoB->id,
            'anio' => 2026,
            'mes' => 6,
        ]);

        $this->assertNotEquals(201, $response->getStatusCode());
        $this->assertDatabaseMissing('liquidaciones', ['empresa_id' => $empresaA->id, 'empleado_id' => $empleadoB->id]);
    }

    public function test_calcular_sin_permiso_devuelve_403()
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado = $this->empleadoConContrato($empresa->id);
        $sinPermiso = $this->crearUsuario($empresa, $this->rolUsuarioBasico);

        $response = $this->actingAs($sinPermiso)->postJson('/api/rrhh/liquidaciones/calcular', [
            'empleado_id' => $empleado->id, 'anio' => 2026, 'mes' => 6,
        ]);

        $response->assertStatus(403);
    }

    // ── index / show ──────────────────────────────────────────────────────

    public function test_index_solo_lista_liquidaciones_de_la_empresa_activa()
    {
        [$empresaA, $adminA] = $this->crearEmpresaConAdmin();
        [$empresaB, $adminB] = $this->crearEmpresaConAdmin();
        $empleadoA = $this->empleadoConContrato($empresaA->id);
        $empleadoB = $this->empleadoConContrato($empresaB->id);

        $this->actingAs($adminA)->postJson('/api/rrhh/liquidaciones/calcular', ['empleado_id' => $empleadoA->id, 'anio' => 2026, 'mes' => 6])->assertStatus(201);
        $this->actingAs($adminB)->postJson('/api/rrhh/liquidaciones/calcular', ['empleado_id' => $empleadoB->id, 'anio' => 2026, 'mes' => 6])->assertStatus(201);

        $response = $this->actingAs($adminA)->getJson('/api/rrhh/liquidaciones');

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('empleado_id')->all();
        $this->assertContains($empleadoA->id, $ids);
        $this->assertNotContains($empleadoB->id, $ids);
    }

    public function test_show_de_liquidacion_de_otra_empresa_devuelve_404()
    {
        [$empresaA, $adminA] = $this->crearEmpresaConAdmin();
        [$empresaB, $adminB] = $this->crearEmpresaConAdmin();
        $empleadoB = $this->empleadoConContrato($empresaB->id);
        $liqB = $this->actingAs($adminB)->postJson('/api/rrhh/liquidaciones/calcular', [
            'empleado_id' => $empleadoB->id, 'anio' => 2026, 'mes' => 6,
        ])->json('data');

        $response = $this->actingAs($adminA)->getJson('/api/rrhh/liquidaciones/'.$liqB['id']);

        $response->assertStatus(404);
    }

    // ── emitir ────────────────────────────────────────────────────────────

    public function test_emitir_via_http_cambia_estado_a_emitida()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $empleado = $this->empleadoConContrato($empresa->id);
        $liq = $this->actingAs($admin)->postJson('/api/rrhh/liquidaciones/calcular', [
            'empleado_id' => $empleado->id, 'anio' => 2026, 'mes' => 6,
        ])->json('data');

        $response = $this->actingAs($admin)->postJson("/api/rrhh/liquidaciones/{$liq['id']}/emitir");

        $response->assertStatus(200)->assertJsonPath('data.estado', Liquidacion::ESTADO_EMITIDA);
    }

    public function test_emitir_dos_veces_falla_con_422()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $empleado = $this->empleadoConContrato($empresa->id);
        $liq = $this->actingAs($admin)->postJson('/api/rrhh/liquidaciones/calcular', [
            'empleado_id' => $empleado->id, 'anio' => 2026, 'mes' => 6,
        ])->json('data');
        $this->actingAs($admin)->postJson("/api/rrhh/liquidaciones/{$liq['id']}/emitir")->assertStatus(200);

        $response = $this->actingAs($admin)->postJson("/api/rrhh/liquidaciones/{$liq['id']}/emitir");

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_emitir_liquidacion_inexistente_devuelve_404()
    {
        [, $admin] = $this->crearEmpresaConAdmin();

        $response = $this->actingAs($admin)->postJson('/api/rrhh/liquidaciones/999999/emitir');

        $response->assertStatus(404);
    }

    // ── anular ────────────────────────────────────────────────────────────

    public function test_anular_via_http_cambia_estado_a_anulada()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $empleado = $this->empleadoConContrato($empresa->id);
        $liq = $this->actingAs($admin)->postJson('/api/rrhh/liquidaciones/calcular', [
            'empleado_id' => $empleado->id, 'anio' => 2026, 'mes' => 6,
        ])->json('data');

        $response = $this->actingAs($admin)->postJson("/api/rrhh/liquidaciones/{$liq['id']}/anular");

        $response->assertStatus(200)->assertJsonPath('data.estado', Liquidacion::ESTADO_ANULADA);
    }

    public function test_anular_liquidacion_pagada_falla_con_422()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $empleado = $this->empleadoConContrato($empresa->id);
        $liq = $this->actingAs($admin)->postJson('/api/rrhh/liquidaciones/calcular', [
            'empleado_id' => $empleado->id, 'anio' => 2026, 'mes' => 6,
        ])->json('data');
        Liquidacion::where('id', $liq['id'])->update(['estado' => Liquidacion::ESTADO_PAGADA]);

        $response = $this->actingAs($admin)->postJson("/api/rrhh/liquidaciones/{$liq['id']}/anular");

        $response->assertStatus(422)->assertJsonPath('success', false);
        $this->assertDatabaseHas('liquidaciones', ['id' => $liq['id'], 'estado' => Liquidacion::ESTADO_PAGADA]);
    }

    public function test_anular_de_otra_empresa_devuelve_404_y_no_modifica_estado()
    {
        [$empresaA, $adminA] = $this->crearEmpresaConAdmin();
        [$empresaB, $adminB] = $this->crearEmpresaConAdmin();
        $empleadoB = $this->empleadoConContrato($empresaB->id);
        $liqB = $this->actingAs($adminB)->postJson('/api/rrhh/liquidaciones/calcular', [
            'empleado_id' => $empleadoB->id, 'anio' => 2026, 'mes' => 6,
        ])->json('data');

        $response = $this->actingAs($adminA)->postJson("/api/rrhh/liquidaciones/{$liqB['id']}/anular");

        $response->assertStatus(404);
        $this->assertDatabaseHas('liquidaciones', ['id' => $liqB['id'], 'estado' => Liquidacion::ESTADO_BORRADOR]);
    }
}
