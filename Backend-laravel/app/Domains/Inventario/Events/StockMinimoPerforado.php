<?php

namespace App\Domains\Inventario\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockMinimoPerforado
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $empresaId,
        public readonly int $productoId,
        public readonly int $bodegaId,
        public readonly float $stockActual,
        public readonly float $stockMinimo,
        public readonly ?int $movimientoId = null
    ) {
    }
}
