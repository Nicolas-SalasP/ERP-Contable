<?php

namespace App\Domains\Rrhh\Controllers;

use App\Domains\Rrhh\Services\Provisiones\VacacionesService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VacacionesController extends Controller
{
    public function __construct(
        private readonly VacacionesService $service,
    ) {
    }

    public function saldo(Request $request, int $empleadoId): JsonResponse
    {
        $saldo = $this->service->saldoActual($request->user()->empresa_id, $empleadoId);

        return response()->json(['success' => true, 'data' => $saldo]);
    }

    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'empleado_id' => 'nullable|integer',
            'estado' => 'nullable|in:PENDIENTE,APROBADA,RECHAZADA,ANULADA',
        ]);

        $solicitudes = $this->service->listarSolicitudes($request->user()->empresa_id, $datos);

        return response()->json(['success' => true, 'data' => $solicitudes]);
    }

    public function solicitar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'empleado_id' => 'required|integer',
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date',
            'observacion' => 'nullable|string|max:500',
        ]);

        $solicitud = $this->service->solicitar(
            $request->user()->empresa_id,
            $datos['empleado_id'],
            $datos['fecha_desde'],
            $datos['fecha_hasta'],
            $request->user()->id,
            $datos['observacion'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Solicitud de vacaciones registrada.',
            'data' => $solicitud,
        ], 201);
    }

    public function aprobar(Request $request, int $id): JsonResponse
    {
        $solicitud = $this->service->aprobar($request->user()->empresa_id, $id, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Solicitud de vacaciones aprobada.',
            'data' => $solicitud,
        ]);
    }

    public function rechazar(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate(['motivo' => 'required|string|min:5|max:500']);

        $solicitud = $this->service->rechazar($request->user()->empresa_id, $id, $request->user()->id, $datos['motivo']);

        return response()->json([
            'success' => true,
            'message' => 'Solicitud de vacaciones rechazada.',
            'data' => $solicitud,
        ]);
    }

    public function anular(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate(['motivo' => 'required|string|min:5|max:500']);

        $solicitud = $this->service->anular($request->user()->empresa_id, $id, $request->user()->id, $datos['motivo']);

        return response()->json([
            'success' => true,
            'message' => 'Solicitud de vacaciones anulada. El saldo fue repuesto.',
            'data' => $solicitud,
        ]);
    }
}
