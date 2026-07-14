<?php

namespace App\Domains\Contabilidad\Services\Alertas;

use App\Domains\Alertas\Contracts\EvaluadorAlerta;
use App\Domains\Alertas\Models\Alerta;
use App\Domains\Alertas\Support\CandidatoAlerta;
use App\Domains\Core\Models\Empresa;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Alerta cuando el F29 del mes anterior no ha sido centralizado contablemente pasada la fecha
 * limite habitual del SII (dia 12/13 del mes siguiente para la mayoria de los contribuyentes;
 * se usa el dia 12 como aproximacion conservadora, sin distinguir extension electronica por RUT).
 * El sistema no modela un estado "F29 declarado" propio: se usa como proxy el mismo criterio que
 * F29DriftService -- existencia de un asiento MAYORIZADO con glosa "Centralizacion F29 - MM/YYYY"
 * y origen_modulo=impuestos.
 */
class F29SinDeclararEvaluador implements EvaluadorAlerta
{
    private const DIA_LIMITE = 12;

    public function tipo(): string
    {
        return 'f29_sin_declarar';
    }

    public function evaluar(): Collection
    {
        $hoy = Carbon::today();
        $periodoAnterior = $hoy->copy()->startOfMonth()->subMonthNoOverflow();
        $anio = $periodoAnterior->year;
        $mes = $periodoAnterior->month;

        $fechaLimite = $periodoAnterior->copy()->addMonthNoOverflow()->day(self::DIA_LIMITE);
        $diasVencido = $fechaLimite->diffInDays($hoy, false);

        if ($diasVencido <= 0) {
            return collect();
        }

        $glosa = 'Centralización F29 - '.str_pad((string) $mes, 2, '0', STR_PAD_LEFT)."/$anio";

        $empresaIdsDeclarados = DB::table('asientos_contables')
            ->where('origen_modulo', 'impuestos')
            ->where('glosa', $glosa)
            ->where('estado', 'MAYORIZADO')
            ->distinct()
            ->pluck('empresa_id');

        $nivel = $diasVencido > 15 ? Alerta::NIVEL_CRITICO : Alerta::NIVEL_ADVERTENCIA;
        $entidadId = ($anio * 100) + $mes;
        $periodoLabel = str_pad((string) $mes, 2, '0', STR_PAD_LEFT).'/'.$anio;

        return Empresa::query()
            ->whereNotIn('id', $empresaIdsDeclarados)
            ->get()
            ->map(fn (Empresa $empresa) => new CandidatoAlerta(
                empresaId: $empresa->id,
                tipo: $this->tipo(),
                nivel: $nivel,
                entidadType: 'f29',
                entidadId: $entidadId,
                mensaje: "El F29 del periodo {$periodoLabel} no aparece centralizado contablemente ({$diasVencido} dias vencido el plazo habitual de declaracion).",
                datos: ['anio' => $anio, 'mes' => $mes, 'dias_vencido' => $diasVencido],
                esDiaria: $nivel === Alerta::NIVEL_CRITICO,
            ));
    }
}
