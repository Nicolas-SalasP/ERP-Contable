<?php

namespace App\Domains\Comercial\Controllers;

use App\Domains\Comercial\Services\ClienteService;
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
            $datos = [
                'empresa_id' => $request->user()->empresa_id,
                'rut' => $request->rut,
                'razon_social' => $request->razonSocial ?? $request->razon_social,
                'direccion' => $request->direccion,
                'email' => $request->emailFacturacion ?? $request->email,
                'telefono' => $request->telefono,
                'contacto_nombre' => $request->nombreContacto ?? $request->contactoNombre ?? $request->contacto_nombre,
                'contacto_email' => $request->emailContacto ?? $request->contacto_email,
                'contacto_telefono' => $request->telefonoContacto ?? $request->contacto_telefono,

                'estado' => 'ACTIVO'
            ];

            $cliente = $this->service->registrarCliente($datos);

            return response()->json([
                'success' => true,
                'data' => $cliente
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function destroy(Request $request, $id)
    {
        $this->service->inactivarCliente($request->user()->empresa_id, $id);
        return response()->json(['success' => true]);
    }
}