<?php

namespace App\Domains\Alertas\Jobs;

use App\Domains\Alertas\Contracts\EvaluadorAlerta;
use App\Domains\Alertas\Models\Alerta;
use App\Domains\Alertas\Notifications\AlertaNotification;
use App\Domains\Alertas\Support\CandidatoAlerta;
use App\Domains\Core\Models\Empresa;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Orquesta todos los EvaluadorAlerta registrados en config('alertas.evaluadores'). Patron
 * calcado de MonitorearVencimientoCertificadosJob (Sii): lock atomico Cache::add para evitar
 * duplicados entre cron/comando manual/workers concurrentes, dedupe persistente contra la
 * tabla `alertas`, y aislamiento de errores por evaluador y por candidato -- una empresa o un
 * tipo de alerta rota nunca debe abortar el resto (multitenant).
 */
class MotorAlertasJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function handle(): void
    {
        $contador = ['enviadas' => 0, 'fallidas' => 0, 'skipped' => 0];
        $erroresEvaluadores = [];

        foreach ($this->resolverEvaluadores() as $evaluador) {
            try {
                $candidatos = $evaluador->evaluar();
            } catch (Throwable $e) {
                // Un evaluador roto (ej. una tabla que aun no migro) no debe tumbar a los demas.
                $erroresEvaluadores[] = [
                    'evaluador' => $evaluador::class,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ];
                Log::error('MotorAlertasJob: fallo aislado evaluando tipo de alerta.', [
                    'evaluador' => $evaluador::class,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);

                continue;
            }

            foreach ($candidatos as $candidato) {
                try {
                    $resultado = $this->procesarCandidato($candidato);
                    $contador[$resultado]++;
                } catch (Throwable $e) {
                    $contador['fallidas']++;
                    Log::error('MotorAlertasJob: fallo aislado procesando candidato.', [
                        'tipo' => $candidato->tipo,
                        'empresa_id' => $candidato->empresaId,
                        'entidad_type' => $candidato->entidadType,
                        'entidad_id' => $candidato->entidadId,
                        'exception' => $e::class,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }

        Log::info('MotorAlertasJob finalizado.', [
            'enviadas' => $contador['enviadas'],
            'fallidas' => $contador['fallidas'],
            'skipped' => $contador['skipped'],
            'errores_evaluadores' => $erroresEvaluadores,
        ]);
    }

    /** @return array<int, EvaluadorAlerta> */
    private function resolverEvaluadores(): array
    {
        $clases = config('alertas.evaluadores', []);

        return array_map(static fn (string $clase) => app($clase), $clases);
    }

    /**
     * @return 'enviadas'|'fallidas'|'skipped'
     */
    private function procesarCandidato(CandidatoAlerta $candidato): string
    {
        // Lock atomico de corta duracion: protege contra dos ejecuciones concurrentes (cron +
        // comando manual, o dos workers) leyendo "no enviado" a la vez y duplicando el email.
        $lockKey = $this->lockKey($candidato);
        if (! Cache::add($lockKey, true, 30)) {
            return 'skipped';
        }

        if ($this->yaNotificado($candidato)) {
            return 'skipped';
        }

        $empresa = Empresa::find($candidato->empresaId);
        $destinatario = $this->resolverDestinatario($empresa);

        $alerta = Alerta::create([
            'empresa_id' => $candidato->empresaId,
            'tipo' => $candidato->tipo,
            'nivel' => $candidato->nivel,
            'entidad_type' => $candidato->entidadType,
            'entidad_id' => $candidato->entidadId,
            'mensaje' => $candidato->mensaje,
            'datos' => $candidato->datos,
            'estado' => Alerta::ESTADO_PENDIENTE,
        ]);

        if ($destinatario === null) {
            Log::warning('MotorAlertasJob: empresa sin email destinatario, alerta creada sin envio.', [
                'alerta_id' => $alerta->id,
                'empresa_id' => $candidato->empresaId,
            ]);

            return 'skipped';
        }

        try {
            Notification::route('mail', $destinatario)->notify(new AlertaNotification($alerta));

            $alerta->update([
                'estado' => Alerta::ESTADO_ENVIADA,
                'enviada_at' => now(),
            ]);

            return 'enviadas';
        } catch (Throwable $e) {
            $alerta->update(['error_mensaje' => $e->getMessage()]);

            Log::error('MotorAlertasJob: fallo el envio de la notificacion.', [
                'alerta_id' => $alerta->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return 'fallidas';
        }
    }

    private function lockKey(CandidatoAlerta $candidato): string
    {
        $sufijo = $candidato->esDiaria ? now()->toDateString() : 'unico';

        return sprintf(
            'alertas:notificar:%d:%s:%s:%s:%s:%s',
            $candidato->empresaId,
            $candidato->tipo,
            $candidato->entidadType ?? 'na',
            $candidato->entidadId ?? 'na',
            $candidato->nivel,
            $sufijo
        );
    }

    /**
     * Dedupe persistente contra la tabla `alertas` (el lock de Cache es solo anti-carrera de 30s).
     * Diarias: no se repite el mismo dia. One-shot: no se repite mientras siga pendiente/enviada
     * en el mismo nivel (si el nivel escala, es una alerta nueva y por tanto un dedupe distinto).
     */
    private function yaNotificado(CandidatoAlerta $candidato): bool
    {
        $query = Alerta::query()
            ->where('empresa_id', $candidato->empresaId)
            ->where('tipo', $candidato->tipo)
            ->where('nivel', $candidato->nivel)
            ->where('entidad_type', $candidato->entidadType)
            ->where('entidad_id', $candidato->entidadId)
            ->whereIn('estado', [Alerta::ESTADO_PENDIENTE, Alerta::ESTADO_ENVIADA]);

        if ($candidato->esDiaria) {
            $query->whereDate('created_at', now()->toDateString());
        }

        return $query->exists();
    }

    private function resolverDestinatario(?Empresa $empresa): ?string
    {
        if ($empresa === null) {
            return null;
        }

        $email = $empresa->email ?? null;

        return (is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) ? $email : null;
    }
}
