<?php

namespace App\Domains\Contabilidad\Services\Alertas;

use App\Domains\Alertas\Contracts\EvaluadorAlerta;
use App\Domains\Alertas\Models\Alerta;
use App\Domains\Alertas\Support\CandidatoAlerta;
use App\Domains\Contabilidad\Models\PeriodoContable;
use App\Domains\Core\Models\Empresa;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Alerta cuando el periodo contable del mes anterior sigue sin cerrarse pasado el plazo
 * habitual de cierre. Solo se persisten los periodos CERRADOS (ver PeriodoContable): la
 * ausencia de fila significa ABIERTO, asi que este evaluador recorre TODAS las empresas y
 * comprueba, para el mes calendario anterior, si existe una fila CERRADO.
 */
class PeriodoSinCerrarEvaluador implements EvaluadorAlerta
{
    /** Dias de gracia tras el fin del mes antes de considerar el cierre "vencido". */
    private const DIAS_PLAZO_CIERRE = 20;

    private const DIAS_ADVERTENCIA = 30;

    private const DIAS_CRITICO = 60;

    public function tipo(): string
    {
        return 'periodo_sin_cerrar';
    }

    public function evaluar(): Collection
    {
        $hoy = Carbon::today();
        $periodoAnterior = $hoy->copy()->startOfMonth()->subMonthNoOverflow();
        $anio = $periodoAnterior->year;
        $mes = $periodoAnterior->month;

        $plazoCierre = $periodoAnterior->copy()->addMonthNoOverflow()->day(self::DIAS_PLAZO_CIERRE);
        $diasVencido = $plazoCierre->diffInDays($hoy, false);

        if ($diasVencido <= 0) {
            return collect();
        }

        $empresaIdsCerrados = PeriodoContable::query()
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->where('estado', PeriodoContable::ESTADO_CERRADO)
            ->pluck('empresa_id');

        $nivel = match (true) {
            $diasVencido > self::DIAS_CRITICO => Alerta::NIVEL_CRITICO,
            $diasVencido > self::DIAS_ADVERTENCIA => Alerta::NIVEL_ADVERTENCIA,
            default => Alerta::NIVEL_INFO,
        };

        // Codifica el periodo (anio, mes) como un id entero estable para poder deduplicar sin
        // depender de una fila real en periodos_contables (que, por definicion, no existe aqui).
        $entidadId = ($anio * 100) + $mes;
        $periodoLabel = str_pad((string) $mes, 2, '0', STR_PAD_LEFT).'/'.$anio;

        return Empresa::query()
            ->whereNotIn('id', $empresaIdsCerrados)
            ->get()
            ->map(fn (Empresa $empresa) => new CandidatoAlerta(
                empresaId: $empresa->id,
                tipo: $this->tipo(),
                nivel: $nivel,
                entidadType: 'periodo_contable',
                entidadId: $entidadId,
                mensaje: "El periodo contable {$periodoLabel} sigue sin cerrarse ({$diasVencido} dias vencido el plazo habitual de cierre).",
                datos: ['anio' => $anio, 'mes' => $mes, 'dias_vencido' => $diasVencido],
                esDiaria: $nivel === Alerta::NIVEL_CRITICO,
            ));
    }
}
