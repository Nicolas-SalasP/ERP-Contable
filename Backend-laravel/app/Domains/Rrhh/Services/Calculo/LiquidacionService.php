<?php

namespace App\Domains\Rrhh\Services\Calculo;

use App\Domains\Rrhh\Models\ConceptoRemuneracion;
use App\Domains\Rrhh\Models\Empleado;
use App\Domains\Rrhh\Models\IndicadorMensual;
use App\Domains\Rrhh\Models\Liquidacion;
use App\Domains\Rrhh\Models\LiquidacionDetalle;
use App\Domains\Rrhh\Models\ParametroPrevisional;
use App\Domains\Rrhh\Models\TablaImpuestoUnico;
use App\Domains\Contabilidad\Models\AsientoContable;
use App\Domains\Rrhh\Exceptions\RrhhException;
use Illuminate\Support\Facades\DB;

/**
 * Motor de liquidación de remuneraciones según legislación laboral chilena.
 *
 * Componentes legales (Código del Trabajo + DL 3500/3501 + Ley 19.728 + LIR Art. 42-43):
 *
 *   Haberes imponibles: sueldo_base, gratificación Art.50, horas_extra, bonos_imponibles
 *   Haberes no imponibles: colación, movilización, asignación_familiar
 *   Descuentos legales: AFP (10% + comisión), salud (7%), AFC (0,6% indef.), impuesto_único
 *   Aportes empleador: SIS (1,62%), AFC empleador, cotización adicional Ley 21.735
 *
 * Los valores NUNCA son hardcodeados: se leen de ParametroPrevisional e IndicadorMensual.
 */
class LiquidacionService
{
    // Orden de presentación en el documento de liquidación
    private const ORDEN_HABERES_IMPONIBLES = 100;
    private const ORDEN_HABERES_NO_IMPONIBLES = 200;
    private const ORDEN_DESCUENTOS_LEGALES = 300;
    private const ORDEN_DESCUENTOS_VOLUNTARIOS = 400;

    public function calcular(int $empresaId, int $empleadoId, int $anio, int $mes, array $extras = []): Liquidacion
    {
        return DB::transaction(function () use ($empresaId, $empleadoId, $anio, $mes, $extras) {
            $empleado = Empleado::where('empresa_id', $empresaId)->with('contratoActivo')->find($empleadoId);
            if (!$empleado) {
                throw RrhhException::noEncontrado('El empleado no existe o no pertenece a la empresa.');
            }

            $contratos = $empleado->contratoActivo;
            $contrato = $contratos->first();
            if (!$contrato) {
                throw RrhhException::regla("El empleado {$empleado->nombre_completo} no tiene un contrato activo.");
            }

            $fechaPeriodo = sprintf('%04d-%02d-01', $anio, $mes);

            $parametro = ParametroPrevisional::vigentePara($fechaPeriodo);
            if (!$parametro) {
                throw RrhhException::regla("No hay parámetros previsionales cargados para el período {$mes}/{$anio}. Carga los indicadores primero.");
            }

            $indicador = IndicadorMensual::paraPeriodo($anio, $mes);
            if (!$indicador) {
                throw RrhhException::regla("No hay indicadores mensuales (UF/UTM) cargados para {$mes}/{$anio}.");
            }

            // Fix 9: rechazar si ya existe una liquidación no-BORRADOR para el período
            $liqExistente = Liquidacion::where('empresa_id', $empresaId)
                ->where('empleado_id', $empleadoId)
                ->where('anio', $anio)
                ->where('mes', $mes)
                ->whereIn('estado', [Liquidacion::ESTADO_EMITIDA, Liquidacion::ESTADO_PAGADA, Liquidacion::ESTADO_ANULADA])
                ->first();
            if ($liqExistente) {
                throw RrhhException::regla(
                    "Ya existe una liquidación en estado {$liqExistente->estado} para {$empleado->nombre_completo} en el período {$mes}/{$anio}. No se puede recalcular."
                );
            }

            Liquidacion::where('empresa_id', $empresaId)
                ->where('empleado_id', $empleadoId)
                ->where('anio', $anio)
                ->where('mes', $mes)
                ->where('estado', Liquidacion::ESTADO_BORRADOR)
                ->forceDelete();

            $liq = Liquidacion::create([
                'empresa_id' => $empresaId,
                'empleado_id' => $empleadoId,
                'contrato_id' => $contrato->id,
                'anio' => $anio,
                'mes' => $mes,
                'parametro_previsional_id' => $parametro->id,
                'indicador_mensual_id' => $indicador->id,
                'estado' => Liquidacion::ESTADO_BORRADOR,
            ]);

            $uf = (float) $indicador->uf_valor;
            $utm = (float) $indicador->utm_valor;
            $sueldoBase = (float) $contrato->sueldo_base;
            $imm = (float) $parametro->imm;

            $detalles = [];
            $orden = self::ORDEN_HABERES_IMPONIBLES;

            // ── 1. SUELDO BASE ─────────────────────────────────────────────
            $detalles[] = $this->lineaHaber($liq, ConceptoRemuneracion::SUELDO_BASE, 'Sueldo Base',
                'HABER_IMPONIBLE', $sueldoBase, $orden++);

            // ── 2. GRATIFICACIÓN LEGAL ART. 50 ─────────────────────────────
            // 25% del sueldo base (+ variable) con tope (4.75 × IMM / 12)
            $gratuBase = $sueldoBase + ($extras['remuneraciones_variables'] ?? 0);
            $gratuTope = round(($parametro->gratificacion_tope_mensual_factor * $imm) / 12);
            $gratuMonto = min(round($gratuBase * 0.25), $gratuTope);
            if ($gratuMonto > 0) {
                $detalles[] = $this->lineaHaberConTasa($liq, ConceptoRemuneracion::GRATIFICACION,
                    'Gratificación Legal Art.50', 'HABER_IMPONIBLE', $gratuBase, 25.0, $gratuMonto, $orden++);
            }

            // ── 3. HORAS EXTRA ─────────────────────────────────────────────
            $horasExtra = (float) ($extras['horas_extra'] ?? 0);
            if ($horasExtra > 0) {
                $jornadaSemanal = (float) ($contrato->horas_semana ?: 42);
                // Fix 4: fórmula DT — divide por 30 días y multiplica por días de jornada semanal
                $valorHoraOrdinaria = ($sueldoBase / 30) * 7 / $jornadaSemanal;
                $valorHoraExtra = $valorHoraOrdinaria * 1.5;
                $montoHorasExtra = round($horasExtra * $valorHoraExtra);
                $detalles[] = $this->lineaHaber($liq, ConceptoRemuneracion::HORAS_EXTRA,
                    'Horas Extraordinarias', 'HABER_IMPONIBLE', $montoHorasExtra, $orden++);
            }

            // ── 4. HABERES FIJOS DEL CONTRATO (imponibles y no imponibles) ─
            $contrato->load('haberes');
            $orden_ni = self::ORDEN_HABERES_NO_IMPONIBLES;

            // Fix 1: filtrar solo tipos haber; descuentos del contrato se procesan en sección 10
            foreach ($contrato->haberes->whereIn('tipo', ['HABER_IMPONIBLE', 'HABER_NO_IMPONIBLE']) as $haber) {
                if (!$haber->activo) {
                    continue;
                }
                $montoHaber = $haber->modalidad_valor === 'PORCENTAJE_SUELDO_BASE'
                    ? round($sueldoBase * ((float) $haber->porcentaje / 100))
                    : (float) $haber->monto;

                if ($haber->tipo === 'HABER_IMPONIBLE') {
                    $detalles[] = $this->lineaHaber($liq, null, $haber->nombre,
                        'HABER_IMPONIBLE', $montoHaber, $orden++, $haber->concepto_remuneracion_id);
                } else {
                    $detalles[] = $this->lineaHaber($liq, null, $haber->nombre,
                        'HABER_NO_IMPONIBLE', $montoHaber, $orden_ni++, $haber->concepto_remuneracion_id);
                }
            }

            // ── 5. ASIGNACIÓN FAMILIAR ─────────────────────────────────────
            $cargasActivas = $empleado->cargasFamiliares()->count();
            if ($cargasActivas > 0) {
                // Fix 10: base del tramo es el imponible acumulado hasta aquí, no el sueldo base
                $imponibleParaAsigFam = 0;
                foreach ($detalles as $d) {
                    if ($d['tipo'] === 'HABER_IMPONIBLE') {
                        $imponibleParaAsigFam += $d['monto'];
                    }
                }
                $montoAsigFam = $this->calcularAsignacionFamiliar($parametro, $imponibleParaAsigFam, $cargasActivas);
                if ($montoAsigFam > 0) {
                    $detalles[] = $this->lineaHaber($liq, ConceptoRemuneracion::ASIGNACION_FAMILIAR,
                        "Asignación Familiar ({$cargasActivas} cargas)", 'HABER_NO_IMPONIBLE', $montoAsigFam, $orden_ni++);
                }
            }

            // ── Totales hasta aquí ─────────────────────────────────────────
            $totalImponible = 0;
            $totalNoImponible = 0;

            foreach ($detalles as $d) {
                if ($d['tipo'] === 'HABER_IMPONIBLE') {
                    $totalImponible += $d['monto'];
                } else {
                    $totalNoImponible += $d['monto'];
                }
            }

            // ── 6. BASE IMPONIBLE CON TOPE AFP (en pesos) ──────────────────
            $topeAfpPesos = round((float) $parametro->tope_imponible_uf * $uf);
            $baseImponible = min($totalImponible, $topeAfpPesos);

            // ── 7. DESCUENTOS LEGALES ──────────────────────────────────────
            $ordenDL = self::ORDEN_DESCUENTOS_LEGALES;

            // AFP 10%
            $afpCotizacion = round($baseImponible * ((float) $parametro->afp_cotizacion_pct / 100));
            $detalles[] = $this->lineaDescuento($liq, ConceptoRemuneracion::AFP_COTIZACION,
                'AFP Cotización Obligatoria (10%)', 'DESCUENTO_LEGAL',
                $baseImponible, (float) $parametro->afp_cotizacion_pct, $afpCotizacion, $ordenDL++);

            // AFP Comisión
            $comisionPct = (float) $parametro->getComisionAfp($empleado->afp ?? 'Capital');
            $afpComision = round($baseImponible * ($comisionPct / 100));
            $detalles[] = $this->lineaDescuento($liq, ConceptoRemuneracion::AFP_COMISION,
                "AFP Comisión {$empleado->afp} ({$comisionPct}%)", 'DESCUENTO_LEGAL',
                $baseImponible, $comisionPct, $afpComision, $ordenDL++);

            // Salud (7% Fonasa o plan Isapre)
            // Fix 3: separar salud_legal (rebaja base tributable) de salud_adicional (solo descuento)
            $saludPct = (float) $parametro->salud_fonasa_pct;
            $saludBase = $baseImponible;
            $saludLegalMonto = round($saludBase * ($saludPct / 100)); // siempre 7% topado

            if ($empleado->tipo_salud === 'ISAPRE' && $empleado->isapre_plan_uf) {
                $saludIsaprePesos = round((float) $empleado->isapre_plan_uf * $uf);
                $saludAdicionalMonto = max(0, $saludIsaprePesos - $saludLegalMonto);
                $saludMonto = $saludLegalMonto + $saludAdicionalMonto;
                $saludLabel = "Salud ISAPRE {$empleado->isapre_nombre}";
            } else {
                $saludAdicionalMonto = 0;
                $saludMonto = $saludLegalMonto;
                $saludLabel = 'Salud FONASA (7%)';
            }
            $detalles[] = $this->lineaDescuento($liq, ConceptoRemuneracion::SALUD,
                $saludLabel, 'DESCUENTO_LEGAL', $saludBase, $saludPct, $saludMonto, $ordenDL++);

            // AFC — tope diferente (135.2 UF)
            $topeAfcPesos = round((float) $parametro->afc_tope_imponible_uf * $uf);
            $baseAfc = min($totalImponible, $topeAfcPesos);

            // Fix 6: el 0,6% del trabajador indefinido cesa al cumplir 11 años
            $afcTrabajadorPct = 0.0;
            if ($contrato->esIndefindo()) {
                $fechaInicioContrato = $contrato->fecha_inicio;
                $fechaPeriodoCarbon = \Carbon\Carbon::create($anio, $mes, 1);
                $aniosAntiguedad = $fechaInicioContrato
                    ? (int) $fechaInicioContrato->diffInYears($fechaPeriodoCarbon)
                    : 0;
                if ($aniosAntiguedad < 11) {
                    $afcTrabajadorPct = (float) $parametro->afc_indefinido_trabajador_pct;
                }
            } else {
                $afcTrabajadorPct = (float) $parametro->afc_plazo_fijo_trabajador_pct;
            }

            $afcMonto = round($baseAfc * ($afcTrabajadorPct / 100));
            if ($afcMonto > 0) {
                $detalles[] = $this->lineaDescuento($liq, ConceptoRemuneracion::AFC_TRABAJADOR,
                    "Seguro Cesantía Trabajador ({$afcTrabajadorPct}%)", 'DESCUENTO_LEGAL',
                    $baseAfc, $afcTrabajadorPct, $afcMonto, $ordenDL++);
            }

            // ── 8. BASE TRIBUTABLE (para impuesto único) ───────────────────
            // Fix 3: solo salud_legal rebaja base tributable; el exceso del plan Isapre no
            $totalDescLegalesPreImpuesto = $afpCotizacion + $afpComision + $saludLegalMonto + $afcMonto;
            $baseTributable = $totalImponible - $totalDescLegalesPreImpuesto;

            // APV voluntario reduce base tributable (Art. 42 bis LIR)
            $apv = (float) ($extras['apv_voluntario'] ?? 0);
            if ($apv > 0) {
                $baseTributable = max(0, $baseTributable - $apv);
            }

            // ── 9. IMPUESTO ÚNICO 2ª CATEGORÍA ────────────────────────────
            $impuestoUnico = TablaImpuestoUnico::calcularImpuesto($anio, $baseTributable, $utm);
            if ($impuestoUnico > 0) {
                $detalles[] = $this->lineaDescuento($liq, ConceptoRemuneracion::IMPUESTO_UNICO,
                    'Impuesto Único 2ª Categoría', 'DESCUENTO_LEGAL',
                    $baseTributable, 0, $impuestoUnico, $ordenDL++);
            }

            // ── 10. DESCUENTOS VOLUNTARIOS ─────────────────────────────────
            $ordenDV = self::ORDEN_DESCUENTOS_VOLUNTARIOS;
            $totalDescuentosVoluntarios = 0;

            foreach ($contrato->haberes->where('tipo', 'DESCUENTO_VOLUNTARIO') as $desc) {
                $montoDesc = $desc->modalidad_valor === 'PORCENTAJE_SUELDO_BASE'
                    ? round($sueldoBase * ((float) $desc->porcentaje / 100))
                    : (float) $desc->monto;
                $detalles[] = $this->lineaDescuento($liq, null, $desc->nombre,
                    'DESCUENTO_VOLUNTARIO', 0, 0, $montoDesc, $ordenDV++, $desc->concepto_remuneracion_id);
                $totalDescuentosVoluntarios += $montoDesc;
            }

            if ($apv > 0) {
                $detalles[] = $this->lineaDescuento($liq, null, 'APV Voluntario',
                    'DESCUENTO_VOLUNTARIO', 0, 0, $apv, $ordenDV++);
                $totalDescuentosVoluntarios += $apv;
            }

            // ── 11. APORTES EMPLEADOR (no van al líquido) ─────────────────
            $sisMonto = round($baseImponible * ((float) $parametro->afp_sis_pct / 100));
            $afcEmpleadorPct = $contrato->esIndefindo()
                ? (float) $parametro->afc_indefinido_empleador_pct
                : (float) $parametro->afc_plazo_fijo_empleador_pct;
            $afcEmpleadorMonto = round($baseAfc * ($afcEmpleadorPct / 100));
            $mutualMonto = round($baseImponible * ((float) $parametro->mutual_cotizacion_basica_pct / 100));
            // Fix 5: cotización adicional Ley 21.735 — cargo patronal puro
            $reformaMonto = round($baseImponible * ((float) $parametro->cotizacion_adicional_empleador_pct / 100));

            // ── 12. TOTALES FINALES ─────────────────────────────────────────
            $totalDescuentosLegales = $afpCotizacion + $afpComision + $saludMonto + $afcMonto + $impuestoUnico;
            $totalDescuentos = $totalDescuentosLegales + $totalDescuentosVoluntarios;

            // Validación tope Art. 58 Código del Trabajo
            $topeVoluntarios = (int) round($totalImponible * 0.15);
            if ($totalDescuentosVoluntarios > $topeVoluntarios) {
                throw RrhhException::regla(
                    "Descuentos voluntarios (\${$totalDescuentosVoluntarios}) superan el límite legal del 15% del imponible (\${$topeVoluntarios}). Art. 58 CT."
                );
            }

            $totalHaberes = $totalImponible + $totalNoImponible;
            $topeTotal = (int) round($totalHaberes * 0.45);
            if ($totalDescuentos > $topeTotal) {
                throw RrhhException::regla(
                    "Descuentos totales (\${$totalDescuentos}) superan el 45% del total de haberes (\${$topeTotal}). Art. 58 CT."
                );
            }

            $liquidoPagar = $totalHaberes - $totalDescuentos;

            // ── 13. PERSISITIR DETALLES ────────────────────────────────────
            foreach ($detalles as $det) {
                LiquidacionDetalle::create($det);
            }

            // ── 14. ACTUALIZAR CABECERA ────────────────────────────────────
            $liq->update([
                'total_haberes_imponibles' => $totalImponible,
                'total_haberes_no_imponibles' => $totalNoImponible,
                'total_haberes' => $totalImponible + $totalNoImponible,
                'base_imponible' => $baseImponible,
                'base_tributable' => $baseTributable,
                'total_descuentos_legales' => $totalDescuentosLegales,
                'total_descuentos_voluntarios' => $totalDescuentosVoluntarios,
                'total_descuentos' => $totalDescuentos,
                'liquido_a_pagar' => $liquidoPagar,
                'aporte_empleador_sis' => $sisMonto,
                'aporte_empleador_afc' => $afcEmpleadorMonto,
                'aporte_empleador_mutual' => $mutualMonto,
                'salud_legal' => $saludLegalMonto,
                'salud_adicional' => $saludAdicionalMonto,
                'aporte_empleador_reforma' => $reformaMonto,
            ]);

            return $liq->load(['detalles', 'empleado', 'contrato']);
        });
    }

    public function emitir(int $empresaId, int $liquidacionId): Liquidacion
    {
        $liq = Liquidacion::where('empresa_id', $empresaId)->find($liquidacionId);
        if (!$liq) {
            throw RrhhException::noEncontrado('La liquidación no existe o no pertenece a la empresa.');
        }
        if ($liq->estado !== Liquidacion::ESTADO_BORRADOR) {
            throw RrhhException::regla("No se puede emitir una liquidación en estado {$liq->estado}.");
        }
        $liq->update(['estado' => Liquidacion::ESTADO_EMITIDA]);
        return $liq->fresh();
    }

    public function anular(int $empresaId, int $liquidacionId): Liquidacion
    {
        $liq = Liquidacion::where('empresa_id', $empresaId)->find($liquidacionId);
        if (!$liq) {
            throw RrhhException::noEncontrado('La liquidación no existe o no pertenece a la empresa.');
        }
        if ($liq->estado === Liquidacion::ESTADO_PAGADA) {
            throw RrhhException::regla('No se puede anular una liquidación ya pagada. Genere una nota de corrección.');
        }
        // Bloquear anulación si el período ya fue centralizado en contabilidad.
        // Mismo criterio de idempotencia que CentralizacionRemuneracionesService:
        // origen_modulo='rrhh', origen_id=anio*100+mes, empresa_id.
        $periodoId = $liq->anio * 100 + $liq->mes;
        $asiento = AsientoContable::where('empresa_id', $empresaId)
            ->where('origen_modulo', 'rrhh')
            ->where('origen_id', $periodoId)
            ->first();
        if ($asiento) {
            throw RrhhException::regla(
                "No se puede anular: el período ya fue centralizado en contabilidad " .
                "(asiento N°{$asiento->numero_comprobante}). " .
                "Anule primero el asiento de centralización."
            );
        }
        $liq->update(['estado' => Liquidacion::ESTADO_ANULADA]);
        return $liq->fresh();
    }

    // ── Helpers privados ────────────────────────────────────────────────────

    private function lineaHaber(Liquidacion $liq, ?string $codigo, string $nombre, string $tipo, float $monto, int $orden, ?int $conceptoId = null): array
    {
        return [
            'empresa_id' => $liq->empresa_id,
            'liquidacion_id' => $liq->id,
            'concepto_remuneracion_id' => $conceptoId,
            'codigo_concepto' => $codigo ?? 'CUSTOM',
            'nombre_concepto' => $nombre,
            'tipo' => $tipo,
            'base_calculo' => null,
            'tasa_aplicada' => null,
            'monto' => $monto,
            'orden' => $orden,
        ];
    }

    private function lineaHaberConTasa(Liquidacion $liq, ?string $codigo, string $nombre, string $tipo, float $base, float $tasa, float $monto, int $orden): array
    {
        return [
            'empresa_id' => $liq->empresa_id,
            'liquidacion_id' => $liq->id,
            'concepto_remuneracion_id' => null,
            'codigo_concepto' => $codigo ?? 'CUSTOM',
            'nombre_concepto' => $nombre,
            'tipo' => $tipo,
            'base_calculo' => $base,
            'tasa_aplicada' => $tasa,
            'monto' => $monto,
            'orden' => $orden,
        ];
    }

    private function lineaDescuento(Liquidacion $liq, ?string $codigo, string $nombre, string $tipo, float $base, float $tasa, float $monto, int $orden, ?int $conceptoId = null): array
    {
        return [
            'empresa_id' => $liq->empresa_id,
            'liquidacion_id' => $liq->id,
            'concepto_remuneracion_id' => $conceptoId,
            'codigo_concepto' => $codigo ?? 'CUSTOM',
            'nombre_concepto' => $nombre,
            'tipo' => $tipo,
            'base_calculo' => $base > 0 ? $base : null,
            'tasa_aplicada' => $tasa > 0 ? $tasa : null,
            'monto' => $monto,
            'orden' => $orden,
        ];
    }

    private function calcularAsignacionFamiliar(ParametroPrevisional $parametro, float $totalImponible, int $cargas): float
    {
        $tramos = $parametro->asignacion_familiar_tramos_json ?? [];
        if (empty($tramos)) {
            return 0.0;
        }

        foreach ($tramos as $tramo) {
            $hasta = $tramo['hasta_pesos'] ?? null;
            if ($hasta === null || $totalImponible <= $hasta) {
                return (float) ($tramo['monto_por_carga'] ?? 0) * $cargas;
            }
        }

        return 0.0;
    }
}
