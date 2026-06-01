<?php

namespace App\Domains\Sii\Jobs;

use App\Domains\Sii\Services\Emision\EmitirDteService;
use App\Domains\Sii\Services\Envio\EnvioSiiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * F6.4 — Job de reintento manual de emision sobre un DTE existente.
 *
 * NO se usa para crear DTEs nuevos (eso lo hace ProcesarFacturaParaSiiListener
 * de F6.2). Las acciones validas son:
 *   - 'reanudar_firma': invoca EmitirDteService::emitir($dteId). Aplica si el
 *                       DTE quedo en BORRADOR (firma fallo).
 *   - 'reanudar_envio': invoca EnvioSiiService::enviar($dteId). Aplica si el
 *                       DTE esta FIRMADO o el ultimo envio fallo
 *                       (ERROR_TRANSPORTE/ERROR_TIMEOUT).
 *
 * Idempotencia: los servicios internos hacen lockForUpdate del DTE/envio. Si
 * un job paralelo ya transiciono el estado, el segundo job recibe excepcion
 * del servicio (estado invalido) y la promueve a fallo del job, donde el log
 * estructurado deja evidencia.
 *
 * Configuracion: tries=2 (operador ya decidio manualmente; no queremos
 * cascada automatica). backoff [60, 300].
 */
class ReintentarEmisionDteJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const ACCION_REANUDAR_FIRMA = 'reanudar_firma';
    public const ACCION_REANUDAR_ENVIO = 'reanudar_envio';

    public int $tries = 2;
    public int $timeout = 120;
    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $dteEmitidoId,
        public readonly string $accion,
        public readonly ?string $razon = null,
        public readonly ?int $usuarioId = null
    ) {
        $this->onQueue('sii');
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(
        EmitirDteService $emitirService,
        EnvioSiiService $envioService
    ): void {
        $contexto = $this->contextoBase();

        Log::channel('sii')->info('Reintento de emision iniciado.', $contexto);

        try {
            match ($this->accion) {
                self::ACCION_REANUDAR_FIRMA => $emitirService->emitir($this->dteEmitidoId),
                self::ACCION_REANUDAR_ENVIO => $envioService->enviar($this->dteEmitidoId),
            };

            Log::channel('sii')->info('Reintento de emision completado.', $contexto);
        } catch (Throwable $e) {
            Log::channel('sii')->error('Reintento de emision fallo.', array_merge($contexto, [
                'exception_class' => $e::class,
                'message'         => $e->getMessage(),
                'trace_hash'      => substr(sha1($e->getTraceAsString()), 0, 8),
            ]));
            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::channel('sii')->critical('Reintento de emision agoto reintentos del job.', array_merge(
            $this->contextoBase(),
            [
                'exception_class' => $exception::class,
                'message'         => $exception->getMessage(),
            ]
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function contextoBase(): array
    {
        return [
            'dte_emitido_id' => $this->dteEmitidoId,
            'accion'         => $this->accion,
            'razon'          => $this->razon,
            'usuario_id'     => $this->usuarioId,
        ];
    }
}
