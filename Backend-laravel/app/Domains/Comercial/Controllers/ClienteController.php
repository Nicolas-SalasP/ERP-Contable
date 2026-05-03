<?php

namespace App\Domains\Comercial\Controllers;

use App\Domains\Comercial\Services\ClienteService;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Exception;

class ClienteController
{
    protected $service;

    public function __construct(ClienteService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->buscarClientesPorEmpresa($request->user()->empresa_id, $request->search)
        ]);
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'rut' => 'required|string|max:20',
                'razon_social' => 'required|string|max:255',
            ]);

            $datos = [
                'empresa_id' => $request->user()->empresa_id,
                'rut' => $request->rut,
                'razon_social' => $request->razonSocial ?? $request->razon_social,
                'email' => $request->email ?? null,
                'estado' => 'ACTIVO'
            ];

            $cliente = $this->service->registrarCliente($datos);

            return response()->json(['success' => true, 'data' => $cliente], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => ['rut' => [$e->getMessage()]]
            ], 422);
        }
    }

    public function destroy(Request $request, $id)
    {
        $this->service->inactivarCliente($request->user()->empresa_id, $id);
        return response()->json(['success' => true]);
    }
}