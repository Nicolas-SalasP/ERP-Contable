<?php

namespace App\Domains\Sii\Events;

use App\Domains\Comercial\Models\Factura;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento que desencadena la emision SII async para una Factura del Comercial.
 *
 * ShouldDispatchAfterCommit: dentro de una DB::transaction encola tras el commit,
 * evitando encolar jobs para facturas que luego se rollback.
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
