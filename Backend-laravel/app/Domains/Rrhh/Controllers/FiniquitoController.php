<?php

namespace App\Domains\Rrhh\Controllers;

use App\Domains\Rrhh\Models\Finiquito;
use App\Domains\Rrhh\Services\Finiquito\FiniquitoService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FiniquitoController extends Controller
{
    public function __construct(private readonly FiniquitoService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $finiquitos = Finiquito::where('empresa_id', $request->user()->empresa_activa_id)
            ->with(['empleado:id,nombres,apellido_paterno,apellido_materno,rut'])
            ->orderByDesc('fecha_termino')
            ->paginate(30);

        return response()->json(['success' => true, 'data' => $finiquitos]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $finiquito = Finiquito::where('empresa_id', $request->user()->empresa_activa_id)
            ->with(['empleado', 'contrato'])
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $finiquito]);
    }

    public function calcular(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'contrato_id' => 'required|integer',
            'causal' => 'required|in:MUTUO_ACUERDO,RENUNCIA,MUERTE,VENCIMIENTO_PLAZO,FIN_OBRA,CASO_FORTUITO,DESPIDO_CONDUCTA,NECESIDADES_EMPRESA,DESAHUCIO',
            'fecha_termino' => 'required|date',
            'promedio_variables' => 'nullable|numeric|min:0',
            'aviso_previo' => 'nullable|boolean',
            'haberes_pendientes' => 'nullable|numeric|min:0',
            'descuentos_pendientes' => 'nullable|numeric|min:0',
            'observaciones' => 'nullable|string|max:500',
        ]);

        $finiquito = $this->service->calcular(
            $request->user()->empresa_activa_id,
            $datos['contrato_id'],
            $datos['causal'],
            $datos['fecha_termino'],
            $request->only(['promedio_variables', 'aviso_previo', 'haberes_pendientes', 'descuentos_pendientes', 'observaciones']),
        );

        return response()->json([
            'success' => true,
            'message' => 'Finiquito calculado correctamente.',
            'data' => $finiquito,
        ], 201);
    }

    public function firmar(Request $request, int $id): JsonResponse
    {
        $finiquito = $this->service->firmar($request->user()->empresa_activa_id, $id);
        return response()->json([
            'success' => true,
            'message' => 'Finiquito firmado. El contrato fue terminado.',
            'data' => $finiquito,
        ]);
    }

    public function anular(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate(['motivo' => 'required|string|min:5|max:500']);

        $finiquito = $this->service->anular($request->user()->empresa_activa_id, $id, $datos['motivo']);

        return response()->json([
            'success' => true,
            'message' => 'Finiquito anulado. El contrato fue reactivado si correspondía.',
            'data' => $finiquito,
        ]);
    }
}
