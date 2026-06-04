<?php

namespace App\Domains\Sii\Listeners;

use App\Domains\Sii\Events\FacturaListaParaEmitirEvent;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Services\Emision\EmitirDteService;
use App\Domains\Sii\Services\Envio\EnvioSiiService;
use App\Domains\Sii\Services\Mapping\FacturaAComercialDteMapper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Listener async que orquesta el flujo SII completo desde una Factura:
 * mapeo → firma → envio. El polling automatico lleva el envio a su estado terminal.
 *
 * Si la factura ya tiene un DTE asociado, reanuda desde el paso pendiente segun
 * su estado. Cada paso re-lanza para que la queue reintente segun $tries/$backoff.
 */
class ProcesarFacturaParaSiiListener implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue       = 'sii';
    public int    $tries       = 3;
    public int    $timeout     = 120;
    public bool   $failOnTimeout = true;

    public function __construct(
        private readonly FacturaAComercialDteMapper $mapper,
        private readonly EmitirDteService $emitirService,
        private readonly EnvioSiiService $envioService
    ) {
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(FacturaListaParaEmitirEvent $event): void
    {
        $factura = $event->factura->fresh(['cliente', 'empresa', 'detalles']);

        $contextoBase = [
            'factura_id' => $factura->id,
            'empresa_id' => $factura->empresa_id,
            'origen'     => $event->origen,
            'usuario_id' => $event->usuarioId,
        ];

        // Si el DTE ya esta en un estado terminal/enviado, skip; de lo contrario
        // se reanuda desde el paso pendiente en vez de relanzar todo.
        $estadosYaProcesados = [
            SiiDteEmitido::ESTADO_ENVIADO_SII,
            SiiDteEmitido::ESTADO_EN_PROCESO_SII,
            SiiDteEmitido::ESTADO_ACEPTADO,
            SiiDteEmitido::ESTADO_ACEPTADO_CON_REPAROS,
            SiiDteEmitido::ESTADO_RECHAZADO,
            SiiDteEmitido::ESTADO_REEMITIDO,
            SiiDteEmitido::ESTADO_ANULADO_CON_NC,
            SiiDteEmitido::ESTADO_ANULADO_FALLO_INTERNO,
        ];

        $dte = $factura->sii_dte_emitido_id !== null
            ? SiiDteEmitido::find($factura->sii_dte_emitido_id)
            : null;

        if ($dte && in_array($dte->estado, $estadosYaProcesados, true)) {
            Log::channel('sii')->info(
                'Listener skip: el DTE ya fue enviado/terminal.',
                array_merge($contextoBase, ['dte_id' => $dte->id, 'estado' => $dte->estado])
            );
            return;
        }

        // Mapeo Factura -> SiiDteEmitido BORRADOR, solo si aun no existe.
        if (!$dte) {
            try {
                $dte = $this->mapper->mapear($factura, $event->referencias);
                Log::channel('sii')->info(
                    'Factura mapeada a DTE BORRADOR.',
                    array_merge($contextoBase, ['dte_id' => $dte->id, 'paso' => 'mapeo'])
                );
            } catch (Throwable $e) {
                $this->logError($contextoBase, $e, 'mapeo', null);
                throw $e;
            }
        }

        // Firmado + folio + persistencia. Se omite si ya esta firmado.
        if ($dte->estado !== SiiDteEmitido::ESTADO_FIRMADO) {
            try {
                $this->emitirService->emitir($dte->id);
                Log::channel('sii')->info(
                    'DTE firmado correctamente.',
                    array_merge($contextoBase, ['dte_id' => $dte->id, 'paso' => 'firma'])
                );
            } catch (Throwable $e) {
                $this->logError($contextoBase, $e, 'firma', $dte->id);
                throw $e;
            }
        }

        // Envio al WS DTEUpload; el polling hace el resto hasta ACEPTADO/RECHAZADO.
        try {
            $this->envioService->enviar($dte->id);
            Log::channel('sii')->info(
                'DTE enviado al SII; polling de F5.3 tomara el resto.',
                array_merge($contextoBase, ['dte_id' => $dte->id, 'paso' => 'envio'])
            );
        } catch (Throwable $e) {
            $this->logError($contextoBase, $e, 'envio', $dte->id);
            throw $e;
        }
    }

    public function failed(FacturaListaParaEmitirEvent $event, Throwable $exception): void
    {
        Log::channel('sii')->critical(
            'Listener fallo despues de todos los reintentos.',
            [
                'factura_id'      => $event->factura->id,
                'origen'          => $event->origen,
                'usuario_id'      => $event->usuarioId,
                'tries_usados'    => $this->tries,
                'exception_class' => $exception::class,
                'message'         => $exception->getMessage(),
            ]
        );
        // F6.4 expondra endpoints para reintento manual.
    }

    /**
     * @param array<string, mixed> $contextoBase
     */
    private function logError(array $contextoBase, Throwable $e, string $paso, ?int $dteId): void
    {
        Log::channel('sii')->error(
            "Falla en paso '{$paso}' del listener de emision SII.",
            array_merge($contextoBase, [
                'paso'            => $paso,
                'dte_id'          => $dteId,
                'exception_class' => $e::class,
                'message'         => $e->getMessage(),
                'trace_hash'      => substr(sha1($e->getTraceAsString()), 0, 8),
            ])
        );
    }
}
