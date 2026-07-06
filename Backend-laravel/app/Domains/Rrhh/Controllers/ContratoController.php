<?php

namespace App\Domains\Rrhh\Controllers;

use App\Domains\Rrhh\Services\ContratoService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContratoController extends Controller
{
    public function __construct(private readonly ContratoService $service)
    {
    }

    public function indexPorEmpleado(Request $request, int $empleadoId): JsonResponse
    {
        $contratos = $this->service->listarPorEmpleado($request->user()->empresa_activa_id, $empleadoId);
        return response()->json(['success' => true, 'data' => $contratos]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $contrato = $this->service->obtener($request->user()->empresa_activa_id, $id);
        return response()->json(['success' => true, 'data' => $contrato]);
    }

    public function store(Request $request, int $empleadoId): JsonResponse
    {
        $datos = $request->validate([
            'tipo' => 'required|in:INDEFINIDO,PLAZO_FIJO,POR_OBRA',
            'fecha_inicio' => 'required|date',
            'fecha_termino' => 'required_if:tipo,PLAZO_FIJO,POR_OBRA|nullable|date|after:fecha_inicio',
            'cargo' => 'nullable|string|max:100',
            'departamento' => 'nullable|string|max:100',
            'centro_costo_id' => 'nullable|integer|exists:centros_costo,id',
            'horas_semana' => 'nullable|integer|min:1|max:44',
            'tipo_jornada' => 'nullable|in:COMPLETA,PARCIAL,TURNO',
            'sueldo_base' => 'required|numeric|min:0',
            'observaciones' => 'nullable|string',
        ]);

        $haberes = $request->validate([
            'haberes' => 'nullable|array',
            'haberes.*.nombre' => 'required|string|max:100',
            'haberes.*.tipo' => 'required|in:HABER_IMPONIBLE,HABER_NO_IMPONIBLE,DESCUENTO_LEGAL,DESCUENTO_VOLUNTARIO',
            'haberes.*.modalidad_valor' => 'required|in:MONTO_FIJO,PORCENTAJE_SUELDO_BASE',
            'haberes.*.monto' => 'nullable|numeric|min:0',
            'haberes.*.porcentaje' => 'nullable|numeric|min:0|max:100',
            'haberes.*.vigente_desde' => 'required|date',
        ])['haberes'] ?? [];

        $contrato = $this->service->crear($request->user()->empresa_activa_id, $empleadoId, $datos, $haberes);

        return response()->json([
            'success' => true,
            'message' => 'Contrato creado correctamente.',
            'data' => $contrato,
        ], 201);
    }

    public function terminar(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate([
            'causal_termino' => 'required|in:MUTUO_ACUERDO,RENUNCIA,MUERTE,VENCIMIENTO_PLAZO,FIN_OBRA,CASO_FORTUITO,DESPIDO_CONDUCTA,NECESIDADES_EMPRESA,DESAHUCIO',
            'fecha_termino_real' => 'required|date',
            'observaciones_termino' => 'nullable|string|max:500',
        ]);

        $contrato = $this->service->terminar($request->user()->empresa_activa_id, $id, $datos);

        return response()->json([
            'success' => true,
            'message' => 'Contrato terminado correctamente.',
            'data' => $contrato,
        ]);
    }

    public function agregarHaber(Request $request, int $contratoId): JsonResponse
    {
        $datos = $request->validate([
            'nombre' => 'required|string|max:100',
            'concepto_remuneracion_id' => 'nullable|integer',
            'tipo' => 'required|in:HABER_IMPONIBLE,HABER_NO_IMPONIBLE,DESCUENTO_LEGAL,DESCUENTO_VOLUNTARIO',
            'modalidad_valor' => 'required|in:MONTO_FIJO,PORCENTAJE_SUELDO_BASE',
            'monto' => 'nullable|numeric|min:0',
            'porcentaje' => 'nullable|numeric|min:0|max:100',
            'vigente_desde' => 'required|date',
            'vigente_hasta' => 'nullable|date|after:vigente_desde',
        ]);

        $haber = $this->service->agregarHaber($request->user()->empresa_activa_id, $contratoId, $datos);

        return response()->json([
            'success' => true,
            'message' => 'Haber/descuento agregado al contrato.',
            'data' => $haber,
        ], 201);
    }
}
