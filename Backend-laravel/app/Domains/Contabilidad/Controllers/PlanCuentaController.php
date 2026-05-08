<?php

namespace App\Domains\Contabilidad\Controllers;

use App\Domains\Contabilidad\Services\PlanCuentaService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
                'codigo' => 'required|string|regex:/^[0-9.-]+$/',
                'nombre' => 'required|string',
                'tipo' => 'required|in:ACTIVO,PASIVO,PATRIMONIO,INGRESO,GASTO',
                'nivel' => 'integer|max:6',
                'imputable' => 'boolean',
                'activo' => 'boolean'
            ]);

            if ($datos['nivel'] == 1 && !empty($datos['imputable'])) {
                throw ValidationException::withMessages(['imputable' => 'Una cuenta de nivel 1 no puede ser imputable.']); // TODO 7 resuelto
            }

            $datos['empresa_id'] = $request->user()->empresa_id;
            $cuenta = $this->service->registrarCuenta($datos);

            return response()->json([
                'success' => true,
                'data' => $cuenta
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $e->errors()
            ], 422);
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
                'codigo' => 'sometimes|required|string|regex:/^[0-9.-]+$/',
                'nombre' => 'sometimes|required|string',
                'tipo' => 'sometimes|required|in:ACTIVO,PASIVO,PATRIMONIO,INGRESO,GASTO',
                'nivel' => 'sometimes|integer|max:6',
                'imputable' => 'sometimes|boolean',
                'activo' => 'sometimes|boolean'
            ]);

            if (isset($datos['nivel']) && $datos['nivel'] == 1 && !empty($datos['imputable'])) {
                throw ValidationException::withMessages(['imputable' => 'Una cuenta de nivel 1 no puede ser imputable.']);
            }

            $cuenta = $this->service->actualizarCuenta($request->user()->empresa_id, $id, $datos);

            return response()->json([
                'success' => true,
                'message' => 'Cuenta actualizada correctamente',
                'data' => $cuenta
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $e->errors()
            ], 422);
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

    public function destroy(Request $request, $id)
    {
        try {
            $this->service->eliminarCuenta($request->user()->empresa_id, $id);
            return response()->json([
                'success' => true,
                'message' => 'Cuenta eliminada correctamente'
            ]);
        } catch (Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 422;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $code);
        }
    }
}