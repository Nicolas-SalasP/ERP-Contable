<?php

namespace App\Domains\Sii\Jobs;

use App\Domains\Sii\Models\SiiCertificadoEmpresa;
use App\Domains\Sii\Models\SiiCertificadoNotificacion;
use App\Domains\Sii\Notifications\CertificadoVencimientoNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Recorre los certificados activos y dispara alertas de vencimiento segun
 * la matriz de niveles definida en SiiCertificadoEmpresa.
 *
 * Frecuencia por nivel:
 *   - BAJA_T60, MEDIA_T30, ALTA_T15  => ONE-SHOT (una sola vez por nivel)
 *   - CRITICA_T7, CRITICA_T1, VENCIDO => DIARIO (uno por dia mientras dure)
 */
class MonitorearVencimientoCertificadosJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    /** Niveles con frecuencia diaria. */
    private const NIVELES_DIARIOS = [
        SiiCertificadoEmpresa::ALERTA_CRITICA_T7,
        SiiCertificadoEmpresa::ALERTA_CRITICA_T1,
        SiiCertificadoEmpresa::ALERTA_VENCIDO,
    ];

    public function handle(): void
    {
        $contador = ['enviadas' => 0, 'fallidas' => 0, 'skipped' => 0];

        $certificados = SiiCertificadoEmpresa::query()
            ->activos()
            ->with('empresa')
            ->get();

        foreach ($certificados as $cert) {
            $nivel = $cert->nivelAlerta();

            if ($nivel === SiiCertificadoEmpresa::ALERTA_SIN_ALERTA) {
                $contador['skipped']++;
                continue;
            }

            $destinatario = $this->resolverDestinatario($cert);
            if ($destinatario === null) {
                Log::channel('sii')->warning('Cert sin email destinatario; skip envio de alerta.', [
                    'certificado_id' => $cert->id,
                    'empresa_id'     => $cert->empresa_id,
                    'nivel'          => $nivel,
                ]);
                $contador['skipped']++;
                continue;
            }

            if (! $this->debeEnviar($cert, $nivel)) {
                $contador['skipped']++;
                continue;
            }

            $resultado = $this->enviar($cert, $nivel, $destinatario);
            $contador[$resultado]++;
        }

        Log::channel('sii')->info('MonitorearVencimientoCertificadosJob finalizado.', [
            'total_certificados' => $certificados->count(),
            'enviadas'           => $contador['enviadas'],
            'fallidas'           => $contador['fallidas'],
            'skipped'            => $contador['skipped'],
        ]);
    }

    private function resolverDestinatario(SiiCertificadoEmpresa $cert): ?string
    {
        $empresa = $cert->empresa;
        if ($empresa === null) {
            return null;
        }

        $candidatos = [
            $empresa->email_intercambio_sii ?? null,
            $empresa->email ?? null,
        ];

        foreach ($candidatos as $email) {
            if (is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        return null;
    }

    private function debeEnviar(SiiCertificadoEmpresa $cert, string $nivel): bool
    {
        if (in_array($nivel, self::NIVELES_DIARIOS, true)) {
            return ! $cert->haEnviadoNivelHoy($nivel);
        }

        // One-shot (BAJA_T60, MEDIA_T30, ALTA_T15).
        return ! $cert->haEnviadoNivel($nivel);
    }

    /**
     * @return 'enviadas'|'fallidas'
     */
    private function enviar(SiiCertificadoEmpresa $cert, string $nivel, string $destinatario): string
    {
        $dias = $cert->diasParaVencer();

        try {
            Notification::route('mail', $destinatario)
                ->notify(new CertificadoVencimientoNotification($cert, $nivel));

            SiiCertificadoNotificacion::create([
                'certificado_id'    => $cert->id,
                'nivel'             => $nivel,
                'enviada_a'         => $destinatario,
                'dias_para_vencer'  => $dias,
                'estado_envio'      => SiiCertificadoNotificacion::ESTADO_ENVIADA,
                'enviada_at'        => now(),
            ]);

            return 'enviadas';
        } catch (Throwable $e) {
            Log::channel('sii')->error('Fallo envio de alerta de vencimiento.', [
                'certificado_id' => $cert->id,
                'nivel'          => $nivel,
                'destinatario'   => $destinatario,
                'exception'      => $e::class,
                'message'        => $e->getMessage(),
            ]);

            SiiCertificadoNotificacion::create([
                'certificado_id'    => $cert->id,
                'nivel'             => $nivel,
                'enviada_a'         => $destinatario,
                'dias_para_vencer'  => $dias,
                'estado_envio'      => SiiCertificadoNotificacion::ESTADO_FALLIDA,
                'error_mensaje'     => $e->getMessage(),
                'enviada_at'        => now(),
            ]);

            return 'fallidas';
        }
    }
}
