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
                'factura_id' => 'required|integer',
                'cuenta_bancaria_id' => 'required|integer',
                'fecha_pago' => 'required|date',
            ]);
            $datos['empresa_id'] = $request->user()->empresa_id;
            $factura = $this->service->conciliarPagoFacturaCompra($datos);

            return response()->json(['success' => true, 'message' => 'Factura pagada exitosamente.', 'data' => $factura], 200);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function movimientosPendientes(Request $request, $idCuenta)
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->service->obtenerMovimientosPendientes($request->user()->empresa_id, $idCuenta)
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'mensaje' => $e->getMessage()], 400);
        }
    }

    public function anticiposPendientes(Request $request)
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->service->obtenerAnticiposPendientes($request->user()->empresa_id)
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'mensaje' => $e->getMessage()], 400);
        }
    }

    public function conciliar(Request $request)
    {
        try {
            $datos = $request->validate([
                'movimiento_id' => 'required|integer',
                'cuenta_codigo' => 'required|string',
                'glosa' => 'required|string',
                'centro_costo_id' => 'nullable|integer',
                'empleado_nombre' => 'nullable|string'
            ]);

            $asiento = $this->service->conciliarDirecto($request->user()->empresa_id, $datos, $request->user()->id);

            return response()->json(['success' => true, 'mensaje' => 'Movimiento conciliado.', 'data' => $asiento]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'mensaje' => $e->getMessage()], 422);
        }
    }

    public function conciliarAnticipo(Request $request)
    {
        try {
            $datos = $request->validate([
                'movimiento_id' => 'required|integer',
                'anticipo_id' => 'required|integer'
            ]);
            $this->service->conciliarAnticipo($request->user()->empresa_id, $datos, $request->user()->id);

            return response()->json(['success' => true, 'mensaje' => 'Anticipo conciliado correctamente.']);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'mensaje' => $e->getMessage()], 422);
        }
    }
}