<?php

namespace App\Domains\Sii\Notifications;

use App\Domains\Sii\Models\SiiCertificadoEmpresa;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CertificadoVencimientoNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly SiiCertificadoEmpresa $cert,
        public readonly string $nivel
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $dias    = $this->cert->diasParaVencer();
        $subject = $this->resolverSubject($dias);

        return (new MailMessage())
            ->subject($subject)
            ->markdown('sii::emails.certificado-vencimiento', [
                'cert'  => $this->cert,
                'nivel' => $this->nivel,
                'dias'  => $dias,
            ]);
    }

    private function resolverSubject(int $dias): string
    {
        return match ($this->nivel) {
            SiiCertificadoEmpresa::ALERTA_VENCIDO    =>
                '[URGENTE] Tu certificado digital SII esta vencido',

            SiiCertificadoEmpresa::ALERTA_CRITICA_T1 =>
                '[CRITICO] Tu certificado SII vence MANANA',

            SiiCertificadoEmpresa::ALERTA_CRITICA_T7 =>
                "[Atencion] Tu certificado SII vence en {$dias} dias",

            default =>
                "Aviso: Tu certificado SII vence en {$dias} dias",
        };
    }
}
