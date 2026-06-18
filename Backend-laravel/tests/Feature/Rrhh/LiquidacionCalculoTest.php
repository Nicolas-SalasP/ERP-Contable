<?php

namespace Tests\Feature\Rrhh;

use App\Domains\Rrhh\Exceptions\RrhhException;
use App\Domains\Rrhh\Models\ConceptoRemuneracion;
use App\Domains\Rrhh\Models\Contrato;
use App\Domains\Rrhh\Models\Empleado;
use App\Domains\Rrhh\Models\IndicadorMensual;
use App\Domains\Rrhh\Models\Liquidacion;
use App\Domains\Rrhh\Models\ParametroPrevisional;
use App\Domains\Rrhh\Models\TablaImpuestoUnico;
use App\Domains\Rrhh\Services\Calculo\LiquidacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * R3-Extra — Casos adicionales del motor de liquidación.
 * Complementa LiquidacionSueldoTest sin duplicar sus escenarios.
 */
class LiquidacionCalculoTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    private LiquidacionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->service = app(LiquidacionService::class);
        $this->cargarParametrosLegales();
    }

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

        $tramos = [
            [1, 0.0,   13.5,  0.000, 0.00],
            [2, 13.5,  30.0,  0.040, 0.54],
            [3, 30.0,  50.0,  0.080, 1.74],
            [4, 50.0,  70.0,  0.135, 4.49],
            [5, 70.0,  90.0,  0.230, 11.14],
            [6, 90.0,  120.0, 0.304, 17.80],
            [7, 120.0, 310.0, 0.350, 23.32],
            [8, 310.0, null,  0.400, 38.82],
        ];
        foreach ($tramos as [$o, $d, $h, $t, $f]) {
            TablaImpuestoUnico::create([
                'anio' => 2026, 'orden' => $o, 'desde_utm' => $d, 'hasta_utm' => $h,
                'tasa' => $t, 'factor_deduccion_utm' => $f,
            ]);
        }
    }

    private function empleadoConContrato(
        int $empresaId,
        float $sueldoBase,
        string $tipo = 'INDEFINIDO',
        string $afp = 'Habitat',
        string $fechaInicio = '2024-01-01',
        string $tipoSalud = 'FONASA',
        ?float $isaplePlanUf = null,
        ?string $isapreNombre = null,
    ): Empleado {
        $empleado = Empleado::create([
            'empresa_id' => $empresaId,
            'rut' => '12.345.678-' . rand(0, 9),
            'nombres' => 'Trabajador',
            'apellido_paterno' => 'Test',
            'afp' => $afp,
            'tipo_salud' => $tipoSalud,
            'isapre_plan_uf' => $isaplePlanUf,
            'isapre_nombre' => $isapreNombre,
        ]);

        Contrato::create([
            'empresa_id' => $empresaId,
            'empleado_id' => $empleado->id,
            'tipo' => $tipo,
            'fecha_inicio' => $fechaInicio,
            'sueldo_base' => $sueldoBase,
            'horas_semana' => 42,
            'estado' => 'VIGENTE',
            'es_contrato_activo' => true,
        ]);

        return $empleado->fresh();
    }

    // ── 1. Gratificación topada a 4.75 × IMM / 12 ────────────────────────────

    public function test_calcula_gratificacion_con_tope_imm(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        // Sueldo base alto: 25% × 4.000.000 = 1.000.000 > tope (4.75 × 539000 / 12 = 213.354)
        $empleado = $this->empleadoConContrato($empresa->id, 4000000);

        $liq = $this->service->calcular($empresa->id, $empleado->id, 2026, 6);

        $topeEsperado = (int) round((4.75 * 539000) / 12); // 213.354
        $gratificacion = $liq->detalles->firstWhere('codigo_concepto', ConceptoRemuneracion::GRATIFICACION);

        $this->assertNotNull($gratificacion);
        $this->assertEquals($topeEsperado, (int) $gratificacion->monto,
            'La gratificación debe estar topada a 4.75 × IMM / 12.');
        $this->assertLessThan(4000000 * 0.25, (float) $gratificacion->monto,
            'El monto calculado sin tope sería mayor; el tope debe aplicarse.');
    }

    // ── 2. AFC en contrato indefinido reciente ────────────────────────────────

    public function test_afc_aplica_en_contrato_indefinido_reciente(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        // Contrato indefinido iniciado en 2024: < 11 años → AFC 0.6% aplica
        $empleado = $this->empleadoConContrato($empresa->id, 1000000, 'INDEFINIDO', 'Habitat', '2024-01-01');

        $liq = $this->service->calcular($empresa->id, $empleado->id, 2026, 6);

        $afc = $liq->detalles->firstWhere('codigo_concepto', ConceptoRemuneracion::AFC_TRABAJADOR);
        $this->assertNotNull($afc, 'Contrato indefinido reciente debe tener descuento AFC trabajador.');
        $this->assertGreaterThan(0, (float) $afc->monto);

        // Verificar que el porcentaje aplicado sea 0.6%
        $imponible = (float) $liq->total_haberes_imponibles;
        $afcEsperado = (int) round($imponible * 0.006);
        $this->assertEquals($afcEsperado, (int) $afc->monto);
    }

    // ── 3. ISAPRE adicional no rebaja base tributable ─────────────────────────

    public function test_isapre_adicional_no_rebaja_base_tributable(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        // Plan ISAPRE de 3 UF: plan_pesos = 3 × 39850 = 119.550
        // salud_legal (7%) < plan → salud_adicional > 0
        $empleado = $this->empleadoConContrato(
            $empresa->id, 1000000, 'INDEFINIDO', 'Habitat', '2024-01-01',
            'ISAPRE', 3.0, 'Cruz Blanca'
        );

        $liq = $this->service->calcular($empresa->id, $empleado->id, 2026, 6);

        $saludAdicional = (float) $liq->salud_adicional;
        $saludLegal = (float) $liq->salud_legal;

        $this->assertGreaterThan(0, $saludAdicional,
            'Con plan ISAPRE mayor al 7%, debe haber salud_adicional.');

        // La base tributable solo descuenta el 7% legal (salud_legal), no el adicional
        $imponible = (float) $liq->total_haberes_imponibles;
        $afpCotizacion = (int) round($imponible * 0.10);
        $afpComision = (int) round($imponible * 0.0127);
        $afcMonto = (int) round($imponible * 0.006);
        $baseTributableEsperada = $imponible - $afpCotizacion - $afpComision - $saludLegal - $afcMonto;

        $this->assertEquals(
            (int) $baseTributableEsperada,
            (int) $liq->base_tributable,
            'La salud_adicional de ISAPRE no debe rebajar la base tributable (solo el 7% legal).'
        );
    }

    // ── 4. Sin ParametroPrevisional → RrhhException ──────────────────────────

    public function test_lanza_error_sin_parametros_previsionales(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado = $this->empleadoConContrato($empresa->id, 1000000);

        // Período 2025-01: no existe ParametroPrevisional ni IndicadorMensual
        $this->expectException(RrhhException::class);
        $this->expectExceptionMessage('parámetros previsionales');

        $this->service->calcular($empresa->id, $empleado->id, 2025, 1);
    }

    // ── 5. Sin IndicadorMensual → RrhhException ───────────────────────────────

    public function test_lanza_error_sin_indicadores_mensuales(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado = $this->empleadoConContrato($empresa->id, 1000000);

        // El ParametroPrevisional vigente cubre 2026-03, pero no hay IndicadorMensual para ese mes
        $this->expectException(RrhhException::class);
        $this->expectExceptionMessage('indicadores mensuales');

        $this->service->calcular($empresa->id, $empleado->id, 2026, 3);
    }

    // ── 6. No recalcular liquidación ya EMITIDA ───────────────────────────────

    public function test_no_recalcula_liquidacion_ya_emitida(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado = $this->empleadoConContrato($empresa->id, 1000000);

        $liq = $this->service->calcular($empresa->id, $empleado->id, 2026, 6);
        $liq->update(['estado' => Liquidacion::ESTADO_EMITIDA]);

        $this->expectException(RrhhException::class);
        $this->expectExceptionMessageMatches('/EMITIDA/');

        $this->service->calcular($empresa->id, $empleado->id, 2026, 6);
    }

    // ── 7. Horas extra incrementan total haberes ──────────────────────────────

    public function test_horas_extra_incrementan_total_haberes(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado = $this->empleadoConContrato($empresa->id, 1000000);

        $liqSinExtras = $this->service->calcular($empresa->id, $empleado->id, 2026, 6);
        $totalSin = (float) $liqSinExtras->total_haberes;

        // Forzar recálculo borrando la primera liquidación
        Liquidacion::where('empresa_id', $empresa->id)
            ->where('empleado_id', $empleado->id)
            ->forceDelete();

        $liqConExtras = $this->service->calcular(
            $empresa->id, $empleado->id, 2026, 6, ['horas_extra' => 10]
        );
        $totalCon = (float) $liqConExtras->total_haberes;

        $this->assertGreaterThan($totalSin, $totalCon,
            '10 horas extra deben incrementar el total de haberes respecto a sin horas extra.');

        $lineaHE = $liqConExtras->detalles->firstWhere('codigo_concepto', ConceptoRemuneracion::HORAS_EXTRA);
        $this->assertNotNull($lineaHE);
        $this->assertEqualsWithDelta($totalCon - $totalSin, (float) $lineaHE->monto, 1.0);
    }

    // ── 8. Liquidación contiene campos mínimos requeridos ─────────────────────

    public function test_liquidacion_arroja_campos_minimos_requeridos(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado = $this->empleadoConContrato($empresa->id, 1000000);

        $liq = $this->service->calcular($empresa->id, $empleado->id, 2026, 6);

        // Campos mínimos en la cabecera
        $this->assertNotNull($liq->total_haberes_imponibles);
        $this->assertNotNull($liq->total_haberes);
        $this->assertNotNull($liq->total_descuentos);
        $this->assertNotNull($liq->liquido_a_pagar);

        // Hay al menos un detalle con tipo HABER_IMPONIBLE (sueldo base)
        $this->assertNotEmpty($liq->detalles->where('tipo', 'HABER_IMPONIBLE'));

        // Hay al menos un detalle con tipo DESCUENTO_LEGAL (AFP)
        $this->assertNotEmpty($liq->detalles->where('tipo', 'DESCUENTO_LEGAL'));

        // Líquido es positivo para un sueldo estándar
        $this->assertGreaterThan(0, (float) $liq->liquido_a_pagar);

        // Relaciones cargadas
        $this->assertNotNull($liq->empleado);
        $this->assertNotNull($liq->contrato);
    }
}
