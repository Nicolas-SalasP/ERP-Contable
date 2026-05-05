<?php

namespace App\Domains\Tesoreria\Controllers;

use App\Domains\Tesoreria\Services\CuentaProveedorService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class CuentaProveedorController
{
    protected $service;

    public function __construct(CuentaProveedorService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request, $proveedorId)
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->service->obtenerPorProveedor($request->user()->empresa_id, $proveedorId)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Proveedor no encontrado.'], 404);
        } catch (Exception $e) {
            $status = $e->getCode() === 403 ? 403 : 400;
            return response()->json(['success' => false, 'message' => $e->getMessage()], $status);
        }
    }

    public function store(Request $request)
    {
        try {
            $datos = $request->validate([
                'proveedorId' => 'required|integer',
                'banco' => 'required|string|max:100',
                'numeroCuenta' => 'required|string|max:50',
                'tipoCuenta' => 'required|string|max:50',
                'paisIso' => 'nullable|string|size:2'
            ]);

            $cuenta = $this->service->registrar($request->user()->empresa_id, $datos);

            return response()->json(['success' => true, 'data' => $cuenta], 201);

        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Proveedor no encontrado.'], 404);
        } catch (Exception $e) {
            $status = $e->getCode() === 403 ? 403 : 400;
            return response()->json(['success' => false, 'message' => $e->getMessage()], $status);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $this->service->eliminar($request->user()->empresa_id, $id);
            return response()->json(['success' => true]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Cuenta bancaria no encontrada.'], 404);
        } catch (Exception $e) {
            $status = $e->getCode() === 403 ? 403 : 400;
            return response()->json(['success' => false, 'message' => $e->getMessage()], $status);
        }
    }
}