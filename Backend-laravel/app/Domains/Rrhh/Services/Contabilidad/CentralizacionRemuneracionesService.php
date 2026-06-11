<?php

namespace App\Domains\Rrhh\Services\Contabilidad;

use App\Domains\Contabilidad\Models\AsientoContable;
use App\Domains\Contabilidad\Services\AsientoContableService;
use App\Domains\Rrhh\Exceptions\RrhhException;
use App\Domains\Rrhh\Models\ConceptoRemuneracion;
use App\Domains\Rrhh\Models\Liquidacion;
use App\Domains\Rrhh\Models\LiquidacionDetalle;
use App\Domains\Rrhh\Models\ProvisionVacaciones;
use App\Domains\Rrhh\Models\RrhhMapeoContable;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * R5 — Centralización contable de remuneraciones.
 *
 * Genera un único asiento contable mensual que consolida todas las
 * liquidaciones EMITIDAS del período. El asiento sigue el esquema
 * estándar chileno:
 *
 *   DEBE:  Gasto Remuneraciones  (total haberes imponibles + no imponibles)
 *          Gasto Leyes Sociales   (SIS + AFC empleador + mutual)
 *          Gasto Provisión Vac.   (opcional, si está configurado)
 *
 *   HABER: Remuneraciones por Pagar  (líquido a pagar a trabajadores)
 *          Retenciones Previsionales  (AFP + salud + AFC trabajador)
 *          Impuesto Único Retenido    (2ª categoría)
 *          Leyes Sociales por Pagar   (aportes empleador)
 *          Descuentos Voluntarios     (APV, préstamos, etc. — opcional)
 *          Provisión Vacaciones       (opcional, par con GASTO)
 *
 * La partida doble cuadra siempre porque:
 *   DEBE = total_haberes + leyes_soc + vac
 *   HABER = liquido + (AFP+salud+AFC_trab) + IUSC + desc_vol + leyes_soc + vac
 *         = total_haberes + leyes_soc + vac  ✓
 *
 * Prerequisito: las cuentas del Plan se configuran en rrhh_mapeo_contable
 * (ver RrhhMapeoContable::TIPOS_REQUERIDOS).
 */
class CentralizacionRemuneracionesService
{
    public function __construct(
        private readonly AsientoContableService $asientoService,
    ) {}

    /**
     * Centraliza todas las liquidaciones EMITIDAS del período en un asiento.
     * Idempotente: lanza excepción si el período ya fue centralizado.
     */
    public function centralizar(
        int $empresaId,
        int $anio,
        int $mes,
        int $userId
    ): AsientoContable {
        // ── 1. Cargar liquidaciones EMITIDAS del período ──────────────────
        $liquidaciones = Liquidacion::where('empresa_id', $empresaId)
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->where('estado', 'EMITIDA')
            ->get();

        if ($liquidaciones->isEmpty()) {
            throw RrhhException::regla(
                "No hay liquidaciones EMITIDAS para el período {$mes}/{$anio}. " .
                "Calcule y emita las liquidaciones antes de centralizar."
            );
        }

        // ── 2. Control de idempotencia (un asiento por período) ───────────
        $periodoId = $anio * 100 + $mes;

        if (AsientoContable::where('empresa_id', $empresaId)
            ->where('origen_modulo', 'rrhh')
            ->where('origen_id', $periodoId)
            ->exists()
        ) {
            throw RrhhException::regla(
                "El período {$mes}/{$anio} ya fue centralizado. " .
                "Consulte los asientos contables de origen 'rrhh' para revisarlo."
            );
        }

        // ── 3. Validar mapeo contable configurado ─────────────────────────
        $mapeos = RrhhMapeoContable::where('empresa_id', $empresaId)
            ->get()
            ->keyBy('tipo_cuenta');

        $tiposFaltantes = array_filter(
            RrhhMapeoContable::TIPOS_REQUERIDOS,
            fn($tipo) => !$mapeos->has($tipo)
        );

        if (!empty($tiposFaltantes)) {
            throw RrhhException::regla(
                'Mapeo contable RRHH incompleto. Configure las cuentas faltantes en ' .
                'Configuración → RRHH → Mapeo Contable: ' . implode(', ', array_values($tiposFaltantes))
            );
        }

        // Las cuentas de provisión vacaciones son un par: ambas o ninguna.
        $tieneGastoVac  = $mapeos->has(RrhhMapeoContable::GASTO_PROVISION_VACACIONES);
        $tienePasivoVac = $mapeos->has(RrhhMapeoContable::PASIVO_PROVISION_VACACIONES);
        if ($tieneGastoVac !== $tienePasivoVac) {
            throw RrhhException::regla(
                'Las cuentas de provisión de vacaciones deben configurarse en par: ' .
                'GASTO_PROVISION_VACACIONES y PASIVO_PROVISION_VACACIONES.'
            );
        }

        // ── 4. Agregar totales ────────────────────────────────────────────
        $liquidacionIds   = $liquidaciones->pluck('id')->all();
        $totalHaberes     = (float) $liquidaciones->sum('total_haberes');
        $totalLeyesSoc    = (float) ($liquidaciones->sum('aporte_empleador_afc')
                          + $liquidaciones->sum('aporte_empleador_sis')
                          + $liquidaciones->sum('aporte_empleador_mutual'));
        $liquidoTotal     = (float) $liquidaciones->sum('liquido_a_pagar');
        $descVolTotal     = (float) $liquidaciones->sum('total_descuentos_voluntarios');

        // Desglose de descuentos legales desde líneas de detalle
        $detallesAgg = LiquidacionDetalle::whereIn('liquidacion_id', $liquidacionIds)
            ->select('codigo_concepto', DB::raw('SUM(monto) as total'))
            ->groupBy('codigo_concepto')
            ->get()
            ->keyBy('codigo_concepto');

        $afpTotal   = (float) ($detallesAgg->get(ConceptoRemuneracion::AFP_COTIZACION)?->total ?? 0)
                    + (float) ($detallesAgg->get(ConceptoRemuneracion::AFP_COMISION)?->total ?? 0);
        $saludTotal = (float) ($detallesAgg->get(ConceptoRemuneracion::SALUD)?->total ?? 0);
        $afcTrab    = (float) ($detallesAgg->get(ConceptoRemuneracion::AFC_TRABAJADOR)?->total ?? 0);
        $iuscTotal  = (float) ($detallesAgg->get(ConceptoRemuneracion::IMPUESTO_UNICO)?->total ?? 0);
        $retencionesPrev = $afpTotal + $saludTotal + $afcTrab;

        // Provisión de vacaciones del período (opcional)
        $provVac = 0.0;
        if ($tieneGastoVac) {
            $provVac = (float) ProvisionVacaciones::where('empresa_id', $empresaId)
                ->where('anio', $anio)
                ->where('mes', $mes)
                ->sum('monto_devengado_mes');
        }

        // ── 5. Construir líneas del asiento ───────────────────────────────
        $fecha = Carbon::create($anio, $mes)->endOfMonth()->format('Y-m-d');
        $periodo = sprintf('%02d/%d', $mes, $anio);
        $n = $liquidaciones->count();
        $glosa = "Centralización remuneraciones {$periodo} ({$n} trabajador" . ($n === 1 ? '' : 'es') . ')';

        $detalles = [];

        $this->agregarLinea($detalles, $mapeos, RrhhMapeoContable::GASTO_REMUNERACIONES,
            $totalHaberes, 0, "Gasto remuneraciones {$periodo}");

        $this->agregarLinea($detalles, $mapeos, RrhhMapeoContable::GASTO_LEYES_SOCIALES,
            $totalLeyesSoc, 0, "Gasto leyes sociales empleador {$periodo}");

        if ($provVac > 0 && $tieneGastoVac) {
            $this->agregarLinea($detalles, $mapeos, RrhhMapeoContable::GASTO_PROVISION_VACACIONES,
                $provVac, 0, "Gasto provisión vacaciones {$periodo}");
        }

        $this->agregarLinea($detalles, $mapeos, RrhhMapeoContable::PASIVO_LIQUIDO_PAGAR,
            0, $liquidoTotal, "Líquido a pagar trabajadores {$periodo}");

        $this->agregarLinea($detalles, $mapeos, RrhhMapeoContable::PASIVO_RETENCIONES_PREVISIONALES,
            0, $retencionesPrev, "Retenciones AFP+Salud+AFC trabajadores {$periodo}");

        $this->agregarLinea($detalles, $mapeos, RrhhMapeoContable::PASIVO_IMPUESTO_UNICO,
            0, $iuscTotal, "Impuesto único 2ª cat. retenido {$periodo}");

        $this->agregarLinea($detalles, $mapeos, RrhhMapeoContable::PASIVO_LEYES_SOCIALES,
            0, $totalLeyesSoc, "Aportes empleador SIS+AFC+Mutual por pagar {$periodo}");

        if ($descVolTotal > 0 && $mapeos->has(RrhhMapeoContable::PASIVO_DESCUENTOS_VOLUNTARIOS)) {
            $this->agregarLinea($detalles, $mapeos, RrhhMapeoContable::PASIVO_DESCUENTOS_VOLUNTARIOS,
                0, $descVolTotal, "Descuentos voluntarios por pagar {$periodo}");
        }

        if ($provVac > 0 && $tienePasivoVac) {
            $this->agregarLinea($detalles, $mapeos, RrhhMapeoContable::PASIVO_PROVISION_VACACIONES,
                0, $provVac, "Provisión vacaciones por pagar {$periodo}");
        }

        // Filtrar líneas con monto cero para mantener el asiento limpio
        $detalles = array_values(array_filter(
            $detalles,
            fn($d) => ($d['debe'] + $d['haber']) > 0.00
        ));

        // ── 6. Registrar asiento contable ─────────────────────────────────
        $asiento = $this->asientoService->registrarAsiento(
            [
                'empresa_id'    => $empresaId,
                'fecha'         => $fecha,
                'glosa'         => $glosa,
                'tipo_asiento'  => 'traspaso',
                'origen_modulo' => 'rrhh',
                'origen_id'     => $periodoId,
                'usuario_id'    => $userId,
                'estado'        => 'MAYORIZADO',
            ],
            $detalles
        );

        // ── 7. Vincular comprobante a las liquidaciones ───────────────────
        Liquidacion::whereIn('id', $liquidacionIds)
            ->update(['comprobante_contable' => $asiento->numero_comprobante]);

        return $asiento->load('detalles');
    }

    private function agregarLinea(
        array &$detalles,
        $mapeos,
        string $tipo,
        float $debe,
        float $haber,
        string $glosaDetalle
    ): void {
        if (!$mapeos->has($tipo)) {
            return;
        }
        $detalles[] = [
            'cuenta_contable' => $mapeos->get($tipo)->cuenta_contable_codigo,
            'debe'            => round($debe, 2),
            'haber'           => round($haber, 2),
            'tipo_operacion'  => $debe > 0 ? 'DEBE' : 'HABER',
            'glosa_detalle'   => $glosaDetalle,
        ];
    }
}
