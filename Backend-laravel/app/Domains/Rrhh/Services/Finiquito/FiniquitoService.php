<?php

namespace App\Domains\Rrhh\Services\Finiquito;

use App\Domains\Rrhh\Models\Contrato;
use App\Domains\Rrhh\Models\Finiquito;
use App\Domains\Rrhh\Models\IndicadorMensual;
use App\Domains\Rrhh\Services\Provisiones\VacacionesService;
use App\Domains\Rrhh\Exceptions\RrhhException;
use Illuminate\Support\Facades\DB;

/**
 * Cálculo de finiquito según Código del Trabajo chileno.
 *
 * Art. 161: necesidades de empresa → derecho a indemnización por años de servicio (Art. 163)
 * Art. 163: 30 días × años completos (fracción > 6 meses = 1 año), máximo 11 años.
 *           Tope por mes: 90 UF.
 * Art. 161: aviso previo 30 días o pago sustitutivo (1 mes de última remuneración).
 * Art. 70: vacaciones proporcionales al término de cualquier contrato.
 */
class FiniquitoService
{
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

            $fechaTerminoDate = \Carbon\Carbon::parse($fechaTermino);
            $fechaInicioDate = $contrato->fecha_inicio;

            // ── Años de servicio ──────────────────────────────────────────
            $aniosCompletos = (int) $fechaInicioDate->diffInYears($fechaTerminoDate);
            $mesesFraccion = (int) $fechaInicioDate->copy()->addYears($aniosCompletos)->diffInMonths($fechaTerminoDate);
            $fraccionCuentaComoAnio = $mesesFraccion > 6;
            $aniosCalculo = min($aniosCompletos + ($fraccionCuentaComoAnio ? 1 : 0), 11);

            // ── Última remuneración ───────────────────────────────────────
            $ultimaRemuneracion = (float) $contrato->sueldo_base;
            $variablesPromedio = (float) ($datos['promedio_variables'] ?? 0);
            $baseCalculo = $ultimaRemuneracion + $variablesPromedio;

            // Tope 90 UF para base de cálculo de indemnización (Art. 163)
            $indicadorActual = IndicadorMensual::paraPeriodo(
                (int) $fechaTerminoDate->format('Y'),
                (int) $fechaTerminoDate->format('n')
            );
            if ($indicadorActual) {
                $tope90Uf = round(90 * (float) $indicadorActual->uf_valor);
                $baseCalculo = min($baseCalculo, $tope90Uf);
            }

            // ── Indemnización por años de servicio (Art. 163) ────────────
            $montoIndemnizacion = 0;
            if ($causal === 'NECESIDADES_EMPRESA' && $aniosCalculo > 0) {
                $montoIndemnizacion = round($baseCalculo * $aniosCalculo);
            }

            // ── Aviso previo sustitutivo (Art. 161) ───────────────────────
            $tieneAvisoPrevio = ($datos['aviso_previo'] ?? false) === true;
            $montoAvisoPrevio = 0;
            if ($causal === 'NECESIDADES_EMPRESA' && !$tieneAvisoPrevio) {
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

    public function firmar(int $empresaId, int $finiquitoId): Finiquito
    {
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
    }
}
