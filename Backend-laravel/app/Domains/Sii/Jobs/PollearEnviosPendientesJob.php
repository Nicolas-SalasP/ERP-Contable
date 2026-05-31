<?php

namespace App\Domains\Sii\Jobs;

use App\Domains\Sii\Models\SiiEnvioDte;
use App\Domains\Sii\Services\Polling\PollearEstadoSiiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * F5.3 — Job programado cada 5 min. Recorre envios en ENVIADO y, para cada
 * uno que ya toca pollear segun backoff, hace HTTP al SII.
 *
 * R7 (HARDENING-1): try/catch por envio garantiza que una excepcion en un
 * envio NO aborta el procesamiento de los demas.
 */
class PollearEnviosPendientesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public ?int $limit = null)
    {
    }

    public function handle(PollearEstadoSiiService $service): void
    {
        $query = SiiEnvioDte::query()
            ->where('estado_envio', SiiEnvioDte::ESTADO_ENVIADO)
            ->orderByRaw('fecha_ultimo_polling IS NULL DESC')
            ->orderBy('fecha_ultimo_polling', 'asc')
            ->orderBy('fecha_envio', 'asc');

        if ($this->limit !== null) {
            $query->limit($this->limit);
        }

        $envios = $query->get();

        $procesados      = 0;
        $skippedNoToca   = 0;
        $erroresAislados = [];

        foreach ($envios as $envio) {
            try {
                if (! $service->yaTocaPollear($envio)) {
                    $skippedNoToca++;
                    continue;
                }

                $service->pollear($envio);
                $procesados++;
            } catch (Throwable $e) {
                $erroresAislados[] = [
                    'envio_id'   => $envio->id,
                    'exception'  => $e::class,
                    'message'    => $e->getMessage(),
                    'trace_hash' => substr(sha1($e->getTraceAsString()), 0, 8),
                ];
                Log::channel('sii')->error('Falla aislada polleando envio.', [
                    'envio_id'  => $envio->id,
                    'exception' => $e::class,
                    'message'   => $e->getMessage(),
                ]);
            }
        }

        Log::channel('sii')->info('PollearEnviosPendientesJob completado.', [
            'total_envios'     => $envios->count(),
            'procesados'       => $procesados,
            'skipped_no_toca'  => $skippedNoToca,
            'con_error'        => count($erroresAislados),
            'errores'          => $erroresAislados,
        ]);
    }
}
