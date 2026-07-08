<?php

namespace App\Domains\Rrhh\Controllers;

use App\Domains\Core\Models\User;
use App\Domains\Rrhh\Services\LibroRemuneracionesService;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Libro de Remuneraciones Digital — DFL-1 Art. 62 Código del Trabajo. */
class LibroRemuneracionesController extends Controller
{
    public function __construct(
        private readonly LibroRemuneracionesService $servicio,
    ) {}

    /** Retorna los datos del libro de remuneraciones en formato JSON. */
    public function simular(Request $request, int $anio, int $mes): JsonResponse
    {
        if (! $this->periodoValido($anio, $mes)) {
            return response()->json(['message' => 'Período inválido.'], 422);
        }

        /** @var User $usuario */
        $usuario = $request->user();

        $datos = $this->servicio->generar($usuario->empresa_activa_id, $anio, $mes);

        return response()->json($datos);
    }

    /** Descarga el libro en Excel (HTML-as-XLS) o PDF; sin formato especificado usa Excel por defecto. */
    public function descargar(Request $request, int $anio, int $mes): Response
    {
        if (! $this->periodoValido($anio, $mes)) {
            return response('Período inválido.', 422);
        }

        /** @var User $usuario */
        $usuario = $request->user();

        $datos   = $this->servicio->generar($usuario->empresa_activa_id, $anio, $mes);
        $formato = strtolower((string) $request->query('formato', 'excel'));

        if ($formato === 'pdf') {
            return $this->descargarPdf($datos, $anio, $mes);
        }

        return $this->descargarExcel($datos, $anio, $mes);
    }

    /**
     * Genera y descarga el libro en PDF (landscape A4).
     *
     * @param  array<string, mixed>  $datos
     */
    private function descargarPdf(array $datos, int $anio, int $mes): Response
    {
        $pdf = Pdf::loadView('pdf.libro_remuneraciones', [
            'datos' => $datos,
            'anio'  => $anio,
            'mes'   => $mes,
        ])->setPaper('a4', 'landscape');

        $nombre = sprintf('libro_remuneraciones_%04d_%02d.pdf', $anio, $mes);

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$nombre}\"",
        ]);
    }

    /**
     * Genera y descarga el libro como HTML-as-XLS (abre en Excel sin paquetes extras).
     *
     * @param  array<string, mixed>  $datos
     */
    private function descargarExcel(array $datos, int $anio, int $mes): Response
    {
        $html   = view('pdf.libro_remuneraciones_excel', [
            'datos' => $datos,
            'anio'  => $anio,
            'mes'   => $mes,
        ])->render();

        $nombre = sprintf('libro_remuneraciones_%04d_%02d.xls', $anio, $mes);

        return response($html, 200, [
            'Content-Type'        => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nombre}\"",
        ]);
    }

    private function periodoValido(int $anio, int $mes): bool
    {
        return $anio >= 2000 && $anio <= 2100 && $mes >= 1 && $mes <= 12;
    }
}
