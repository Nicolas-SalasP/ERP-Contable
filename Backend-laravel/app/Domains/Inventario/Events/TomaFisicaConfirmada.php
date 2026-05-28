<?php

namespace App\Domains\Inventario\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TomaFisicaConfirmada
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $empresaId,
        public readonly int $tomaFisicaId,
        public readonly int $usuarioId,
        public readonly int $movimientosGenerados
    ) {
    }
}
