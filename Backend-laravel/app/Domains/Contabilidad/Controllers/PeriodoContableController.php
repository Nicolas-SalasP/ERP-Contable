<?php

namespace App\Domains\Contabilidad\Controllers;

use App\Domains\Contabilidad\Services\PeriodoContableService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PeriodoContableController extends Controller
{
    public function __construct(private readonly PeriodoContableService $service)
    {
    }

    /** Lista los periodos cerrados de la empresa (para la UI de candados). */
    public function index(Request $request): JsonResponse
    {
        $anio = $request->integer('anio') ?: null;

        return response()->json([
            'success' => true,
            'data' => $this->service->listarCerrados($request->user()->empresa_id, $anio),
        ]);
    }

    public function cerrar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'anio' => 'required|integer|min:2000|max:2100',
            'mes' => 'required|integer|min:1|max:12',
            'motivo' => 'nullable|string|max:500',
        ]);

        $periodo = $this->service->cerrar(
            $request->user()->empresa_id,
            $datos['anio'],
            $datos['mes'],
            $request->user(),
            $datos['motivo'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Periodo cerrado correctamente.',
            'data' => $periodo,
        ]);
    }

    public function reabrir(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'anio' => 'required|integer|min:2000|max:2100',
            'mes' => 'required|integer|min:1|max:12',
            'motivo' => 'required|string|max:500',
        ]);

        $periodo = $this->service->reabrir(
            $request->user()->empresa_id,
            $datos['anio'],
            $datos['mes'],
            $request->user(),
            $datos['motivo'],
        );

        return response()->json([
            'success' => true,
            'message' => 'Periodo reabierto correctamente.',
            'data' => $periodo,
        ]);
    }
}
