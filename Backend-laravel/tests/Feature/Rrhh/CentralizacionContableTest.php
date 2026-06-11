<?php

namespace Tests\Feature\Rrhh;

use App\Domains\Contabilidad\Models\AsientoContable;
use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Rrhh\Exceptions\RrhhException;
use App\Domains\Rrhh\Models\Contrato;
use App\Domains\Rrhh\Models\Empleado;
use App\Domains\Rrhh\Models\IndicadorMensual;
use App\Domains\Rrhh\Models\Liquidacion;
use App\Domains\Rrhh\Models\ParametroPrevisional;
use App\Domains\Rrhh\Models\RrhhMapeoContable;
use App\Domains\Rrhh\Models\TablaImpuestoUnico;
use App\Domains\Rrhh\Services\Calculo\LiquidacionService;
use App\Domains\Rrhh\Services\Contabilidad\CentralizacionRemuneracionesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * R5 — Centralización contable de remuneraciones.
 * Valida partida doble, idempotencia, mapeo contable y aislamiento multitenant.
 */
class CentralizacionContableTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    private CentralizacionRemuneracionesService $service;
    private LiquidacionService $liquidacionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->service = app(CentralizacionRemuneracionesService::class);
        $this->liquidacionService = app(LiquidacionService::class);
        $this->cargarParametrosLegales();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function cargarParametrosLegales(): void
    {
        ParametroPrevisional::create([
            'vigente_desde' => '2026-01-01',
            'vigente_hasta' => null,
            'afp_cotizacion_pct' => 10.0,
            'afp_comisiones_json' => ['Habitat' => 1.27, 'Modelo' => 0.58, 'Capital' => 1.44],
            'afp_sis_pct' => 1.62,
            'tope_imponible_uf' => 90.0,
            'salud_fonasa_pct' => 7.0,
            'afc_indefinido_trabajador_pct' => 0.6,
            'afc_indefinido_empleador_pct' => 2.4,
            'afc_plazo_fijo_trabajador_pct' => 0.0,
            'afc_plazo_fijo_empleador_pct' => 3.0,
            'afc_tope_imponible_uf' => 135.2,
            'imm' => 539000,
            'gratificacion_tope_mensual_factor' => 4.75,
            'cotizacion_adicional_empleador_pct' => 1.0,
            'mutual_cotizacion_basica_pct' => 0.9,
            'asignacion_familiar_tramos_json' => [
                ['hasta_pesos' => 620251, 'monto_por_carga' => 22007],
                ['hasta_pesos' => null, 'monto_por_carga' => 0],
            ],
            'fuente' => 'test',
        ]);

        IndicadorMensual::create([
            'anio' => 2026, 'mes' => 6,
            'uf_valor' => 39850, 'utm_valor' => 71506, 'uta_valor' => 71506 * 12,
        ]);

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
        $cuentas = [];
        $definiciones = [
            RrhhMapeoContable::GASTO_REMUNERACIONES       => ['4101', 'Gasto Remuneraciones', 'GASTO'],
            RrhhMapeoContable::GASTO_LEYES_SOCIALES       => ['4102', 'Gasto Leyes Sociales', 'GASTO'],
            RrhhMapeoContable::PASIVO_LIQUIDO_PAGAR       => ['2201', 'Remuneraciones por Pagar', 'PASIVO'],
            RrhhMapeoContable::PASIVO_RETENCIONES_PREVISIONALES => ['2202', 'Retenciones Previsionales', 'PASIVO'],
            RrhhMapeoContable::PASIVO_IMPUESTO_UNICO      => ['2203', 'Impuesto Único por Pagar', 'PASIVO'],
            RrhhMapeoContable::PASIVO_LEYES_SOCIALES      => ['2204', 'Leyes Sociales Empleador', 'PASIVO'],
        ];

        foreach ($definiciones as $tipo => [$codigo, $nombre, $tipoCuenta]) {
            $cuentas[$tipo] = PlanCuenta::create([
                'empresa_id' => $empresaId,
                'codigo' => $codigo,
                'nombre' => $nombre,
                'tipo' => $tipoCuenta,
                'nivel' => 2,
                'imputable' => true,
                'activo' => true,
            ]);
        }

        return $cuentas;
    }

    private function configurarMapeo(int $empresaId, array $cuentas): void
    {
        foreach ($cuentas as $tipo => $cuenta) {
            RrhhMapeoContable::create([
                'empresa_id' => $empresaId,
                'tipo_cuenta' => $tipo,
                'cuenta_contable_codigo' => $cuenta->codigo,
            ]);
        }
    }

    private function crearYEmitirLiquidacion(int $empresaId, string $afp = 'Habitat'): Liquidacion
    {
        $rut = '12.' . rand(100, 999) . '.' . rand(100, 999) . '-' . rand(0, 9);
        $empleado = Empleado::create([
            'empresa_id' => $empresaId,
            'rut' => $rut,
            'nombres' => 'Trabajador Test',
            'apellido_paterno' => 'Apellido',
            'afp' => $afp,
            'tipo_salud' => 'FONASA',
        ]);

        Contrato::create([
            'empresa_id' => $empresaId,
            'empleado_id' => $empleado->id,
            'tipo' => 'INDEFINIDO',
            'fecha_inicio' => '2024-01-01',
            'sueldo_base' => 900000,
            'horas_semana' => 42,
            'estado' => 'VIGENTE',
            'es_contrato_activo' => true,
        ]);

        $liq = $this->liquidacionService->calcular($empresaId, $empleado->id, 2026, 6);
        return $this->liquidacionService->emitir($empresaId, $liq->id);
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_genera_asiento_con_partida_doble_correcta(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $cuentas = $this->crearCuentas($empresa->id);
        $this->configurarMapeo($empresa->id, $cuentas);
        $this->crearYEmitirLiquidacion($empresa->id);

        $asiento = $this->service->centralizar($empresa->id, 2026, 6, $admin->id);

        $this->assertNotNull($asiento);
        $this->assertEquals('rrhh', $asiento->origen_modulo);
        $this->assertEquals(202606, $asiento->origen_id);

        // Partida doble: DEBE == HABER
        $totalDebe  = (float) $asiento->detalles->sum('debe');
        $totalHaber = (float) $asiento->detalles->sum('haber');
        $this->assertEqualsWithDelta($totalDebe, $totalHaber, 0.01,
            "El asiento de centralización debe cuadrar (DEBE={$totalDebe} HABER={$totalHaber})."
        );

        // Al menos 4 líneas: 2 DEBE + 2 HABER mínimo
        $this->assertGreaterThanOrEqual(4, $asiento->detalles->count());

        // Línea de gasto remuneraciones existe
        $lineaGasto = $asiento->detalles->firstWhere('cuenta_contable', '4101');
        $this->assertNotNull($lineaGasto);
        $this->assertGreaterThan(0, (float) $lineaGasto->debe);

        // Líquido por pagar existe
        $lineaLiquido = $asiento->detalles->firstWhere('cuenta_contable', '2201');
        $this->assertNotNull($lineaLiquido);
        $this->assertGreaterThan(0, (float) $lineaLiquido->haber);
    }

    public function test_el_debe_iguala_haberes_mas_leyes_sociales(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $cuentas = $this->crearCuentas($empresa->id);
        $this->configurarMapeo($empresa->id, $cuentas);
        $liq = $this->crearYEmitirLiquidacion($empresa->id);

        $asiento = $this->service->centralizar($empresa->id, 2026, 6, $admin->id);

        // DEBE debe ser total_haberes + leyes_sociales_empleador
        $gastoRem   = (float) $asiento->detalles->firstWhere('cuenta_contable', '4101')?->debe ?? 0;
        $gastoLeyes = (float) $asiento->detalles->firstWhere('cuenta_contable', '4102')?->debe ?? 0;
        $expectedDebe = (float) $liq->total_haberes + (float) ($liq->aporte_empleador_afc + $liq->aporte_empleador_sis + $liq->aporte_empleador_mutual);

        $this->assertEqualsWithDelta($expectedDebe, $gastoRem + $gastoLeyes, 1.00);
    }

    public function test_previene_doble_centralizacion(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $cuentas = $this->crearCuentas($empresa->id);
        $this->configurarMapeo($empresa->id, $cuentas);
        $this->crearYEmitirLiquidacion($empresa->id);

        $this->service->centralizar($empresa->id, 2026, 6, $admin->id);

        $this->expectException(RrhhException::class);
        $this->service->centralizar($empresa->id, 2026, 6, $admin->id);
    }

    public function test_falla_con_mapeo_incompleto(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $this->crearYEmitirLiquidacion($empresa->id);

        // Mapeo parcial: solo 1 cuenta configurada
        $cuentas = $this->crearCuentas($empresa->id);
        RrhhMapeoContable::create([
            'empresa_id' => $empresa->id,
            'tipo_cuenta' => RrhhMapeoContable::GASTO_REMUNERACIONES,
            'cuenta_contable_codigo' => '4101',
        ]);

        $this->expectException(RrhhException::class);
        $this->service->centralizar($empresa->id, 2026, 6, $admin->id);
    }

    public function test_falla_si_no_hay_liquidaciones_emitidas(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $cuentas = $this->crearCuentas($empresa->id);
        $this->configurarMapeo($empresa->id, $cuentas);

        $this->expectException(RrhhException::class);
        $this->service->centralizar($empresa->id, 2026, 6, $admin->id);
    }

    public function test_actualiza_comprobante_en_todas_las_liquidaciones(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $cuentas = $this->crearCuentas($empresa->id);
        $this->configurarMapeo($empresa->id, $cuentas);

        // Dos liquidaciones del mismo período
        $this->crearYEmitirLiquidacion($empresa->id);
        $this->crearYEmitirLiquidacion($empresa->id);

        $asiento = $this->service->centralizar($empresa->id, 2026, 6, $admin->id);

        $liqActualizadas = Liquidacion::where('empresa_id', $empresa->id)
            ->where('anio', 2026)
            ->where('mes', 6)
            ->where('estado', 'EMITIDA')
            ->whereNotNull('comprobante_contable')
            ->count();

        $this->assertEquals(2, $liqActualizadas);

        // Todos apuntan al mismo comprobante
        $comprobantes = Liquidacion::where('empresa_id', $empresa->id)
            ->where('anio', 2026)->where('mes', 6)
            ->pluck('comprobante_contable')
            ->unique()
            ->filter();
        $this->assertCount(1, $comprobantes);
        $this->assertEquals($asiento->numero_comprobante, $comprobantes->first());
    }

    public function test_liquidaciones_borrador_no_son_centralizadas(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $cuentas = $this->crearCuentas($empresa->id);
        $this->configurarMapeo($empresa->id, $cuentas);

        // Una emitida + una en BORRADOR
        $liqEmitida = $this->crearYEmitirLiquidacion($empresa->id);

        $rut2 = '11.' . rand(100, 999) . '.' . rand(100, 999) . '-2';
        $emp2 = Empleado::create([
            'empresa_id' => $empresa->id, 'rut' => $rut2,
            'nombres' => 'Trabajador2', 'apellido_paterno' => 'B',
            'afp' => 'Habitat', 'tipo_salud' => 'FONASA',
        ]);
        Contrato::create([
            'empresa_id' => $empresa->id, 'empleado_id' => $emp2->id,
            'tipo' => 'INDEFINIDO', 'fecha_inicio' => '2025-01-01',
            'sueldo_base' => 700000, 'horas_semana' => 42,
            'estado' => 'VIGENTE', 'es_contrato_activo' => true,
        ]);
        // Quedará en BORRADOR (no emitida)
        $this->liquidacionService->calcular($empresa->id, $emp2->id, 2026, 6);

        $asiento = $this->service->centralizar($empresa->id, 2026, 6, $admin->id);

        // Solo la liquidación emitida fue centralizada
        $centralizadas = Liquidacion::where('empresa_id', $empresa->id)
            ->whereNotNull('comprobante_contable')
            ->count();
        $this->assertEquals(1, $centralizadas);
    }

    public function test_aislamiento_multitenant(): void
    {
        [$empresaA, $adminA] = $this->crearEmpresaConAdmin();
        [$empresaB] = $this->crearEmpresaConAdmin();

        // Solo empresa A tiene parametros y liquidaciones
        $cuentasA = $this->crearCuentas($empresaA->id);
        $this->configurarMapeo($empresaA->id, $cuentasA);
        $this->crearYEmitirLiquidacion($empresaA->id);

        $asientoA = $this->service->centralizar($empresaA->id, 2026, 6, $adminA->id);

        // Empresa B no puede centralizar (no tiene liquidaciones)
        $this->expectException(RrhhException::class);
        $this->service->centralizar($empresaB->id, 2026, 6, $adminA->id);
    }

    public function test_endpoint_requiere_permiso_procesar(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();

        $rolSinPermiso = \App\Domains\Core\Models\Rol::create([
            'nombre' => 'Sin Permiso', 'jerarquia' => 10, 'permisos' => [],
        ]);
        $usuarioSin = $this->crearUsuario($empresa, $rolSinPermiso);

        $response = $this->actingAs($usuarioSin)->postJson("/api/rrhh/centralizacion/2026/6");
        $response->assertStatus(403);
    }
}
