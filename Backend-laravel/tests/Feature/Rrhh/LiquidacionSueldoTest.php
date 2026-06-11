<?php

namespace Tests\Feature\Rrhh;

use App\Domains\Contabilidad\Models\AsientoContable;
use App\Domains\Rrhh\Exceptions\RrhhException;
use App\Domains\Rrhh\Models\ConceptoRemuneracion;
use App\Domains\Rrhh\Models\Contrato;
use App\Domains\Rrhh\Models\Empleado;
use App\Domains\Rrhh\Models\HaberDescuentoContrato;
use App\Domains\Rrhh\Models\IndicadorMensual;
use App\Domains\Rrhh\Models\Liquidacion;
use App\Domains\Rrhh\Models\ParametroPrevisional;
use App\Domains\Rrhh\Models\TablaImpuestoUnico;
use App\Domains\Rrhh\Services\Calculo\LiquidacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * R3 — Motor de liquidación. Valida los cálculos previsionales chilenos:
 * AFP 10% + comisión, salud 7%, AFC 0,6%, impuesto único por tabla, tope imponible.
 */
class LiquidacionSueldoTest extends TestCase
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

        // Fix 2: rebajas correctas tramos 7 y 8
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
    ): Empleado {
        $empleado = Empleado::create([
            'empresa_id' => $empresaId,
            'rut' => '12.345.678-' . rand(0, 9),
            'nombres' => 'Trabajador',
            'apellido_paterno' => 'Test',
            'afp' => $afp,
            'tipo_salud' => 'FONASA',
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

    public function test_calcula_descuentos_previsionales_basicos(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado = $this->empleadoConContrato($empresa->id, 1000000, 'INDEFINIDO', 'Habitat');

        $liq = $this->service->calcular($empresa->id, $empleado->id, 2026, 6);

        // Gratificación = min(25% × 1.000.000, 4.75 × 539000 / 12) = min(250000, 213354) = 213354
        $gratificacion = $liq->detalles->firstWhere('codigo_concepto', ConceptoRemuneracion::GRATIFICACION);
        $this->assertEquals(213354, (float) $gratificacion->monto);

        $imponible = 1000000 + 213354; // 1.213.354
        $this->assertEquals($imponible, (float) $liq->total_haberes_imponibles);

        // AFP 10% sobre imponible (bajo tope 90 UF = 3.586.500)
        $afp = $liq->detalles->firstWhere('codigo_concepto', ConceptoRemuneracion::AFP_COTIZACION);
        $this->assertEquals(round($imponible * 0.10), (float) $afp->monto);

        // AFP comisión Habitat 1.27%
        $comision = $liq->detalles->firstWhere('codigo_concepto', ConceptoRemuneracion::AFP_COMISION);
        $this->assertEquals(round($imponible * 0.0127), (float) $comision->monto);

        // Salud 7%
        $salud = $liq->detalles->firstWhere('codigo_concepto', ConceptoRemuneracion::SALUD);
        $this->assertEquals(round($imponible * 0.07), (float) $salud->monto);

        // AFC trabajador indefinido 0.6% (contrato inicio 2024, < 11 años al 2026)
        $afc = $liq->detalles->firstWhere('codigo_concepto', ConceptoRemuneracion::AFC_TRABAJADOR);
        $this->assertEquals(round($imponible * 0.006), (float) $afc->monto);
    }

    public function test_aplica_tope_imponible_afp_90_uf(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado = $this->empleadoConContrato($empresa->id, 5000000, 'INDEFINIDO', 'Modelo');

        $liq = $this->service->calcular($empresa->id, $empleado->id, 2026, 6);

        $topeEsperado = round(90 * 39850); // 3.586.500
        $this->assertEquals($topeEsperado, (float) $liq->base_imponible);

        $afp = $liq->detalles->firstWhere('codigo_concepto', ConceptoRemuneracion::AFP_COTIZACION);
        $this->assertEquals(round($topeEsperado * 0.10), (float) $afp->monto);
    }

    public function test_contrato_plazo_fijo_no_descuenta_afc_trabajador(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado = $this->empleadoConContrato($empresa->id, 900000, 'PLAZO_FIJO', 'Modelo');

        $liq = $this->service->calcular($empresa->id, $empleado->id, 2026, 6);

        $afc = $liq->detalles->firstWhere('codigo_concepto', ConceptoRemuneracion::AFC_TRABAJADOR);
        $this->assertNull($afc);

        $this->assertGreaterThan(0, (float) $liq->aporte_empleador_afc);
    }

    public function test_calcula_impuesto_unico_para_sueldo_alto(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado = $this->empleadoConContrato($empresa->id, 3000000, 'INDEFINIDO', 'Modelo');

        $liq = $this->service->calcular($empresa->id, $empleado->id, 2026, 6);

        $impuesto = $liq->detalles->firstWhere('codigo_concepto', ConceptoRemuneracion::IMPUESTO_UNICO);
        $this->assertNotNull($impuesto);
        $this->assertGreaterThan(0, (float) $impuesto->monto);
    }

    public function test_sueldo_minimo_no_paga_impuesto_unico(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado = $this->empleadoConContrato($empresa->id, 539000, 'INDEFINIDO', 'Modelo');

        $liq = $this->service->calcular($empresa->id, $empleado->id, 2026, 6);

        $impuesto = $liq->detalles->firstWhere('codigo_concepto', ConceptoRemuneracion::IMPUESTO_UNICO);
        $this->assertNull($impuesto);
    }

    public function test_liquido_a_pagar_es_haberes_menos_descuentos(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado = $this->empleadoConContrato($empresa->id, 1000000, 'INDEFINIDO', 'Habitat');

        $liq = $this->service->calcular($empresa->id, $empleado->id, 2026, 6);

        $esperado = (float) $liq->total_haberes - (float) $liq->total_descuentos;
        $this->assertEquals($esperado, (float) $liq->liquido_a_pagar);
        $this->assertGreaterThan(0, (float) $liq->liquido_a_pagar);
    }

    public function test_recalcular_reemplaza_borrador_anterior(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado = $this->empleadoConContrato($empresa->id, 1000000);

        $this->service->calcular($empresa->id, $empleado->id, 2026, 6);
        $this->service->calcular($empresa->id, $empleado->id, 2026, 6);

        $count = Liquidacion::where('empresa_id', $empresa->id)
            ->where('empleado_id', $empleado->id)
            ->where('anio', 2026)->where('mes', 6)
            ->count();
        $this->assertEquals(1, $count);
    }

    public function test_falla_sin_parametros_del_periodo(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado = $this->empleadoConContrato($empresa->id, 1000000);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('indicadores mensuales');

        $this->service->calcular($empresa->id, $empleado->id, 2027, 3);
    }

    // ── Fix 1: haberes del contrato ────────────────────────────────────────────

    public function test_descuento_legal_del_contrato_no_se_paga_como_haber(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado = $this->empleadoConContrato($empresa->id, 1000000);
        $contrato = $empleado->contratoActivo->first();

        HaberDescuentoContrato::create([
            'empresa_id' => $empresa->id,
            'contrato_id' => $contrato->id,
            'nombre' => 'Descuento Legal Test',
            'tipo' => 'DESCUENTO_LEGAL',
            'modalidad_valor' => 'MONTO_FIJO',
            'monto' => 50000,
            'activo' => true,
            'vigente_desde' => '2026-01-01',
        ]);

        $liq = $this->service->calcular($empresa->id, $empleado->id, 2026, 6);

        // El DESCUENTO_LEGAL del contrato NO debe sumarse a haberes imponibles
        $imponibleSinDescuento = 1000000 + 213354; // sueldo + gratificación únicamente
        $this->assertEquals($imponibleSinDescuento, (float) $liq->total_haberes_imponibles);
    }

    public function test_descuento_voluntario_del_contrato_no_se_duplica(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado = $this->empleadoConContrato($empresa->id, 1000000);
        $contrato = $empleado->contratoActivo->first();

        HaberDescuentoContrato::create([
            'empresa_id' => $empresa->id,
            'contrato_id' => $contrato->id,
            'nombre' => 'Descuento Voluntario Test',
            'tipo' => 'DESCUENTO_VOLUNTARIO',
            'modalidad_valor' => 'MONTO_FIJO',
            'monto' => 30000,
            'activo' => true,
            'vigente_desde' => '2026-01-01',
        ]);

        $liq = $this->service->calcular($empresa->id, $empleado->id, 2026, 6);

        // Aparece exactamente una vez en descuentos voluntarios
        $lineasDesc = $liq->detalles->where('tipo', 'DESCUENTO_VOLUNTARIO')
            ->where('nombre_concepto', 'Descuento Voluntario Test');
        $this->assertCount(1, $lineasDesc);
        $this->assertEquals(30000, (float) $lineasDesc->first()->monto);

        // Y el imponible no contiene ese monto
        $this->assertEquals(1000000 + 213354, (float) $liq->total_haberes_imponibles);
    }

    // ── Fix 2: continuidad de tramos IUSC ─────────────────────────────────────

    public function test_iusc_tramos_son_continuos(): void
    {
        // Para cada límite entre tramos consecutivos, el impuesto calculado con ambos tramos
        // debe coincidir (tolerancia 0.01 UTM).
        $utm = 71506.0;
        $tramos = TablaImpuestoUnico::paraAnio(2026)->values();

        for ($i = 0; $i < $tramos->count() - 1; $i++) {
            $tramoActual = $tramos[$i];
            $tramoSiguiente = $tramos[$i + 1];

            if ($tramoActual->hasta_utm === null) {
                continue;
            }

            $limiteUtm = (float) $tramoActual->hasta_utm;
            $limitePesos = $limiteUtm * $utm;

            // Impuesto desde tramo i (en el extremo superior)
            $impuestoTramoActual = ($limitePesos * (float) $tramoActual->tasa)
                - ((float) $tramoActual->factor_deduccion_utm * $utm);

            // Impuesto desde tramo i+1 (en el extremo inferior)
            $impuestoTramoSiguiente = ($limitePesos * (float) $tramoSiguiente->tasa)
                - ((float) $tramoSiguiente->factor_deduccion_utm * $utm);

            $diferenciaUtm = abs($impuestoTramoActual - $impuestoTramoSiguiente) / $utm;

            $this->assertLessThanOrEqual(
                0.01,
                $diferenciaUtm,
                "Discontinuidad en tramo {$tramoActual->orden}→{$tramoSiguiente->orden} en límite {$limiteUtm} UTM: diferencia {$diferenciaUtm} UTM"
            );
        }
    }

    // ── Fix 3: Isapre — separación salud_legal y salud_adicional ──────────────

    public function test_isapre_plan_mayor_7pct_separa_salud_legal_y_adicional(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $empleado = Empleado::create([
            'empresa_id' => $empresa->id,
            'rut' => '11.111.111-1',
            'nombres' => 'Isabel',
            'apellido_paterno' => 'Isapre',
            'afp' => 'Habitat',
            'tipo_salud' => 'ISAPRE',
            'isapre_nombre' => 'Cruz Blanca',
            'isapre_plan_uf' => 3.0, // plan = 3 UF × 39850 = 119.550 pesos
        ]);
        Contrato::create([
            'empresa_id' => $empresa->id,
            'empleado_id' => $empleado->id,
            'tipo' => 'INDEFINIDO',
            'fecha_inicio' => '2024-01-01',
            'sueldo_base' => 1000000,
            'horas_semana' => 42,
            'estado' => 'VIGENTE',
            'es_contrato_activo' => true,
        ]);
        $empleado = $empleado->fresh();

        $liq = $this->service->calcular($empresa->id, $empleado->id, 2026, 6);

        $imponible = 1000000 + 213354; // 1.213.354
        $saludLegalEsperado = round($imponible * 0.07);   // 84.935
        $planPesos = round(3.0 * 39850);                   // 119.550
        $saludAdicionalEsperado = max(0, $planPesos - $saludLegalEsperado); // 34.615

        // salud_legal + salud_adicional = monto total descontado
        $this->assertEquals($saludLegalEsperado, (float) $liq->salud_legal);
        $this->assertEquals($saludAdicionalEsperado, (float) $liq->salud_adicional);
        $this->assertEquals($saludLegalEsperado + $saludAdicionalEsperado, (float) $liq->detalles->firstWhere('codigo_concepto', ConceptoRemuneracion::SALUD)->monto);

        // Solo salud_legal rebaja base tributable
        $afpTotal = round($imponible * 0.10) + round($imponible * 0.0127);
        $afcMonto = round($imponible * 0.006);
        $baseTributableEsperada = $imponible - $afpTotal - $saludLegalEsperado - $afcMonto;
        $this->assertEquals($baseTributableEsperada, (float) $liq->base_tributable);
    }

    // ── Fix 4: hora extra fórmula DT ──────────────────────────────────────────

    public function test_hora_extra_usa_formula_dt(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado = $this->empleadoConContrato($empresa->id, 1000000, 'INDEFINIDO', 'Habitat');

        // valorHora = (1.000.000 / 30) * 7 / 42 = 5555.56; horaExtra = 5555.56 * 1.5 = 8333.33 → 8333
        $liq = $this->service->calcular($empresa->id, $empleado->id, 2026, 6, ['horas_extra' => 1]);

        $lineaHE = $liq->detalles->firstWhere('codigo_concepto', ConceptoRemuneracion::HORAS_EXTRA);
        $this->assertNotNull($lineaHE);

        // Con jornada 42 h: (1.000.000/30)*7/42*1.5 = 8333.33 → round = 8333
        $esperado = (int) round((1000000 / 30) * 7 / 42 * 1.5);
        $this->assertEquals($esperado, (float) $lineaHE->monto);
    }

    // ── Fix 5: aporte empleador Ley 21.735 ────────────────────────────────────

    public function test_aporte_empleador_reforma_no_descuenta_liquido(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado = $this->empleadoConContrato($empresa->id, 1000000, 'INDEFINIDO', 'Habitat');

        $liq = $this->service->calcular($empresa->id, $empleado->id, 2026, 6);

        $topeAfp = round(90 * 39850); // 3.586.500; imponible real 1.213.354 < tope
        $imponible = 1000000 + 213354;
        $reformaEsperado = round($imponible * 0.01);

        $this->assertEquals($reformaEsperado, (float) $liq->aporte_empleador_reforma);

        // No debe afectar el líquido (es cargo patronal)
        $liquidoCalculado = (float) $liq->total_haberes - (float) $liq->total_descuentos;
        $this->assertEquals($liquidoCalculado, (float) $liq->liquido_a_pagar);
    }

    // ── Fix 6: AFC 11 años ─────────────────────────────────────────────────────

    public function test_afc_trabajador_cesa_al_cumplir_11_anios(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        // Contrato iniciado hace exactamente 11 años al período 2026-06
        $empleado = $this->empleadoConContrato($empresa->id, 1000000, 'INDEFINIDO', 'Habitat', '2015-06-01');

        $liq = $this->service->calcular($empresa->id, $empleado->id, 2026, 6);

        // >= 11 años → AFC trabajador = 0, línea no aparece
        $afc = $liq->detalles->firstWhere('codigo_concepto', ConceptoRemuneracion::AFC_TRABAJADOR);
        $this->assertNull($afc);

        // El empleador sigue aportando
        $this->assertGreaterThan(0, (float) $liq->aporte_empleador_afc);
    }

    public function test_afc_trabajador_aplica_antes_de_11_anios(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado = $this->empleadoConContrato($empresa->id, 1000000, 'INDEFINIDO', 'Habitat', '2016-01-01');

        $liq = $this->service->calcular($empresa->id, $empleado->id, 2026, 6);

        // < 11 años → AFC trabajador aplicado
        $afc = $liq->detalles->firstWhere('codigo_concepto', ConceptoRemuneracion::AFC_TRABAJADOR);
        $this->assertNotNull($afc);
        $this->assertGreaterThan(0, (float) $afc->monto);
    }

    // ── Fix 7: TablaImpuestoUnico — tabla vacía lanza excepción ───────────────

    public function test_tabla_impuesto_vacia_lanza_excepcion(): void
    {
        // Calcular para un año sin tabla cargada
        $this->expectException(RrhhException::class);
        $this->expectExceptionMessage('No existe tabla de impuesto único para el año 2099');

        TablaImpuestoUnico::calcularImpuesto(2099, 5000000, 71506);
    }

    // ── Fix 8: ParametroPrevisional — AFP sin comisión lanza excepción ─────────

    public function test_afp_sin_comision_configurada_lanza_excepcion(): void
    {
        $parametro = ParametroPrevisional::vigentePara('2026-06-01');

        $this->expectException(RrhhException::class);
        $this->expectExceptionMessage('AFP "PlanVitalDesconocida" sin comisión configurada');

        $parametro->getComisionAfp('PlanVitalDesconocida');
    }

    // ── Fix 9: liquidación no-BORRADOR bloquea recálculo ──────────────────────

    public function test_no_puede_recalcular_liquidacion_emitida(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado = $this->empleadoConContrato($empresa->id, 1000000);

        $liq = $this->service->calcular($empresa->id, $empleado->id, 2026, 6);
        $liq->update(['estado' => Liquidacion::ESTADO_EMITIDA]);

        $this->expectException(RrhhException::class);
        $this->expectExceptionMessage('EMITIDA');

        $this->service->calcular($empresa->id, $empleado->id, 2026, 6);
    }

    public function test_no_puede_recalcular_liquidacion_pagada(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado = $this->empleadoConContrato($empresa->id, 1000000);

        $liq = $this->service->calcular($empresa->id, $empleado->id, 2026, 6);
        $liq->update(['estado' => Liquidacion::ESTADO_PAGADA]);

        $this->expectException(RrhhException::class);
        $this->expectExceptionMessage('PAGADA');

        $this->service->calcular($empresa->id, $empleado->id, 2026, 6);
    }

    // ── Fix 11: anular bloquea si período centralizado ────────────────────────

    public function test_anular_lanza_excepcion_si_periodo_ya_centralizado(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado  = $this->empleadoConContrato($empresa->id, 1000000);

        $liq = $this->service->calcular($empresa->id, $empleado->id, 2026, 6);
        $liq->update(['estado' => Liquidacion::ESTADO_EMITIDA]);

        // Simular que el período fue centralizado (mismo criterio de idempotencia).
        $periodoId = 2026 * 100 + 6;
        AsientoContable::create([
            'empresa_id'        => $empresa->id,
            'numero_comprobante' => 'RRHH-202606-001',
            'fecha'             => '2026-06-30',
            'glosa'             => 'Centralización remuneraciones 06/2026',
            'tipo_asiento'      => 'traspaso',
            'origen_modulo'     => 'rrhh',
            'origen_id'         => $periodoId,
            'usuario_id'        => 1,
            'estado'            => 'MAYORIZADO',
        ]);

        $this->expectException(RrhhException::class);
        $this->expectExceptionMessage('ya fue centralizado en contabilidad');

        $this->service->anular($empresa->id, $liq->id);
    }

    public function test_anular_sin_centralizacion_funciona_correctamente(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado  = $this->empleadoConContrato($empresa->id, 1000000);

        $liq = $this->service->calcular($empresa->id, $empleado->id, 2026, 6);
        $liq->update(['estado' => Liquidacion::ESTADO_EMITIDA]);

        // Sin asiento de centralización: anular debe proceder sin error.
        $anulada = $this->service->anular($empresa->id, $liq->id);

        $this->assertEquals(Liquidacion::ESTADO_ANULADA, $anulada->estado);
    }

    // ── Fix 10: asignación familiar usa imponible total ───────────────────────

    public function test_asignacion_familiar_usa_total_imponible(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empleado = $this->empleadoConContrato($empresa->id, 539000, 'INDEFINIDO', 'Habitat');

        // Agregar una carga familiar
        $empleado->cargasFamiliares()->create([
            'empresa_id' => $empresa->id,
            'empleado_id' => $empleado->id,
            'nombre' => 'Hijo Test',
            'tipo' => 'HIJO',
            'activa' => true,
            'vigente_desde' => '2026-01-01',
        ]);

        $liq = $this->service->calcular($empresa->id, $empleado->id, 2026, 6);

        // sueldo_base (539.000) < tope tramo (620.251) < imponible total con gratificación (673.750):
        // si la base fuera el sueldo base recibiría 22.007; al usar el imponible total queda fuera de tramo.
        $asig = $liq->detalles->firstWhere('codigo_concepto', ConceptoRemuneracion::ASIGNACION_FAMILIAR);
        $this->assertTrue($asig === null || (float) $asig->monto === 0.0);
    }
}
