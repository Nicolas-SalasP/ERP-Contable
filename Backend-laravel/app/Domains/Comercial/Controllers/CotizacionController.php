<?php

namespace App\Domains\Comercial\Controllers;

use App\Domains\Comercial\Services\CotizacionService;
use App\Domains\Tesoreria\Models\CuentaBancariaEmpresa;
use App\Domains\Core\Models\Empresa;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
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
            'data' => $this->service->obtenerPorEmpresa($request->user()->empresa_id)
        ]);
    }

    public function store(Request $request)
    {
        try {
            $datos = [
                'empresa_id' => $request->user()->empresa_id,
                'cliente_id' => $request->clienteId ?? $request->cliente_id,
                'numero_cotizacion' => $request->numeroCotizacion ?? $request->numero_cotizacion,
                'fecha_emision' => $request->fechaEmision ?? $request->fecha_emision,
                'fecha_validez' => $request->fechaValidez ?? $request->fecha_validez,
                'validez' => $request->validezDias ?? $request->validez ?? 30,
                'subtotal' => $request->subtotal,
                'porcentaje_descuento' => $request->porcentajeDescuento ?? $request->porcentaje_descuento ?? 0,
                'monto_descuento' => $request->montoDescuento ?? $request->monto_descuento ?? 0,
                'monto_neto' => $request->montoNeto ?? $request->monto_neto,
                'porcentaje_iva' => $request->porcentajeIva ?? $request->porcentaje_iva ?? 19,
                'monto_iva' => $request->montoIva ?? $request->monto_iva,
                'monto_total' => $request->montoTotal ?? $request->monto_total,
                'estado_id' => $request->estadoId ?? $request->estado_id ?? 1,
                'notas_condiciones' => $request->notasCondiciones ?? $request->notas_condiciones,
                'es_afecta' => $request->has('esAfecta') ? $request->esAfecta : ($request->es_afecta ?? 1),
            ];

            $detallesRaw = $request->input('detalles', $request->input('items', []));

            if (empty($detallesRaw)) {
                throw new Exception("La cotización debe tener al menos un detalle.");
            }

            $detalles = array_map(function ($item) {
                return [
                    'producto_nombre' => $item['productoNombre'] ?? 'Servicio/Producto General',
                    'descripcion' => $item['descripcion'] ?? '',
                    'cantidad' => $item['cantidad'] ?? 1,
                    'precio_unitario' => $item['precioUnitario'] ?? $item['precio_unitario'] ?? 0,
                    'subtotal' => $item['subtotal'] ?? 0,
                ];
            }, $detallesRaw);

            $cotizacion = $this->service->crearCotizacion($datos, $detalles);

            return response()->json([
                'success' => true,
                'data' => $cotizacion
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function generarPdf(Request $request, $id)
    {
        try {
            $empresaId = $request->user()->empresa_id;
            $cotizacion = $this->service->obtenerPorId($empresaId, (int) $id);
            $empresa = Empresa::find($empresaId);
            $cuentasBancarias = CuentaBancariaEmpresa::where('empresa_id', $empresaId)->get();
            $pdf = Pdf::loadView('pdf.cotizacion', compact('cotizacion', 'empresa', 'cuentasBancarias'));
            $nombreLimpio = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $cotizacion->nombre_cliente);
            return $pdf->download('Cotizacion_' . $cotizacion->id . ' - ' . trim($nombreLimpio) . '.pdf');

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}