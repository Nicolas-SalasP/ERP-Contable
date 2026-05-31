<?php

namespace App\Domains\Sii\Events;

use App\Domains\Comercial\Models\Factura;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * F6.2 — Evento que desencadena la emision SII async para una Factura del
 * Comercial.
 *
 * ShouldDispatchAfterCommit: si se dispara dentro de una DB::transaction
 * abierta, Laravel encola DESPUES del commit. Si no hay tx, se encola
 * inmediato. Esto previene encolar jobs para facturas que despues se
 * rollback (HARDENING F6.0 H5).
 *
 * Listener registrado: ProcesarFacturaParaSiiListener (queue=sii, async).
 */
class FacturaListaParaEmitirEvent implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param array<int, array<string, mixed>> $referencias  shape para tipo_dte ∈ {56,61}
     * @param string                           $origen  'manual'|'automatico'|'reintento'
     */
    public function __construct(
        public readonly Factura $factura,
        public readonly array $referencias = [],
        public readonly string $origen = 'manual',
        public readonly ?int $usuarioId = null
    ) {
    }
}
