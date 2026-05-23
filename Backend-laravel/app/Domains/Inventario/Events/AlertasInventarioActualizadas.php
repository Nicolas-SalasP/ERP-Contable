<?php

namespace App\Domains\Inventario\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AlertasInventarioActualizadas implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $empresaId,
        public readonly int $total,
        public readonly int $criticas,
        public readonly string $calculadoEn
    ) {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('inventario.empresa.' . $this->empresaId);
    }

    public function broadcastAs(): string
    {
        return 'inventario.alertas.actualizadas';
    }

    public function broadcastQueue(): string
    {
        return 'inventario';
    }

    public function broadcastWith(): array
    {
        return [
            'empresa_id' => $this->empresaId,
            'total' => $this->total,
            'criticas' => $this->criticas,
            'calculado_en' => $this->calculadoEn,
        ];
    }
}