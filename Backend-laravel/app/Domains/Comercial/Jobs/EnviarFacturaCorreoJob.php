<?php

namespace App\Domains\Comercial\Jobs;

use App\Domains\Comercial\Models\DocumentoAdjunto;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Notifications\FacturaEmitidaNotification;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
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

/** Mismo diseño y motivo que EnviarCotizacionCorreoJob -- ver ese archivo para el detalle. */
class EnviarFacturaCorreoJob implements ShouldQueue
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
        private readonly int $facturaId,
        private readonly ?int $usuarioId,
    ) {}

    public function handle(): void
    {
        $factura = Factura::withoutGlobalScopes()
            ->with(['cliente'])
            ->where('empresa_id', $this->empresaId)
            ->where('tipo', 'VENTA')
            ->find($this->facturaId);

        if ($factura === null) {
            Log::warning('EnviarFacturaCorreoJob: factura no encontrada.', [
                'empresa_id' => $this->empresaId,
                'factura_id' => $this->facturaId,
            ]);

            return;
        }

        $empresa = Empresa::withoutGlobalScopes()->find($this->empresaId);
        if ($empresa === null || empty($empresa->email)) {
            Log::warning('EnviarFacturaCorreoJob: empresa sin email de contacto configurado, no se puede notificar.', [
                'empresa_id' => $this->empresaId,
            ]);

            return;
        }

        $usuario = $this->usuarioId
            ? User::withoutGlobalScopes()->find($this->usuarioId)
            : null;

        $rutaPdf = null;

        try {
            $rutaPdf = $this->generarPdf($factura, $empresa);
            $adjuntosExtra = $this->adjuntosDeLaFactura($factura);

            Notification::route('mail', $empresa->email)
                ->notify(new FacturaEmitidaNotification($factura, $rutaPdf, $usuario, $adjuntosExtra));

            // Envio al cliente: apagado hasta activar el flag explicitamente.
            if (config('notificaciones.cliente_habilitado') === true) {
                $emailCliente = $factura->cliente->contacto_email ?? $factura->cliente->email ?? null;
                if ($emailCliente) {
                    Notification::route('mail', $emailCliente)
                        ->notify(new FacturaEmitidaNotification($factura, $rutaPdf, $usuario, $adjuntosExtra));
                }
            }

            $factura->update([
                'notificada_at' => now(),
                'usuario_notificacion_id' => $this->usuarioId,
            ]);
        } catch (Throwable $e) {
            Log::error('EnviarFacturaCorreoJob fallo.', [
                'empresa_id' => $this->empresaId,
                'factura_id' => $this->facturaId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        } finally {
            if ($rutaPdf !== null && Storage::disk('local')->exists($rutaPdf)) {
                Storage::disk('local')->delete($rutaPdf);
            }
        }
    }

    private function generarPdf(Factura $factura, Empresa $empresa): string
    {
        $pdf = Pdf::loadView('pdf.factura', compact('factura', 'empresa'));

        $ruta = "facturas/pdfs-envio/factura-{$factura->id}-".uniqid().'.pdf';
        Storage::disk('local')->makeDirectory('facturas/pdfs-envio');
        Storage::disk('local')->put($ruta, $pdf->output());

        return $ruta;
    }

    /** @return array<int, array{ruta: string, nombre: string, mime: string}> */
    private function adjuntosDeLaFactura(Factura $factura): array
    {
        $adjuntos = DocumentoAdjunto::withoutGlobalScopes()
            ->where('empresa_id', $this->empresaId)
            ->where('factura_id', $factura->id)
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
