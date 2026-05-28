<?php

namespace App\Domains\Inventario\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class RegistrarEventoInventarioListener implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    public int $timeout = 60;

    /**
     * Fuerza que todos los eventos de dominio de Inventario
     * se procesen en la cola dedicada "inventario",
     * evitando que caigan en la cola default.
     */
    public function viaQueue(): string
    {
        return 'inventario';
    }

    public function handle(object $event): void
    {
        Log::info('Evento de dominio de Inventario procesado.', [
            'evento' => $event::class,
            'empresa_id' => $this->leerPropiedad($event, 'empresaId'),
            'producto_id' => $this->leerPropiedad($event, 'productoId'),
            'bodega_id' => $this->leerPropiedad($event, 'bodegaId'),
            'lote_id' => $this->leerPropiedad($event, 'loteId'),
            'toma_fisica_id' => $this->leerPropiedad($event, 'tomaFisicaId'),
            'criticidad' => $this->leerPropiedad($event, 'criticidad'),
            'tipo' => $this->leerPropiedad($event, 'tipo'),
        ]);
    }

    public function failed(object $event, Throwable $exception): void
    {
        Log::error('Falló el procesamiento de evento de dominio de Inventario.', [
            'evento' => $event::class,
            'error' => $exception->getMessage(),
            'empresa_id' => $this->leerPropiedad($event, 'empresaId'),
            'producto_id' => $this->leerPropiedad($event, 'productoId'),
            'lote_id' => $this->leerPropiedad($event, 'loteId'),
        ]);
    }

    private function leerPropiedad(object $event, string $propiedad): mixed
    {
        return property_exists($event, $propiedad)
            ? $event->{$propiedad}
            : null;
    }
}