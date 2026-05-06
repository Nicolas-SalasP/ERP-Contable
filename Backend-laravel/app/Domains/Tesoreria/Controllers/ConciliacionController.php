<?php

namespace App\Domains\Tesoreria\Controllers;

use App\Domains\Tesoreria\Services\ConciliacionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            $status = $e->getCode() === 403 ? 403 : 422;
            return response()->json(['success' => false, 'message' => $e->getMessage()], $status);
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
            $status = $e->getCode() === 403 ? 403 : 400;
            return response()->json(['success' => false, 'mensaje' => $e->getMessage()], $status);
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
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            $status = $e->getCode() === 403 ? 403 : 422;
            return response()->json(['success' => false, 'mensaje' => $e->getMessage()], $status);
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
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            $status = $e->getCode() === 403 ? 403 : 422;
            return response()->json(['success' => false, 'mensaje' => $e->getMessage()], $status);
        }
    }

    public function sugerencias(Request $request, $id)
    {
        try {
            $sugerencias = $this->service->obtenerSugerenciasConciliacion($request->user()->empresa_id, $id);
            return response()->json(['success' => true, 'data' => $sugerencias]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function conciliarFacturas(Request $request)
    {
        try {
            $datos = $request->validate([
                'movimiento_id' => 'required|integer',
                'facturas_ids' => 'nullable|array',
                'entidad_id' => 'nullable|integer'
            ]);

            $asiento = $this->service->procesarPagoFacturas(
                $request->user()->empresa_id,
                $request->user()->id,
                $datos['movimiento_id'],
                $datos['facturas_ids'] ?? [],
                $datos['entidad_id'] ?? null
            );
            
            return response()->json([
                'success' => true, 
                'message' => 'Conciliación procesada exitosamente.',
                'asiento' => $asiento 
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}