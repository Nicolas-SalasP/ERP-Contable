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
        return response()->json($this->service->obtenerClientesPorEmpresa($request->user()->empresa_id));
    }

    public function store(Request $request)
    {
        try {
            $datos = $request->validate([
                'rut' => 'required|string|max:20',
                'razon_social' => 'required|string|max:255',
                'email' => 'nullable|email',
                'telefono' => 'nullable|string'
            ]);

            $datos['empresa_id'] = $request->user()->empresa_id;

            $cliente = $this->service->registrarCliente($datos);

            return response()->json(['message' => 'Cliente creado con éxito', 'data' => $cliente], 201);
            
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}