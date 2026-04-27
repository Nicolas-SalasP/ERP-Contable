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
                'imputable' => 'boolean'
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
}