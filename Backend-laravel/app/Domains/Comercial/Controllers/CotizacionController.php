<?php

namespace App\Domains\Comercial\Controllers;

use App\Domains\Comercial\Services\CotizacionService;
use App\Domains\Tesoreria\Models\CuentaBancariaEmpresa;
use App\Domains\Core\Models\Empresa;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Validation\ValidationException;
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
            $request->validate([
                'porcentaje_descuento' => 'nullable|numeric|min:0|max:100',
                'fecha_emision' => 'nullable|date',
                'fecha_validez' => 'nullable|date|after_or_equal:fecha_emision',
                'detalles' => 'required|array|min:1',
                'detalles.*.cantidad' => 'required|numeric|min:0.01',
                'detalles.*.precio_unitario' => 'required|numeric|min:0',
            ]);

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

            $detallesRaw = $request->input('detalles', []);

            $detalles = array_map(function ($item) {
                return [
                    'producto_nombre' => $item['productoNombre'] ?? $item['producto_nombre'] ?? 'Servicio/Producto General',
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

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $e->errors()
            ], 422);
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

    public function actualizarEstado(Request $request, $id)
    {
        try {
            $datos = $request->validate([
                'estado' => 'sometimes|string',
                'estado_id' => 'sometimes|integer',
            ]);

            $estadoNombre = $datos['estado'] ?? null;
            if (!$estadoNombre && isset($datos['estado_id'])) {
                $estadoModel = \App\Domains\Comercial\Models\EstadoCotizacion::find($datos['estado_id']);
                if (!$estadoModel) {
                    return response()->json([
                        'success' => false,
                        'message' => "Estado con id {$datos['estado_id']} no existe."
                    ], 422);
                }
                $estadoNombre = $estadoModel->nombre;
            }

            if (!$estadoNombre) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debes especificar estado o estado_id.',
                    'errors' => ['estado' => ['Campo requerido.']]
                ], 422);
            }

            $cotizacion = $this->service->actualizarEstado(
                $request->user()->empresa_id,
                (int) $id,
                $estadoNombre
            );

            return response()->json([
                'success' => true,
                'data' => $cotizacion
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            $status = $e->getCode() === 404 ? 404 : 400;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $status);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $datos = $request->validate([
                'porcentaje_descuento' => 'nullable|numeric|min:0|max:100',
                'fecha_validez' => 'nullable|date',
                'detalles' => 'nullable|array|min:1',
                'detalles.*.producto_nombre' => 'required_with:detalles|string|max:255',
                'detalles.*.cantidad' => 'required_with:detalles|numeric|min:1',
                'detalles.*.precio_unitario' => 'required_with:detalles|numeric|min:0',
            ]);

            $cotizacion = $this->service->actualizarCotizacion(
                $request->user()->empresa_id, 
                (int) $id, 
                $datos
            );

            return response()->json([
                'success' => true,
                'message' => 'Cotización actualizada correctamente.',
                'data' => $cotizacion
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function facturar(Request $request, $id)
    {
        try {
            $factura = $this->service->convertirEnFactura(
                $request->user()->empresa_id,
                (int) $id
            );

            return response()->json([
                'success' => true,
                'message' => 'Factura de venta creada exitosamente.',
                'data' => $factura
            ], 201);
        } catch (Exception $e) {
            $status = $e->getCode() === 404 ? 404 : 400;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $status);
        }
    }
}