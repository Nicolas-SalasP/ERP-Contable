<?php

namespace App\Domains\Alertas\Notifications;

use App\Domains\Alertas\Models\Alerta;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Notificacion generica de correo para cualquier tipo de Alerta; el subject varia segun nivel. */
class AlertaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Alerta $alerta
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->resolverSubject())
            ->line($this->alerta->mensaje)
            ->line('Tipo de alerta: '.$this->alerta->tipo)
            ->line('Revisa la bandeja de alertas en Tenri ERP para marcarla como resuelta.');
    }

    private function resolverSubject(): string
    {
        return match ($this->alerta->nivel) {
            Alerta::NIVEL_CRITICO => '[CRITICO] Alerta Tenri ERP: '.$this->alerta->tipo,
            Alerta::NIVEL_ADVERTENCIA => '[Atencion] Alerta Tenri ERP: '.$this->alerta->tipo,
            default => 'Aviso Tenri ERP: '.$this->alerta->tipo,
        };
    }
}
