<?php

namespace App\Domains\Sii\Listeners;

use App\Domains\Sii\Events\FacturaListaParaEmitirEvent;
use App\Domains\Sii\Services\Emision\EmitirDteService;
use App\Domains\Sii\Services\Envio\EnvioSiiService;
use App\Domains\Sii\Services\Mapping\FacturaAComercialDteMapper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * F6.2 — Listener async que orquesta el flujo SII completo desde una Factura:
 * mapeo (F6.1) → firma (F4.4) → envio (F5.2). El polling automatico de
 * F5.3 (job programado cada 5 min) lleva el envio a su estado terminal.
 *
 * Idempotencia: si la factura ya tiene sii_dte_emitido_id seteado, skip
 * silencioso (no relanza). Reanudacion desde paso intermedio (DTE en
 * BORRADOR/FIRMADO tras fallo previo) sera via endpoints de F6.4.
 *
 * Aislamiento de excepciones (HARDENING R7): cada paso en try/catch
 * propio con log estructurado + trace_hash. Re-throw para que la queue
 * marque failed y reintente segun $tries/$backoff.
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

        // 1. Idempotencia: si ya tiene DTE asociado, skip silencioso.
        if ($factura->sii_dte_emitido_id !== null) {
            Log::channel('sii')->info(
                'Listener skip: factura ya tiene DTE asociado.',
                array_merge($contextoBase, ['dte_id' => $factura->sii_dte_emitido_id])
            );
            return;
        }

        // 2. PASO 1: Mapeo Factura -> SiiDteEmitido BORRADOR (F6.1).
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

        // 3. PASO 2: Firmado + folio + persistencia (F4.4).
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

        // 4. PASO 3: Envio al WS DTEUpload (F5.2). El polling de F5.3 hace
        //    el resto hasta ACEPTADO/RECHAZADO.
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
