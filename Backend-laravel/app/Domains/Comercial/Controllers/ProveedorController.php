<?php

namespace App\Domains\Comercial\Controllers;

use App\Domains\Comercial\Services\ProveedorService;
use Illuminate\Http\Request;
use Exception;

class ProveedorController
{
    protected $service;

    public function __construct(ProveedorService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        return response()->json($this->service->obtenerProveedoresPorEmpresa($request->user()->empresa_id));
    }

    public function store(Request $request)
    {
        try {
            $datos = $request->all();
            $datos['empresa_id'] = $request->user()->empresa_id;

            $proveedor = $this->service->registrarProveedor($datos);

            return response()->json(['message' => 'Proveedor creado', 'data' => $proveedor], 201);

        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}