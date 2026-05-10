<?php

namespace App\Domains\Comercial\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Comercial\Services\AnticipoProveedorService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class AnticipoProveedorController extends Controller
{
    protected $service;

    public function __construct(AnticipoProveedorService $service)
    {
        $this->service = $service;
    }

    public function store(Request $request)
    {
        try {
            $datos = $request->validate([
                'proveedor_id' => 'required|integer',
                'monto' => 'required|numeric|min:1',
                'fecha' => 'nullable|date',
                'referencia' => 'nullable|string|max:255',
            ]);

            $anticipo = $this->service->registrar(
                $request->user()->empresa_id,
                $datos
            );

            return response()->json([
                'success' => true,
                'message' => 'Anticipo registrado exitosamente.',
                'data' => $anticipo
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function aplicar(Request $request, $id)
    {
        try {
            $datos = $request->validate([
                'factura_id' => 'required|integer',
                'monto_a_aplicar' => 'required|numeric|min:1',
            ]);

            $anticipo = $this->service->aplicarAFactura(
                $request->user()->empresa_id,
                (int) $id,
                (int) $datos['factura_id'],
                (float) $datos['monto_a_aplicar']
            );

            return response()->json([
                'success' => true,
                'message' => 'Anticipo aplicado correctamente.',
                'data' => $anticipo
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            $status = $e->getCode() === 404 ? 404 : 400;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $status);
        }
    }

    public function index(Request $request)
    {
        try {
            $anticipos = $this->service->listar(
                $request->user()->empresa_id,
                $request->query('proveedor_id') ? (int) $request->query('proveedor_id') : null
            );
            return response()->json(['success' => true, 'data' => $anticipos]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
