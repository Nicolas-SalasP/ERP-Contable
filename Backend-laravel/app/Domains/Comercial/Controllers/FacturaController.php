<?php

namespace App\Domains\Comercial\Controllers;

use App\Domains\Comercial\Services\FacturaService;
use Illuminate\Http\Request;
use Exception;

class FacturaController
{
    protected $service;

    public function __construct(FacturaService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->obtenerFacturasPorEmpresa($request->user()->empresa_id, $request->query('estado'))
        ]);
    }

    public function historial(Request $request)
    {
        return $this->index($request);
    }

    public function check(Request $request)
    {
        $existe = $this->service->verificarDuplicado(
            $request->user()->empresa_id,
            (int) ($request->query('proveedorId') ?? $request->query('proveedor_id')),
            $request->query('numeroFactura') ?? $request->query('numero_factura')
        );

        return response()->json([
            'success' => true,
            'exists' => $existe
        ]);
    }

    public function show(Request $request, $id)
    {
        try {
            $factura = $this->service->obtenerFacturaPorId($request->user()->empresa_id, (int) $id);

            return response()->json([
                'success' => true,
                'data' => $factura
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 404);
        }
    }

    public function store(Request $request)
    {
        try {
            $datos = [
                'empresa_id' => $request->user()->empresa_id,
                'proveedor_id' => $request->proveedorId ?? $request->proveedor_id,
                'cuenta_bancaria_id' => $request->cuentaBancariaId ?? $request->cuenta_bancaria_id,
                'numero_factura' => $request->numeroFactura ?? $request->numero_factura,
                'fecha_emision' => $request->fechaEmision ?? $request->fecha_emision,
                'fecha_vencimiento' => $request->fechaVencimiento ?? $request->fecha_vencimiento,
                'monto_neto' => $request->montoNeto ?? $request->monto_neto,
                'monto_iva' => $request->montoIva ?? $request->monto_iva,
                'monto_bruto' => $request->montoBruto ?? $request->monto_bruto,
                'tipo_documento' => $request->tipoDocumento ?? $request->tipo_documento,
                'autorizador_id' => $request->autorizadorId ?? $request->autorizador_id,
            ];

            $factura = $this->service->registrarFacturaCompra($datos);

            return response()->json([
                'success' => true,
                'data' => $factura
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }
}