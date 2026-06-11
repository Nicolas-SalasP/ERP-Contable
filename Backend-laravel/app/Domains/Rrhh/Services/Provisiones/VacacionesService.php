<?php

namespace App\Domains\Rrhh\Services\Provisiones;

use App\Domains\Rrhh\Models\Contrato;
use App\Domains\Rrhh\Models\ProvisionVacaciones;
use Illuminate\Support\Facades\DB;

/**
 * Devengo mensual de vacaciones (Art. 67 Código del Trabajo).
 *
 * Por cada mes trabajado, el empleado devenga 1,25 días hábiles de feriado
 * (15 días hábiles anuales ÷ 12 meses). Las vacaciones progresivas (Art. 68)
 * aplican cuando el empleado acumula 10+ años de servicios.
 */
class VacacionesService
{
    // Días hábiles de vacaciones por año según ley (base Art. 67)
    private const DIAS_HABILES_ANUALES = 15.0;

    public function devengarMes(int $empresaId, int $liquidacionId): ProvisionVacaciones
    {
        return DB::transaction(function () use ($empresaId, $liquidacionId) {
            $liq = \App\Domains\Rrhh\Models\Liquidacion::where('empresa_id', $empresaId)
                ->with(['empleado', 'contrato'])
                ->findOrFail($liquidacionId);

            $contrato = $liq->contrato;
            $empleado = $liq->empleado;
            $anio = $liq->anio;
            $mes = $liq->mes;

            $diasAnuales = $this->diasAnualesSegunAntigüedad($contrato);
            $diasMes = round($diasAnuales / 12, 4);

            // Remuneración diaria (base para provisión)
            $remDiaria = round((float) $contrato->sueldo_base / 30, 2);

            $montoMes = round($remDiaria * $diasMes, 2);

            // Buscar saldo anterior
            $anterior = ProvisionVacaciones::where('empresa_id', $empresaId)
                ->where('empleado_id', $empleado->id)
                ->where(function ($q) use ($anio, $mes) {
                    $q->where('anio', $anio)->where('mes', '<', $mes)
                        ->orWhere('anio', '<', $anio);
                })
                ->orderByDesc('anio')
                ->orderByDesc('mes')
                ->first();

            $saldoAnterior = $anterior ? (float) $anterior->saldo_dias_habiles : 0.0;
            $montoAnterior = $anterior ? (float) $anterior->monto_provisionado_total : 0.0;

            return ProvisionVacaciones::updateOrCreate(
                ['empresa_id' => $empresaId, 'empleado_id' => $empleado->id, 'anio' => $anio, 'mes' => $mes],
                [
                    'contrato_id' => $contrato->id,
                    'dias_devengados_mes' => $diasMes,
                    'saldo_dias_habiles' => round($saldoAnterior + $diasMes, 4),
                    'monto_devengado_mes' => $montoMes,
                    'monto_provisionado_total' => round($montoAnterior + $montoMes, 2),
                    'remuneracion_diaria' => $remDiaria,
                ]
            );
        });
    }

    public function saldoActual(int $empresaId, int $empleadoId): array
    {
        $ultimo = ProvisionVacaciones::where('empresa_id', $empresaId)
            ->where('empleado_id', $empleadoId)
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->first();

        return [
            'dias_disponibles' => $ultimo ? (float) $ultimo->saldo_dias_habiles : 0.0,
            'monto_provisionado' => $ultimo ? (float) $ultimo->monto_provisionado_total : 0.0,
            'ultimo_periodo' => $ultimo ? "{$ultimo->mes}/{$ultimo->anio}" : null,
        ];
    }

    public function calcularVacacionesProporcionales(Contrato $contrato, string $fechaTermino): array
    {
        $fechaInicio = $contrato->fecha_inicio;
        // Días trabajados desde el último aniversario
        $aniversario = $fechaInicio->copy()->year(now()->year);
        if ($aniversario->gt(now())) {
            $aniversario->subYear();
        }

        $diasDesdeAniversario = $aniversario->diffInDays($fechaTermino);
        $diasAnuales = $this->diasAnualesSegunAntigüedad($contrato);
        $diasProporcionales = round(($diasDesdeAniversario / 365) * $diasAnuales, 4);

        $remDiaria = (float) $contrato->sueldo_base / 30;
        $monto = round($diasProporcionales * $remDiaria, 2);

        return [
            'dias' => $diasProporcionales,
            'monto' => $monto,
            'remuneracion_diaria' => $remDiaria,
        ];
    }

    private function diasAnualesSegunAntigüedad(Contrato $contrato): float
    {
        // Vacaciones progresivas Art. 68: 10+ años → 1 día extra c/3 años adicionales
        $anios = (int) $contrato->fecha_inicio->diffInYears(now());
        if ($anios < 10) {
            return self::DIAS_HABILES_ANUALES;
        }
        $extra = (int) floor(($anios - 10) / 3);
        return self::DIAS_HABILES_ANUALES + $extra;
    }
}
