<?php

namespace App\Domains\Contabilidad\Notifications;

use App\Domains\Contabilidad\Models\ReporteContableSolicitado;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Deliberadamente NO ShouldQueue: se despacha de forma sincrona dentro de
 * GenerarReporteContableJob (que si corre en cola), para garantizar que el
 * archivo temporal del adjunto todavia exista cuando se envia el correo antes
 * de que el job lo borre en su bloque finally.
 */
class ReporteContableGeneradoNotification extends Notification
{
    public function __construct(
        public readonly ReporteContableSolicitado $solicitud,
        public readonly string $rutaArchivo
    ) {
    }

    /** @return array<int, string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $tipo = $this->solicitud->tipo_reporte === 'libro_mayor' ? 'Libro Mayor' : 'Libro Diario';
        $desde = $this->solicitud->fecha_inicio->format('d-m-Y');
        $hasta = $this->solicitud->fecha_fin->format('d-m-Y');

        return (new MailMessage())
            ->subject("Tu reporte de {$tipo} ({$desde} al {$hasta}) esta listo")
            ->line("Adjuntamos el {$tipo} solicitado para el periodo {$desde} al {$hasta}.")
            ->line('Este correo fue generado automaticamente por Tenri ERP Cloud.')
            ->attach(Storage::disk('local')->path($this->rutaArchivo), [
                'as' => "{$tipo}-{$desde}-al-{$hasta}.xlsx",
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
    }
}
