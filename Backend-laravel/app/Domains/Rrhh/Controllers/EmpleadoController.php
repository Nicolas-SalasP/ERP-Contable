<?php

namespace App\Domains\Rrhh\Controllers;

use App\Domains\Rrhh\Services\EmpleadoService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmpleadoController extends Controller
{
    public function __construct(private readonly EmpleadoService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $filtros = $request->only(['estado', 'buscar', 'por_pagina']);
        $data = $this->service->listar($request->user()->empresa_id, $filtros);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $empleado = $this->service->obtener($request->user()->empresa_id, $id);
        return response()->json(['success' => true, 'data' => $empleado]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'rut' => 'required|string|max:12',
            'nombres' => 'required|string|max:100',
            'apellido_paterno' => 'required|string|max:60',
            'apellido_materno' => 'nullable|string|max:60',
            'fecha_nacimiento' => 'nullable|date',
            'sexo' => 'nullable|in:M,F,O',
            'nacionalidad' => 'nullable|string|max:3',
            'estado_civil' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:150',
            'telefono' => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:255',
            'ciudad' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'afp' => 'nullable|string|max:30',
            'tipo_salud' => 'nullable|in:FONASA,ISAPRE',
            'isapre_nombre' => 'nullable|string|max:50',
            'isapre_plan_uf' => 'nullable|numeric|min:0',
            'isapre_cotizacion_adicional_pct' => 'nullable|numeric|min:0',
            'banco_nombre' => 'nullable|string|max:80',
            'banco_tipo_cuenta' => 'nullable|string|max:30',
            'banco_numero_cuenta' => 'nullable|string|max:30',
            'fecha_ingreso_empresa' => 'nullable|date',
            'observaciones' => 'nullable|string',
        ]);

        $empleado = $this->service->crear($request->user()->empresa_id, $datos);

        return response()->json([
            'success' => true,
            'message' => 'Empleado creado correctamente.',
            'data' => $empleado,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate([
            'rut' => 'sometimes|string|max:12',
            'nombres' => 'sometimes|string|max:100',
            'apellido_paterno' => 'sometimes|string|max:60',
            'apellido_materno' => 'nullable|string|max:60',
            'fecha_nacimiento' => 'nullable|date',
            'sexo' => 'nullable|in:M,F,O',
            'email' => 'nullable|email|max:150',
            'telefono' => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:255',
            'ciudad' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'afp' => 'nullable|string|max:30',
            'tipo_salud' => 'nullable|in:FONASA,ISAPRE',
            'isapre_nombre' => 'nullable|string|max:50',
            'isapre_plan_uf' => 'nullable|numeric|min:0',
            'banco_nombre' => 'nullable|string|max:80',
            'banco_tipo_cuenta' => 'nullable|string|max:30',
            'banco_numero_cuenta' => 'nullable|string|max:30',
            'estado' => 'nullable|in:ACTIVO,INACTIVO,LICENCIA',
            'observaciones' => 'nullable|string',
        ]);

        $empleado = $this->service->actualizar($request->user()->empresa_id, $id, $datos);

        return response()->json([
            'success' => true,
            'message' => 'Empleado actualizado correctamente.',
            'data' => $empleado,
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->service->eliminar($request->user()->empresa_id, $id);

        return response()->json([
            'success' => true,
            'message' => 'Empleado eliminado correctamente.',
        ]);
    }
}
