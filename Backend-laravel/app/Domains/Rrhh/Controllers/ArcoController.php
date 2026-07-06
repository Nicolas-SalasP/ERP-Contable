<?php

namespace App\Domains\Rrhh\Controllers;

use App\Domains\Rrhh\Services\ArcoService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Fase 5 — Ley 21.719: derechos ARCO+ del titular empleado, operados por el
 * DPO/administrador.
 */
class ArcoController extends Controller
{
    public function __construct(private readonly ArcoService $service)
    {
    }

    /**
     * Derecho de acceso/portabilidad: exporta los datos personales del titular.
     */
    public function exportar(Request $request, int $id): JsonResponse
    {
        $data = $this->service->exportarEmpleado($request->user()->empresa_activa_id, $id);

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Derecho de oposición/bloqueo: bloquea el tratamiento de los datos.
     */
    public function bloquear(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate([
            'motivo' => 'nullable|string',
        ]);

        $empleado = $this->service->bloquearEmpleado(
            $request->user()->empresa_activa_id,
            $id,
            $datos['motivo'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Tratamiento bloqueado correctamente.',
            'data' => $empleado,
        ]);
    }

    /**
     * Derecho de supresión/cancelación: anonimiza (total o parcial según retención).
     */
    public function suprimir(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate([
            'motivo' => 'nullable|string',
        ]);

        $resultado = $this->service->suprimirEmpleado(
            $request->user()->empresa_activa_id,
            $id,
            $datos['motivo'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => $resultado['mensaje'],
            'data' => ['modo' => $resultado['modo']],
        ]);
    }
}
