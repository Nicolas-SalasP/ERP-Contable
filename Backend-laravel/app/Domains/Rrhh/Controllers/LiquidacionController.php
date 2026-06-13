<?php

namespace App\Domains\Rrhh\Controllers;

use App\Domains\Rrhh\Models\Liquidacion;
use App\Domains\Rrhh\Services\Calculo\LiquidacionService;
use App\Domains\Rrhh\Services\Provisiones\VacacionesService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiquidacionController extends Controller
{
    public function __construct(
        private readonly LiquidacionService $service,
        private readonly VacacionesService $vacaciones,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'anio' => 'nullable|integer|min:2000|max:2100',
            'mes' => 'nullable|integer|min:1|max:12',
            'empleado_id' => 'nullable|integer',
        ]);

        $query = Liquidacion::where('empresa_id', $request->user()->empresa_id)
            ->with(['empleado:id,nombres,apellido_paterno,apellido_materno,rut'])
            ->orderByDesc('anio')
            ->orderByDesc('mes');

        if (!empty($datos['anio'])) {
            $query->where('anio', $datos['anio']);
        }
        if (!empty($datos['mes'])) {
            $query->where('mes', $datos['mes']);
        }
        if (!empty($datos['empleado_id'])) {
            $query->where('empleado_id', $datos['empleado_id']);
        }

        return response()->json(['success' => true, 'data' => $query->paginate(30)]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $liq = Liquidacion::where('empresa_id', $request->user()->empresa_id)
            ->with(['detalles', 'empleado', 'contrato', 'parametro', 'indicador'])
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $liq]);
    }

    public function calcular(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'empleado_id' => 'required|integer',
            'anio' => 'required|integer|min:2000|max:2100',
            'mes' => 'required|integer|min:1|max:12',
            'horas_extra' => 'nullable|numeric|min:0',
            'remuneraciones_variables' => 'nullable|numeric|min:0',
            'apv_voluntario' => 'nullable|numeric|min:0',
        ]);

        $liq = $this->service->calcular(
            $request->user()->empresa_id,
            $datos['empleado_id'],
            $datos['anio'],
            $datos['mes'],
            $request->only(['horas_extra', 'remuneraciones_variables', 'apv_voluntario']),
        );

        // Devengar provisión de vacaciones del mes
        $this->vacaciones->devengarMes($request->user()->empresa_id, $liq->id);

        return response()->json([
            'success' => true,
            'message' => 'Liquidación calculada correctamente.',
            'data' => $liq,
        ], 201);
    }

    public function emitir(Request $request, int $id): JsonResponse
    {
        $liq = $this->service->emitir($request->user()->empresa_id, $id);
        return response()->json([
            'success' => true,
            'message' => 'Liquidación emitida.',
            'data' => $liq,
        ]);
    }

    public function anular(Request $request, int $id): JsonResponse
    {
        $liq = $this->service->anular($request->user()->empresa_id, $id);
        return response()->json([
            'success' => true,
            'message' => 'Liquidación anulada.',
            'data' => $liq,
        ]);
    }
}
