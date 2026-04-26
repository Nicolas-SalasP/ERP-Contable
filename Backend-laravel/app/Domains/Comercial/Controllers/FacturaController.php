<?php

namespace App\Domains\Comercial\Controllers;

use App\Domains\Comercial\Services\FacturaService;
use App\Domains\Comercial\Models\Factura;
use Illuminate\Http\Request;

class FacturaController
{
    protected $service;

    public function __construct(FacturaService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $facturas = Factura::where('empresa_id', $request->user()->empresa_id)
            ->with(['proveedor', 'cuentaBancaria']) // Relaciones clave
            ->orderBy('fecha_emision', 'desc')
            ->paginate(20);

        return response()->json($facturas);
    }

    public function store(Request $request)
    {
        try {
            $datos = $request->all();
            $datos['empresa_id'] = $request->user()->empresa_id;
            $factura = $this->service->registrarFacturaCompra($datos);

            return response()->json([
                'success' => true,
                'message' => 'Factura registrada y validada correctamente',
                'data' => $factura
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422); // Unprocessable Entity (Error de reglas de negocio)
        }
    }
}