<?php

namespace App\Domains\Comercial\Notifications;

use App\Domains\Comercial\Models\Factura;
use App\Domains\Core\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

/** Deliberadamente NO ShouldQueue -- ver CotizacionEnviadaNotification para el motivo. */
class FacturaEmitidaNotification extends Notification
{
    /** @param array<int, array{ruta: string, nombre: string, mime: string}> $adjuntosExtra */
    public function __construct(
        public readonly Factura $factura,
        public readonly string $rutaPdf,
        public readonly ?User $usuario,
        public readonly array $adjuntosExtra = [],
    ) {}

    /** @return array<int, string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $cliente = $this->factura->cliente;

        $mensaje = (new MailMessage)
            ->subject("Factura de venta {$this->factura->numero_factura} generada")
            ->line("Se generó la factura de venta {$this->factura->numero_factura}.")
            ->line('Cliente: '.($cliente->razon_social ?? 'Sin cliente asociado'))
            ->line('Monto total: $'.number_format((float) $this->factura->monto_bruto, 0, ',', '.'))
            ->line('Generada por: '.($this->usuario->nombre ?? 'Sistema'))
            ->line('Fecha de emisión: '.$this->factura->fecha_emision->format('d-m-Y'))
            ->attach(Storage::disk('local')->path($this->rutaPdf), [
                'as' => "Factura-{$this->factura->numero_factura}.pdf",
                'mime' => 'application/pdf',
            ])
            ->line('Este correo fue generado automáticamente por Tenri ERP Cloud.');

        foreach ($this->adjuntosExtra as $adjunto) {
            $mensaje->attach($adjunto['ruta'], [
                'as' => $adjunto['nombre'],
                'mime' => $adjunto['mime'],
            ]);
        }

        return $mensaje;
    }
}
