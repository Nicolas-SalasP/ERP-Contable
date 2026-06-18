<?php

namespace App\Domains\Rrhh\Services\Emrcl;

use App\Domains\Rrhh\Exceptions\RrhhException;
use App\Domains\Rrhh\Models\Liquidacion;

class GenerarEmrclService
{
    public function generar(int $empresaId, int $anio, int $mes): array
    {
        $liquidaciones = Liquidacion::where('empresa_id', $empresaId)
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->whereIn('estado', [Liquidacion::ESTADO_EMITIDA, 'PAGADA'])
            ->with(['empleado', 'contrato', 'detalles'])
            ->get();

        if ($liquidaciones->isEmpty()) {
            throw new RrhhException(
                "No hay liquidaciones emitidas para el período {$mes}/{$anio}."
            );
        }

        $cuadro1 = ['mujeres' => 0, 'hombres' => 0, 'total' => 0];
        $cuadro2 = [
            'mujeres' => ['remuneraciones_ordinarias' => 0, 'aportes_patronales' => 0],
            'hombres' => ['remuneraciones_ordinarias' => 0, 'aportes_patronales' => 0],
        ];
        $cuadro3 = [
            'mujeres' => ['afp' => 0, 'salud' => 0, 'afc' => 0, 'impuesto' => 0],
            'hombres' => ['afp' => 0, 'salud' => 0, 'afc' => 0, 'impuesto' => 0],
        ];
        $cuadro4 = [
            'mujeres' => ['horas_pagadas' => 0, 'derivado' => true],
            'hombres' => ['horas_pagadas' => 0, 'derivado' => true],
        ];
        $advertencias       = [];
        $haySexoIndefinido  = false;

        foreach ($liquidaciones as $liq) {
            $sexo  = $liq->empleado?->sexo;
            $grupo = $sexo === 'F' ? 'mujeres' : 'hombres';

            if (! in_array($sexo, ['M', 'F'], true)) {
                $haySexoIndefinido = true;
            }

            // Cuadro 1 — cantidad de trabajadores
            $cuadro1[$grupo]++;

            // Cuadro 2 — remuneraciones brutas y aportes patronales
            // Remuneraciones ordinarias = total_haberes de la liquidación mensual
            // (los finiquitos son modelos separados — Finiquito, no Liquidacion)
            $cuadro2[$grupo]['remuneraciones_ordinarias'] += (int) $liq->total_haberes;
            $cuadro2[$grupo]['aportes_patronales']        += (int) (
                ($liq->aporte_empleador_afc    ?? 0)
                + ($liq->aporte_empleador_mutual ?? 0)
                + ($liq->aporte_empleador_sis   ?? 0)
            );

            // Cuadro 3 — deducciones legales del trabajador
            $detallesPorCodigo = $liq->detalles->keyBy('codigo_concepto');

            $cuadro3[$grupo]['afp'] += (int) (
                ($detallesPorCodigo['AFP_COTIZACION']?->monto ?? 0)
                + ($detallesPorCodigo['AFP_COMISION']?->monto ?? 0)
            );
            $cuadro3[$grupo]['salud']    += (int) ($liq->salud_legal ?? 0);
            $cuadro3[$grupo]['afc']      += (int) ($detallesPorCodigo['AFC_TRABAJADOR']?->monto ?? 0);
            $cuadro3[$grupo]['impuesto'] += (int) ($detallesPorCodigo['IMPUESTO_UNICO']?->monto ?? 0);

            // Cuadro 4 — horas pagadas derivadas de días trabajados × (horas_semana ÷ 5)
            $diasTrabajados = (int) ($liq->dias_trabajados ?? 0);
            $horasSemana    = (int) ($liq->contrato?->horas_semana ?? 45);
            $horasPorDia    = $horasSemana > 0 ? round($horasSemana / 5, 2) : 9.0;
            $cuadro4[$grupo]['horas_pagadas'] += (int) round($diasTrabajados * $horasPorDia);
        }

        $cuadro1['total'] = $cuadro1['mujeres'] + $cuadro1['hombres'];

        $advertencias[] = 'Las horas del Cuadro N°4 se derivaron multiplicando días trabajados × (horas semanales ÷ 5). Verifique antes de informar al INE.';

        if ($haySexoIndefinido) {
            $advertencias[] = 'Uno o más trabajadores tienen sexo no definido (O o sin registro) y fueron contabilizados en el grupo Hombres. Revise los datos de empleados.';
        }

        return [
            'periodo'                => ['anio' => $anio, 'mes' => $mes],
            'cuadro1_trabajadores'   => $cuadro1,
            'cuadro2_remuneraciones' => $cuadro2,
            'cuadro3_deducciones'    => $cuadro3,
            'cuadro4_horas'          => $cuadro4,
            'advertencias'           => $advertencias,
        ];
    }
}
