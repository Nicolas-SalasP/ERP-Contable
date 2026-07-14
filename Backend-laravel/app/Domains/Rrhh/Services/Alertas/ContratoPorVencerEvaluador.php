<?php

namespace App\Domains\Rrhh\Services\Alertas;

use App\Domains\Alertas\Contracts\EvaluadorAlerta;
use App\Domains\Alertas\Models\Alerta;
use App\Domains\Alertas\Support\CandidatoAlerta;
use App\Domains\Core\Scopes\EmpresaScope;
use App\Domains\Rrhh\Models\Contrato;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Alerta por contratos a plazo fijo que vencen dentro de los proximos N dias y siguen activos
 * (evita que un plazo fijo termine sin que RRHH prepare renovacion o finiquito a tiempo).
 */
class ContratoPorVencerEvaluador implements EvaluadorAlerta
{
    private const DIAS_VENTANA = 30;

    private const DIAS_CRITICO = 7;

    public function tipo(): string
    {
        return 'contrato_por_vencer';
    }

    public function evaluar(): Collection
    {
        $hoy = Carbon::today();
        $limite = $hoy->copy()->addDays(self::DIAS_VENTANA);

        $contratos = Contrato::withoutGlobalScope(EmpresaScope::class)
            ->with('empleado')
            ->where('tipo', '!=', 'INDEFINIDO')
            ->where('es_contrato_activo', true)
            ->whereNotNull('fecha_termino')
            ->whereNull('fecha_termino_real')
            ->whereBetween('fecha_termino', [$hoy->toDateString(), $limite->toDateString()])
            ->get();

        $candidatos = collect();

        foreach ($contratos as $contrato) {
            $diasParaVencer = $hoy->diffInDays($contrato->fecha_termino, false);
            $nivel = $diasParaVencer <= self::DIAS_CRITICO ? Alerta::NIVEL_CRITICO : Alerta::NIVEL_ADVERTENCIA;
            $nombre = (string) ($contrato->empleado->nombre_completo ?? 'empleado sin nombre');

            $candidatos->push(new CandidatoAlerta(
                empresaId: (int) $contrato->empresa_id,
                tipo: $this->tipo(),
                nivel: $nivel,
                entidadType: Contrato::class,
                entidadId: (int) $contrato->id,
                mensaje: "El contrato a plazo fijo de {$nombre} vence en {$diasParaVencer} dias ({$contrato->fecha_termino->toDateString()}).",
                datos: [
                    'contrato_id' => $contrato->id,
                    'empleado_id' => $contrato->empleado_id,
                    'fecha_termino' => $contrato->fecha_termino->toDateString(),
                    'dias_para_vencer' => $diasParaVencer,
                ],
                esDiaria: $nivel === Alerta::NIVEL_CRITICO,
            ));
        }

        return $candidatos;
    }
}
