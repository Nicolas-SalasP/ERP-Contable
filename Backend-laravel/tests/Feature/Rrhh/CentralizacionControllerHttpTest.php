<?php

namespace Tests\Feature\Rrhh;

use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Rrhh\Models\Contrato;
use App\Domains\Rrhh\Models\Empleado;
use App\Domains\Rrhh\Models\IndicadorMensual;
use App\Domains\Rrhh\Models\ParametroPrevisional;
use App\Domains\Rrhh\Models\RrhhMapeoContable;
use App\Domains\Rrhh\Models\TablaImpuestoUnico;
use App\Domains\Rrhh\Services\Calculo\LiquidacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * CentralizacionContableTest ya cubre la logica de negocio de centralizar()
 * llamando al service DIRECTO, y su unico test HTTP (test_endpoint_requiere_permiso_procesar)
 * queda bloqueado en el middleware de permisos (403) antes de llegar al controller.
 * Este archivo cubre lo que faltaba: indexMapeo/upsertMapeo/destroyMapeo via HTTP,
 * y un centralizar() exitoso que si atraviesa el controller real.
 */
class CentralizacionControllerHttpTest extends TestCase
{
    use PreparaEntornoBase, RefreshDatabase;

    private LiquidacionService $liquidacionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->liquidacionService = app(LiquidacionService::class);
    }

    // ── Mapeo contable (HTTP) ────────────────────────────────────────────────

    public function test_index_mapeo_devuelve_tipos_requeridos_y_todos_los_tipos()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();

        $response = $this->actingAs($admin)->getJson('/api/rrhh/mapeo-contable');

        $response->assertStatus(200)
            ->assertJsonPath('data', [])
            ->assertJsonStructure(['success', 'data', 'tipos_requeridos', 'todos_los_tipos']);
        $this->assertContains(RrhhMapeoContable::GASTO_REMUNERACIONES, $response->json('tipos_requeridos'));
    }

    public function test_upsert_mapeo_crea_y_luego_actualiza_el_mismo_tipo()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();

        $crear = $this->actingAs($admin)->postJson('/api/rrhh/mapeo-contable', [
            'tipo_cuenta' => RrhhMapeoContable::GASTO_REMUNERACIONES,
            'cuenta_contable_codigo' => '4101',
        ]);
        $crear->assertStatus(200)->assertJsonPath('data.cuenta_contable_codigo', '4101');

        // Mismo tipo -> updateOrCreate no duplica, actualiza la fila existente.
        $actualizar = $this->actingAs($admin)->postJson('/api/rrhh/mapeo-contable', [
            'tipo_cuenta' => RrhhMapeoContable::GASTO_REMUNERACIONES,
            'cuenta_contable_codigo' => '4199',
        ]);
        $actualizar->assertStatus(200)->assertJsonPath('data.cuenta_contable_codigo', '4199');

        $this->assertSame(1, RrhhMapeoContable::where('empresa_id', $empresa->id)
            ->where('tipo_cuenta', RrhhMapeoContable::GASTO_REMUNERACIONES)->count());
    }

    public function test_upsert_mapeo_rechaza_tipo_cuenta_invalido_con_422()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();

        $response = $this->actingAs($admin)->postJson('/api/rrhh/mapeo-contable', [
            'tipo_cuenta' => 'TIPO_QUE_NO_EXISTE',
            'cuenta_contable_codigo' => '4101',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['tipo_cuenta']);
    }

    public function test_destroy_mapeo_elimina_el_tipo_configurado()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        RrhhMapeoContable::create([
            'empresa_id' => $empresa->id,
            'tipo_cuenta' => RrhhMapeoContable::GASTO_REMUNERACIONES,
            'cuenta_contable_codigo' => '4101',
        ]);

        $response = $this->actingAs($admin)->deleteJson('/api/rrhh/mapeo-contable/'.RrhhMapeoContable::GASTO_REMUNERACIONES);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('rrhh_mapeo_contable', [
            'empresa_id' => $empresa->id,
            'tipo_cuenta' => RrhhMapeoContable::GASTO_REMUNERACIONES,
        ]);
    }

    public function test_destroy_mapeo_de_tipo_no_configurado_devuelve_404()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();

        $response = $this->actingAs($admin)->deleteJson('/api/rrhh/mapeo-contable/'.RrhhMapeoContable::PASIVO_IMPUESTO_UNICO);

        $response->assertStatus(404);
    }

    // ── Centralizar (HTTP, exitoso, atraviesa el controller real) ───────────

    public function test_centralizar_via_http_genera_asiento_y_devuelve_201()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $this->cargarParametrosLegales();
        $cuentas = $this->crearCuentas($empresa->id);
        $this->configurarMapeoHttp($empresa->id, $cuentas, $admin);
        $this->crearYEmitirLiquidacion($empresa->id);

        $response = $this->actingAs($admin)->postJson('/api/rrhh/centralizacion/2026/6');

        $response->assertStatus(201)
            ->assertJsonStructure(['success', 'message', 'data'])
            ->assertJsonPath('success', true);
        $this->assertStringContainsString('centralizadas', $response->json('message'));
    }

    public function test_centralizar_via_http_rechaza_mes_invalido_con_422()
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();

        $response = $this->actingAs($admin)->postJson('/api/rrhh/centralizacion/2026/13');

        $response->assertStatus(422)->assertJsonPath('message', 'Período inválido.');
    }

    // ── Helpers (calcados de CentralizacionContableTest para consistencia) ──

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

    private function crearCuentas(int $empresaId): array
    {
        $definiciones = [
            RrhhMapeoContable::GASTO_REMUNERACIONES => ['4101', 'Gasto Remuneraciones', 'GASTO'],
            RrhhMapeoContable::GASTO_LEYES_SOCIALES => ['4102', 'Gasto Leyes Sociales', 'GASTO'],
            RrhhMapeoContable::PASIVO_LIQUIDO_PAGAR => ['2201', 'Remuneraciones por Pagar', 'PASIVO'],
            RrhhMapeoContable::PASIVO_RETENCIONES_PREVISIONALES => ['2202', 'Retenciones Previsionales', 'PASIVO'],
            RrhhMapeoContable::PASIVO_IMPUESTO_UNICO => ['2203', 'Impuesto Único por Pagar', 'PASIVO'],
            RrhhMapeoContable::PASIVO_LEYES_SOCIALES => ['2204', 'Leyes Sociales Empleador', 'PASIVO'],
        ];

        $cuentas = [];
        foreach ($definiciones as $tipo => [$codigo, $nombre, $tipoCuenta]) {
            $cuentas[$tipo] = PlanCuenta::create([
                'empresa_id' => $empresaId, 'codigo' => $codigo, 'nombre' => $nombre,
                'tipo' => $tipoCuenta, 'nivel' => 2, 'imputable' => true, 'activo' => true,
            ]);
        }

        return $cuentas;
    }

    /** Configura el mapeo via HTTP (no Eloquent directo) para que este archivo tambien ejercite upsertMapeo(). */
    private function configurarMapeoHttp(int $empresaId, array $cuentas, $admin): void
    {
        foreach ($cuentas as $tipo => $cuenta) {
            $this->actingAs($admin)->postJson('/api/rrhh/mapeo-contable', [
                'tipo_cuenta' => $tipo,
                'cuenta_contable_codigo' => $cuenta->codigo,
            ])->assertStatus(200);
        }
    }

    private function crearYEmitirLiquidacion(int $empresaId): void
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
            'sueldo_base' => 900000, 'horas_semana' => 42,
            'estado' => 'VIGENTE', 'es_contrato_activo' => true,
        ]);

        $liq = $this->liquidacionService->calcular($empresaId, $empleado->id, 2026, 6);
        $this->liquidacionService->emitir($empresaId, $liq->id);
    }
}
