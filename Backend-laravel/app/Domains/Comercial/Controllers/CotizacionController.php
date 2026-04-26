<?php

namespace App\Domains\Comercial\Controllers;

use App\Domains\Comercial\Services\CotizacionService;
use Illuminate\Http\Request;
use Exception;

class CotizacionController
{
    protected $service;

    public function __construct(CotizacionService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->obtenerPorEmpresa($request->user()->empresa_id)
        ]);
    }

    public function store(Request $request)
    {
        try {
            $datos = $request->except('detalles');
            $datos['empresa_id'] = $request->user()->empresa_id;
            
            $detalles = $request->input('detalles', []);

            if (empty($detalles)) {
                throw new Exception("La cotización debe tener al menos un detalle.");
            }

            $cotizacion = $this->service->crearCotizacion($datos, $detalles);

            return response()->json([
                'success' => true,
                'data'    => $cotizacion
            ], 201);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }
}