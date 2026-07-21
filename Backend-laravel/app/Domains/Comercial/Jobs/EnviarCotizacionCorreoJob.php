<?php

namespace App\Domains\Comercial\Jobs;

use App\Domains\Comercial\Models\Cotizacion;
use App\Domains\Comercial\Models\DocumentoAdjunto;
use App\Domains\Comercial\Notifications\CotizacionEnviadaNotification;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Tesoreria\Models\CuentaBancariaEmpresa;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Corre en cola porque genera un PDF (dompdf) y hace una llamada SMTP -- no debe
 * bloquear la respuesta HTTP del boton "Enviar cotizacion". Nunca confia en
 * auth()/el usuario autenticado (no existe en contexto de cola): empresa_id y
 * usuario_id vienen explicitos por constructor, y toda consulta re-filtra por
 * empresa_id a mano (mismo patron que GenerarReporteContableJob).
 *
 * Envio al cliente (contacto_email real de la cotizacion, nunca un email del
 * request) queda armado pero apagado por config('notificaciones.cliente_habilitado').
 */
class EnviarCotizacionCorreoJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public int $timeout = 120;

    public function __construct(
        private readonly int $empresaId,
        private readonly int $cotizacionId,
        private readonly ?int $usuarioId,
    ) {}

    public function handle(): void
    {
        $cotizacion = Cotizacion::withoutGlobalScopes()
            ->with(['cliente', 'detalles'])
            ->where('empresa_id', $this->empresaId)
            ->find($this->cotizacionId);

        if ($cotizacion === null) {
            Log::warning('EnviarCotizacionCorreoJob: cotizacion no encontrada.', [
                'empresa_id' => $this->empresaId,
                'cotizacion_id' => $this->cotizacionId,
            ]);

            return;
        }

        $empresa = Empresa::withoutGlobalScopes()->find($this->empresaId);
        if ($empresa === null || empty($empresa->email)) {
            Log::warning('EnviarCotizacionCorreoJob: empresa sin email de contacto configurado, no se puede notificar.', [
                'empresa_id' => $this->empresaId,
            ]);

            return;
        }

        $usuario = $this->usuarioId
            ? User::withoutGlobalScopes()->find($this->usuarioId)
            : null;

        $rutaPdf = null;

        try {
            $cuentasBancarias = CuentaBancariaEmpresa::withoutGlobalScopes()
                ->where('empresa_id', $this->empresaId)
                ->get();

            $rutaPdf = $this->generarPdf($cotizacion, $empresa, $cuentasBancarias);

            $adjuntosExtra = $this->adjuntosDeLaCotizacion($cotizacion);

            Notification::route('mail', $empresa->email)
                ->notify(new CotizacionEnviadaNotification($cotizacion, $rutaPdf, $usuario, $adjuntosExtra));

            // Envio al cliente: apagado hasta activar el flag explicitamente.
            if (config('notificaciones.cliente_habilitado') === true) {
                $emailCliente = $cotizacion->cliente->contacto_email ?? $cotizacion->cliente->email ?? null;
                if ($emailCliente) {
                    Notification::route('mail', $emailCliente)
                        ->notify(new CotizacionEnviadaNotification($cotizacion, $rutaPdf, $usuario, $adjuntosExtra));
                }
            }

            $cotizacion->update([
                'enviada_at' => now(),
                'usuario_envio_id' => $this->usuarioId,
            ]);
        } catch (Throwable $e) {
            Log::error('EnviarCotizacionCorreoJob fallo.', [
                'empresa_id' => $this->empresaId,
                'cotizacion_id' => $this->cotizacionId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        } finally {
            if ($rutaPdf !== null && Storage::disk('local')->exists($rutaPdf)) {
                Storage::disk('local')->delete($rutaPdf);
            }
        }
    }

    private function generarPdf(Cotizacion $cotizacion, Empresa $empresa, $cuentasBancarias): string
    {
        $pdf = Pdf::loadView('pdf.cotizacion', compact('cotizacion', 'empresa', 'cuentasBancarias'));

        $ruta = "cotizaciones/pdfs-envio/cotizacion-{$cotizacion->id}-".uniqid().'.pdf';
        Storage::disk('local')->makeDirectory('cotizaciones/pdfs-envio');
        Storage::disk('local')->put($ruta, $pdf->output());

        return $ruta;
    }

    /** @return array<int, array{ruta: string, nombre: string, mime: string}> */
    private function adjuntosDeLaCotizacion(Cotizacion $cotizacion): array
    {
        $adjuntos = DocumentoAdjunto::withoutGlobalScopes()
            ->where('empresa_id', $this->empresaId)
            ->where('cotizacion_id', $cotizacion->id)
            ->get();

        $resultado = [];
        foreach ($adjuntos as $adjunto) {
            if (! Storage::disk('local')->exists($adjunto->ruta)) {
                continue;
            }
            $resultado[] = [
                'ruta' => Storage::disk('local')->path($adjunto->ruta),
                'nombre' => $adjunto->nombre_original,
                'mime' => $adjunto->mime_type ?? 'application/octet-stream',
            ];
        }

        return $resultado;
    }
}
