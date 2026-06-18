<?php

namespace App\Domains\Rrhh\Controllers;

use App\Domains\Rrhh\Exceptions\RrhhException;
use App\Domains\Rrhh\Services\Emrcl\GenerarEmrclService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmrclController extends Controller
{
    public function __construct(
        private readonly GenerarEmrclService $servicio,
    ) {}

    /**
     * GET /api/rrhh/emrcl/{anio}/{mes}
     * Genera el reporte EMRCL (Encuesta Mensual INE de Remuneraciones y Costos Laborales)
     * para el período indicado.
     */
    public function generar(Request $request, int $anio, int $mes): JsonResponse
    {
        $anio = (int) $anio;
        $mes  = (int) $mes;

        if ($anio < 2000 || $anio > 2100) {
            return response()->json(['mensaje' => 'Año fuera de rango válido.'], 422);
        }
        if ($mes < 1 || $mes > 12) {
            return response()->json(['mensaje' => 'Mes debe estar entre 1 y 12.'], 422);
        }

        try {
            $reporte = $this->servicio->generar(
                $request->user()->empresa_id,
                $anio,
                $mes,
            );

            return response()->json($reporte);
        } catch (RrhhException $e) {
            return response()->json(['mensaje' => $e->getMessage()], 422);
        }
    }
}
