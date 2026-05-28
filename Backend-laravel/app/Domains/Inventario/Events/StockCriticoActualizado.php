<?php

namespace App\Domains\Inventario\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockCriticoActualizado implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $empresaId,
        public readonly int $productoId,
        public readonly int $bodegaId,
        public readonly float $stockActual,
        public readonly float $stockMinimo
    ) {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('inventario.empresa.' . $this->empresaId);
    }

    public function broadcastAs(): string
    {
        return 'inventario.stock.critico';
    }

    public function broadcastQueue(): string
    {
        return 'inventario';
    }

    public function broadcastWith(): array
    {
        return [
            'empresa_id' => $this->empresaId,
            'producto_id' => $this->productoId,
            'bodega_id' => $this->bodegaId,
            'stock_actual' => $this->stockActual,
            'stock_minimo' => $this->stockMinimo,
        ];
    }
}