<?php

namespace App\Domains\Rrhh\Services\Finiquito;

use App\Domains\Rrhh\Models\Contrato;
use App\Domains\Rrhh\Models\Finiquito;
use App\Domains\Rrhh\Models\IndicadorMensual;
use App\Domains\Rrhh\Services\Provisiones\VacacionesService;
use App\Domains\Rrhh\Exceptions\RrhhException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cálculo de finiquito según Código del Trabajo chileno.
 *
 * Art. 161: necesidades de empresa / desahucio → derecho a indemnización por años de servicio (Art. 163)
 * Art. 163: 30 días × años completos (fracción > 6 meses = 1 año), máximo 11 años.
 *           Tope por mes: 90 UF (calculada al último día del mes ANTERIOR al término, Art. 172).
 * Art. 161: aviso previo 30 días o pago sustitutivo (1 mes de última remuneración).
 * Art. 70: vacaciones proporcionales al término de cualquier contrato.
 */
class FiniquitoService
{
    // Causales que dan derecho a indemnización por años de servicio y aviso previo (Art. 161 inc. 1 y 2)
    private const CAUSALES_INDEMNIZACION = ['NECESIDADES_EMPRESA', 'DESAHUCIO'];

    public function __construct(private readonly VacacionesService $vacaciones)
    {
    }

    public function calcular(
        int $empresaId,
        int $contratoId,
        string $causal,
        string $fechaTermino,
        array $datos = []
    ): Finiquito {
        return DB::transaction(function () use ($empresaId, $contratoId, $causal, $fechaTermino, $datos) {
            $contrato = Contrato::where('empresa_id', $empresaId)
                ->with(['empleado'])
                ->find($contratoId);

            if (!$contrato) {
                throw RrhhException::noEncontrado('El contrato no existe o no pertenece a la empresa.');
            }
            if ($contrato->estado === 'TERMINADO') {
                throw RrhhException::regla('El contrato ya fue terminado.');
            }

            $fechaTerminoDate = Carbon::parse($fechaTermino);
            $fechaInicioDate = $contrato->fecha_inicio;

            // ── Años de servicio ──────────────────────────────────────────
            $aniosCompletos = (int) $fechaInicioDate->diffInYears($fechaTerminoDate);
            $inicioFraccion = $fechaInicioDate->copy()->addYears($aniosCompletos);
            $mesesFraccion = (int) $inicioFraccion->diffInMonths($fechaTerminoDate);
            $diasRestantesFraccion = (int) $inicioFraccion->copy()->addMonths($mesesFraccion)->diffInDays($fechaTerminoDate);
            // Fix #3: fracción SUPERIOR a 6 meses (6m+1d ya cuenta como año, no solo >6m)
            $fraccionCuentaComoAnio = $mesesFraccion > 6 || ($mesesFraccion === 6 && $diasRestantesFraccion > 0);
            $aniosCalculo = min($aniosCompletos + ($fraccionCuentaComoAnio ? 1 : 0), 11);

            // ── Última remuneración ───────────────────────────────────────
            $ultimaRemuneracion = (float) $contrato->sueldo_base;
            $variablesPromedio = (float) ($datos['promedio_variables'] ?? 0);
            $baseCalculo = $ultimaRemuneracion + $variablesPromedio;

            // Fix #4: tope 90 UF usa UF del último día del mes ANTERIOR al término (Art. 172 CdT)
            $mesAnterior = $fechaTerminoDate->copy()->startOfMonth()->subMonth();
            $indicadorMesAnterior = IndicadorMensual::paraPeriodo(
                (int) $mesAnterior->format('Y'),
                (int) $mesAnterior->format('n')
            );
            if (!$indicadorMesAnterior) {
                throw RrhhException::regla(
                    "No existe el indicador UF para {$mesAnterior->format('m/Y')} "
                    . "(mes anterior al término). Registre el indicador antes de calcular el finiquito."
                );
            }
            $tope90Uf = round(90 * (float) $indicadorMesAnterior->uf_valor);
            $baseCalculo = min($baseCalculo, $tope90Uf);

            // Fix #2: DESAHUCIO (Art. 161 inc. 2) también da derecho a indemnización y aviso previo
            $tieneDerechoIndemnizacion = in_array($causal, self::CAUSALES_INDEMNIZACION, true);

            // ── Indemnización por años de servicio (Art. 163) ────────────
            $montoIndemnizacion = 0;
            if ($tieneDerechoIndemnizacion && $aniosCalculo > 0) {
                $montoIndemnizacion = round($baseCalculo * $aniosCalculo);
            }

            // ── Aviso previo sustitutivo (Art. 161) ───────────────────────
            $tieneAvisoPrevio = ($datos['aviso_previo'] ?? false) === true;
            $montoAvisoPrevio = 0;
            if ($tieneDerechoIndemnizacion && !$tieneAvisoPrevio) {
                $montoAvisoPrevio = round($baseCalculo);
            }

            // ── Vacaciones proporcionales (Art. 70) ───────────────────────
            $vacsProp = $this->vacaciones->calcularVacacionesProporcionales($contrato, $fechaTermino);

            // ── Total ─────────────────────────────────────────────────────
            $totalBruto = $montoIndemnizacion + $montoAvisoPrevio + $vacsProp['monto']
                + (float) ($datos['haberes_pendientes'] ?? 0);

            $totalNeto = $totalBruto - (float) ($datos['descuentos_pendientes'] ?? 0);

            $finiquito = Finiquito::create([
                'empresa_id' => $empresaId,
                'empleado_id' => $contrato->empleado_id,
                'contrato_id' => $contratoId,
                'causal' => $causal,
                'fecha_termino' => $fechaTermino,
                'fecha_inicio_contrato' => $fechaInicioDate->toDateString(),
                'ultima_remuneracion_mensual' => $ultimaRemuneracion,
                'promedio_remuneraciones_variables' => $variablesPromedio,
                'remuneracion_base_calculo' => $baseCalculo,
                'anios_servicio' => $aniosCompletos,
                'meses_fraccion' => $mesesFraccion,
                'fraccion_cuenta_como_anio' => $fraccionCuentaComoAnio,
                'anios_calculo' => $aniosCalculo,
                'monto_indemnizacion_anos' => $montoIndemnizacion,
                'tiene_aviso_previo' => $tieneAvisoPrevio,
                'monto_aviso_previo' => $montoAvisoPrevio,
                'dias_vacaciones_proporcionales' => $vacsProp['dias'],
                'monto_vacaciones_proporcionales' => $vacsProp['monto'],
                'haberes_pendientes' => (float) ($datos['haberes_pendientes'] ?? 0),
                'descuentos_pendientes' => (float) ($datos['descuentos_pendientes'] ?? 0),
                'total_bruto' => $totalBruto,
                'total_neto' => $totalNeto,
                'estado' => 'BORRADOR',
                'observaciones' => $datos['observaciones'] ?? null,
            ]);

            return $finiquito->load(['empleado', 'contrato']);
        });
    }

    // Fix #5: firmar() envuelto en DB::transaction para atomicidad
    public function firmar(int $empresaId, int $finiquitoId): Finiquito
    {
        return DB::transaction(function () use ($empresaId, $finiquitoId) {
            $finiquito = Finiquito::where('empresa_id', $empresaId)->findOrFail($finiquitoId);
            if ($finiquito->estado !== 'BORRADOR') {
                throw RrhhException::regla("El finiquito ya está en estado {$finiquito->estado}.");
            }
            $finiquito->update(['estado' => 'FIRMADO']);

            // Terminar el contrato asociado
            $contrato = Contrato::find($finiquito->contrato_id);
            if ($contrato && $contrato->estado !== 'TERMINADO') {
                $contrato->update([
                    'estado' => 'TERMINADO',
                    'es_contrato_activo' => false,
                    'causal_termino' => $finiquito->causal,
                    'fecha_termino_real' => $finiquito->fecha_termino,
                ]);
            }

            return $finiquito->fresh();
        });
    }

    /**
     * Revierte un finiquito FIRMADO por error: reactiva el contrato asociado
     * si sigue terminado por este mismo finiquito. No hay asiento contable que
     * reversar (Finiquito.comprobante_contable nunca se setea en este servicio).
     */
    public function anular(int $empresaId, int $finiquitoId, string $motivo): Finiquito
    {
        return DB::transaction(function () use ($empresaId, $finiquitoId, $motivo) {
            $finiquito = Finiquito::where('empresa_id', $empresaId)->findOrFail($finiquitoId);

            if ($finiquito->estado === 'ANULADO') {
                throw RrhhException::regla('Este finiquito ya fue anulado anteriormente.');
            }
            if ($finiquito->estado === 'PAGADO') {
                throw RrhhException::regla('No se puede anular un finiquito ya pagado.');
            }
            if ($finiquito->estado !== 'FIRMADO') {
                throw RrhhException::regla("No se puede anular un finiquito en estado {$finiquito->estado}.");
            }

            $finiquito->update([
                'estado' => 'ANULADO',
                'observaciones' => trim(($finiquito->observaciones ?? '') . "\n[ANULADO] {$motivo}"),
            ]);

            // Reactiva el contrato solo si sigue terminado por ESTE finiquito
            // (mismo causal + fecha que firmar() escribio) -- si algo mas lo
            // modifico despues, no lo tocamos para no pisar otro estado.
            $contrato = Contrato::find($finiquito->contrato_id);
            if ($contrato
                && $contrato->estado === 'TERMINADO'
                && $contrato->causal_termino === $finiquito->causal
                && $contrato->fecha_termino_real?->toDateString() === $finiquito->fecha_termino->toDateString()
            ) {
                $contrato->update([
                    'estado' => 'VIGENTE',
                    'es_contrato_activo' => true,
                    'causal_termino' => null,
                    'fecha_termino_real' => null,
                ]);
            }

            return $finiquito->fresh();
        });
    }
}
