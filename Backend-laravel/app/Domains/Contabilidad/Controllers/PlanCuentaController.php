<?php

namespace App\Domains\Contabilidad\Controllers;

use App\Domains\Contabilidad\Services\PlanCuentaService;
use Illuminate\Http\Request;
use Exception;

class PlanCuentaController
{
    protected $service;

    public function __construct(PlanCuentaService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->listarCuentas($request->user()->empresa_id)
        ]);
    }

    public function store(Request $request)
    {
        try {
            $datos = $request->validate([
                'codigo' => 'required|string',
                'nombre' => 'required|string',
                'tipo' => 'required|in:ACTIVO,PASIVO,PATRIMONIO,INGRESO,GASTO',
                'nivel' => 'integer',
                'imputable' => 'boolean',
                'activo' => 'boolean'
            ]);

            $datos['empresa_id'] = $request->user()->empresa_id;
            $cuenta = $this->service->registrarCuenta($datos);

            return response()->json([
                'success' => true,
                'data' => $cuenta
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $datos = $request->validate([
                'codigo' => 'sometimes|required|string',
                'nombre' => 'sometimes|required|string',
                'tipo' => 'sometimes|required|in:ACTIVO,PASIVO,PATRIMONIO,INGRESO,GASTO',
                'nivel' => 'sometimes|integer',
                'imputable' => 'sometimes|boolean',
                'activo' => 'sometimes|boolean'
            ]);

            $cuenta = $this->service->actualizarCuenta($request->user()->empresa_id, $id, $datos);

            return response()->json([
                'success' => true,
                'message' => 'Cuenta actualizada correctamente',
                'data' => $cuenta
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function imputables(Request $request)
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->service->listarCuentasImputables($request->user()->empresa_id)
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}