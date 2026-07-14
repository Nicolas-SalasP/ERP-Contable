<?php

namespace App\Domains\Contabilidad\Jobs;

use App\Domains\Contabilidad\Models\ReporteContableSolicitado;
use App\Domains\Contabilidad\Notifications\ReporteContableGeneradoNotification;
use App\Domains\Contabilidad\Services\ReporteContableService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

/**
 * Genera en background el Excel de Libro Diario/Mayor para rangos de fecha largos (hasta 10 anios,
 * ver ReporteController::MAX_DIAS_EXPORTACION) que serian pesados para responder de forma sincrona,
 * y lo envia por correo. Corre en la cola 'reportes' -- requiere `php artisan queue:work` (o
 * `queue:work --queue=reportes`) activo, igual que los Jobs de Sii.
 */
class GenerarReporteContableJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public int $timeout = 600;

    public function __construct(private readonly int $solicitudId)
    {
        $this->onQueue('reportes');
    }

    public function handle(ReporteContableService $service): void
    {
        $solicitud = ReporteContableSolicitado::withoutGlobalScopes()->find($this->solicitudId);
        if ($solicitud === null) {
            Log::warning('GenerarReporteContableJob: solicitud no encontrada.', [
                'solicitud_id' => $this->solicitudId,
            ]);
            return;
        }

        $solicitud->update(['estado' => ReporteContableSolicitado::ESTADO_PROCESANDO]);

        $rutaTemporal = null;

        try {
            $fechaInicio = $solicitud->fecha_inicio->format('Y-m-d');
            $fechaFin = $solicitud->fecha_fin->format('Y-m-d');

            if ($solicitud->tipo_reporte === 'libro_mayor') {
                $reporte = $service->generarLibroMayor(
                    $solicitud->empresa_id,
                    (string) $solicitud->cuenta_contable,
                    $fechaInicio,
                    $fechaFin,
                    $solicitud->filtro
                );
                $filas = $this->filasLibroMayor($reporte);
                $encabezados = ['Fecha', 'Comprobante', 'Glosa', 'Estado', 'Debe', 'Haber', 'Saldo'];
                $nombreHoja = 'Libro Mayor';
            } else {
                $reporte = $service->generarLibroDiario($solicitud->empresa_id, $fechaInicio, $fechaFin, $solicitud->filtro);
                $filas = $this->filasLibroDiario($reporte);
                $encabezados = ['Fecha', 'Comprobante', 'Glosa', 'Estado', 'Cuenta', 'Nombre Cuenta', 'Debe', 'Haber'];
                $nombreHoja = 'Libro Diario';
            }

            $rutaTemporal = $this->construirExcel($nombreHoja, $encabezados, $filas, $solicitud->id);

            Notification::route('mail', $solicitud->email_destino)
                ->notify(new ReporteContableGeneradoNotification($solicitud, $rutaTemporal));

            $solicitud->update([
                'estado' => ReporteContableSolicitado::ESTADO_ENVIADO,
                'enviado_at' => now(),
                'error_mensaje' => null,
            ]);
        } catch (Throwable $e) {
            Log::error('GenerarReporteContableJob fallo.', [
                'solicitud_id' => $solicitud->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            $solicitud->update([
                'estado' => ReporteContableSolicitado::ESTADO_ERROR,
                'error_mensaje' => $e->getMessage(),
            ]);
        } finally {
            if ($rutaTemporal !== null && Storage::disk('local')->exists($rutaTemporal)) {
                Storage::disk('local')->delete($rutaTemporal);
            }
        }
    }

    /** @return array<int, array<int, mixed>> */
    private function filasLibroDiario($asientos): array
    {
        $filas = [];
        foreach ($asientos as $asiento) {
            foreach ($asiento->detalles as $detalle) {
                $filas[] = [
                    $asiento->fecha->format('Y-m-d'),
                    $asiento->numero_comprobante,
                    $asiento->glosa,
                    $asiento->estado,
                    $detalle->cuenta_contable,
                    $detalle->cuenta->nombre ?? '',
                    (float) $detalle->debe,
                    (float) $detalle->haber,
                ];
            }
        }
        return $filas;
    }

    /** @return array<int, array<int, mixed>> */
    private function filasLibroMayor(array $reporte): array
    {
        $filas = [];
        foreach ($reporte['movimientos'] as $mov) {
            $filas[] = [
                $mov['fecha'],
                $mov['comprobante'],
                $mov['glosa'],
                $mov['estado'],
                (float) $mov['debe'],
                (float) $mov['haber'],
                (float) $mov['saldo'],
            ];
        }
        return $filas;
    }

    /**
     * @param array<int, string> $encabezados
     * @param array<int, array<int, mixed>> $filas
     */
    private function construirExcel(string $nombreHoja, array $encabezados, array $filas, int $solicitudId): string
    {
        $spreadsheet = new Spreadsheet();
        $hoja = $spreadsheet->getActiveSheet();
        $hoja->setTitle($nombreHoja);

        $hoja->fromArray($encabezados, null, 'A1');
        $hoja->fromArray($filas, null, 'A2');

        foreach (range('A', chr(ord('A') + count($encabezados) - 1)) as $columna) {
            $hoja->getColumnDimension($columna)->setAutoSize(true);
        }

        $nombreArchivo = "reportes-contables/reporte-{$solicitudId}.xlsx";
        $rutaAbsoluta = Storage::disk('local')->path($nombreArchivo);

        Storage::disk('local')->makeDirectory('reportes-contables');
        (new Xlsx($spreadsheet))->save($rutaAbsoluta);

        return $nombreArchivo;
    }
}
