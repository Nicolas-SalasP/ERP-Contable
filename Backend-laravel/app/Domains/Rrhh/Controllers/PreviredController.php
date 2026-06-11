<?php

namespace App\Domains\Rrhh\Controllers;

use App\Domains\Rrhh\Services\Previred\PreviredService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * R6 — Descarga del archivo previsional mensual para Previred.
 *
 * El archivo CSV se genera al vuelo desde las liquidaciones EMITIDAS del
 * período. No se persiste en disco: se descarga directo desde la BD.
 */
class PreviredController extends Controller
{
    public function __construct(
        private readonly PreviredService $service,
    ) {}

    /**
     * GET /api/rrhh/previred/{anio}/{mes}/archivo
     * Genera y descarga el archivo previsional del período.
     */
    public function archivo(Request $request, int $anio, int $mes): StreamedResponse|Response
    {
        if ($mes < 1 || $mes > 12 || $anio < 2000 || $anio > 2100) {
            return response('Período inválido.', 422);
        }

        $contenido = $this->service->generarArchivo(
            $request->user()->empresa_id,
            $anio,
            $mes
        );

        $nombreArchivo = sprintf('previred_%04d_%02d.txt', $anio, $mes);

        return response()->streamDownload(function () use ($contenido) {
            echo $contenido;
        }, $nombreArchivo, [
            'Content-Type'        => 'text/plain; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nombreArchivo}\"",
        ]);
    }

    /**
     * GET /api/rrhh/previred/{anio}/{mes}/preview
     * Retorna el contenido del archivo como JSON para previsualización en el SPA.
     *
     * El formato de 105 campos NO tiene encabezado; cada línea es una fila de datos.
     * El JSON devuelto incluye un array posicional de 105 elementos por trabajador.
     */
    public function preview(Request $request, int $anio, int $mes): Response|\Illuminate\Http\JsonResponse
    {
        if ($mes < 1 || $mes > 12 || $anio < 2000 || $anio > 2100) {
            return response()->json(['success' => false, 'message' => 'Período inválido.'], 422);
        }

        $contenido = $this->service->generarArchivo(
            $request->user()->empresa_id,
            $anio,
            $mes
        );

        // El archivo no tiene encabezado: cada línea es directamente una fila de datos
        $lineas = array_values(array_filter(explode("\r\n", $contenido)));
        $filas  = array_map(fn($l) => explode(';', $l), $lineas);

        return response()->json([
            'success'             => true,
            'periodo'             => sprintf('%02d/%d', $mes, $anio),
            'total_trabajadores'  => count($filas),
            'total_campos'        => 105,
            'filas'               => $filas,
        ]);
    }
}
