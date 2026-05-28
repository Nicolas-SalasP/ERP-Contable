<?php

namespace App\Domains\Inventario\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoteVencidoDetectado
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $empresaId,
        public readonly int $productoId,
        public readonly int $bodegaId,
        public readonly int $loteId,
        public readonly float $stockActual,
        public readonly string $fechaVencimiento,
        public readonly string $referencia
    ) {
    }
}
