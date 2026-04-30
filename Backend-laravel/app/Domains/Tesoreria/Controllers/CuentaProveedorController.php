<?php

namespace App\Domains\Tesoreria\Controllers;

use App\Domains\Tesoreria\Services\CuentaProveedorService;
use Illuminate\Http\Request;

class CuentaProveedorController
{
    protected $service;

    public function __construct(CuentaProveedorService $service)
    {
        $this->service = $service;
    }

    public function index($proveedorId)
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->obtenerPorProveedor($proveedorId)
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'proveedorId'  => 'required',
            'banco'        => 'required',
            'numeroCuenta' => 'required',
            'tipoCuenta'   => 'required',
        ]);

        $this->service->registrar($request->all());

        return response()->json(['success' => true]);
    }

    public function destroy($id)
    {
        $this->service->eliminar($id);
        return response()->json(['success' => true]);
    }
}