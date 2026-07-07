<?php

namespace App\Domains\Rrhh\Controllers;

use App\Domains\Core\Models\Auditoria;
use App\Domains\Rrhh\Models\Liquidacion;
use App\Domains\Rrhh\Services\Calculo\LiquidacionService;
use App\Domains\Rrhh\Services\Provisiones\VacacionesService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

        $query = Liquidacion::where('empresa_id', $request->user()->empresa_activa_id)
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
        $liq = Liquidacion::where('empresa_id', $request->user()->empresa_activa_id)
            ->with(['detalles', 'empleado', 'contrato', 'parametro', 'indicador'])
            ->findOrFail($id);

        // Auditoria de lectura PII (Ley 21.719 — Fase 3): solo registra el ID del empleado, nunca nombre/RUT/otros datos PII.
        if (config('auditoria.lectura_pii')) {
            try {
                Auditoria::create([
                    'auditable_type'     => Liquidacion::class,
                    'auditable_id'       => $liq->id,
                    'nombre_usuario'     => $request->user()->nombre ?? 'Sistema',
                    'operacion'          => 'LECTURA',
                    'estado_anterior'    => null,
                    'estado_nuevo'       => null,
                    'detalle'            => 'Consulta de liquidación del empleado id=' . $liq->empleado_id,
                    'referencia_cruzada' => (string) $liq->empresa_id,
                ]);
            } catch (\Throwable $e) {
                Log::warning('LiquidacionController: fallo al registrar lectura PII', [
                    'liquidacion_id' => $liq->id,
                    'error'          => $e->getMessage(),
                ]);
            }
        }

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
            $request->user()->empresa_activa_id,
            $datos['empleado_id'],
            $datos['anio'],
            $datos['mes'],
            $request->only(['horas_extra', 'remuneraciones_variables', 'apv_voluntario']),
        );

        // Devengar provisión de vacaciones del mes
        $this->vacaciones->devengarMes($request->user()->empresa_activa_id, $liq->id);

        return response()->json([
            'success' => true,
            'message' => 'Liquidación calculada correctamente.',
            'data' => $liq,
        ], 201);
    }

    public function emitir(Request $request, int $id): JsonResponse
    {
        $liq = $this->service->emitir($request->user()->empresa_activa_id, $id);
        return response()->json([
            'success' => true,
            'message' => 'Liquidación emitida.',
            'data' => $liq,
        ]);
    }

    public function anular(Request $request, int $id): JsonResponse
    {
        $liq = $this->service->anular($request->user()->empresa_activa_id, $id);
        return response()->json([
            'success' => true,
            'message' => 'Liquidación anulada.',
            'data' => $liq,
        ]);
    }
}
