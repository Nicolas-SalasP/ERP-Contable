<?php

namespace App\Domains\Comercial\Notifications;

use App\Domains\Comercial\Models\Cotizacion;
use App\Domains\Core\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Deliberadamente NO ShouldQueue: se despacha de forma sincrona dentro de
 * EnviarCotizacionCorreoJob (que si corre en cola), mismo patron que
 * ReporteContableGeneradoNotification -- garantiza que el PDF temporal todavia
 * exista cuando se arma el correo, antes de que el job lo borre en su finally.
 */
class CotizacionEnviadaNotification extends Notification
{
    /** @param array<int, array{ruta: string, nombre: string, mime: string}> $adjuntosExtra */
    public function __construct(
        public readonly Cotizacion $cotizacion,
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
        $cliente = $this->cotizacion->cliente;

        $mensaje = (new MailMessage)
            ->subject("Cotización {$this->cotizacion->numero_cotizacion} enviada")
            ->line("Se generó y envió la cotización {$this->cotizacion->numero_cotizacion}.")
            ->line('Cliente: '.($cliente->razon_social ?? $this->cotizacion->nombre_cliente))
            ->line('Monto total: $'.number_format((float) $this->cotizacion->monto_total, 0, ',', '.'))
            ->line('Enviado por: '.($this->usuario->nombre ?? 'Sistema'))
            ->line('Fecha de envío: '.now()->format('d-m-Y H:i'))
            ->attach(Storage::disk('local')->path($this->rutaPdf), [
                'as' => "Cotizacion-{$this->cotizacion->numero_cotizacion}.pdf",
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
