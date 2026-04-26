<?php

namespace App\Domains\Tesoreria\Controllers;

use App\Domains\Tesoreria\Services\ConciliacionService;
use Illuminate\Http\Request;
use Exception;

class ConciliacionController
{
    protected $service;

    public function __construct(ConciliacionService $service)
    {
        $this->service = $service;
    }

    public function pagarFacturaCompra(Request $request)
    {
        try {
            $datos = $request->validate([
                'factura_id'         => 'required|integer',
                'cuenta_bancaria_id' => 'required|integer',
                'fecha_pago'         => 'required|date',
            ]);

            $datos['empresa_id'] = $request->user()->empresa_id;

            $factura = $this->service->conciliarPagoFacturaCompra($datos);

            return response()->json([
                'success' => true,
                'message' => 'Factura pagada y asiento contable generado exitosamente.',
                'data'    => $factura
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }
}