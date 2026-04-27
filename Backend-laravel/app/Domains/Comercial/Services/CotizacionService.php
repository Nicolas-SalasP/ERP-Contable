<?php

namespace App\Domains\Comercial\Services;

use App\Domains\Comercial\Models\Cotizacion;
use App\Domains\Comercial\Models\CotizacionDetalle;
use App\Domains\Comercial\Models\Cliente;
use Illuminate\Support\Facades\DB;
use Exception;

class CotizacionService
{
    public function obtenerPorEmpresa(int $empresaId)
    {
        return Cotizacion::where('empresa_id', $empresaId)
            ->with(['cliente', 'estado'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function crearCotizacion(array $datos, array $detalles): Cotizacion
    {
        return DB::transaction(function () use ($datos, $detalles) {

            $cliente = Cliente::find($datos['cliente_id']);

            $subtotalCalculado = 0;
            foreach ($detalles as $det) {
                $subtotalCalculado += ($det['cantidad'] * $det['precio_unitario']);
            }

            $porcentajeDescuento = $datos['porcentaje_descuento'] ?? 0;
            $montoDescuento = round($subtotalCalculado * ($porcentajeDescuento / 100));
            $montoNeto = $subtotalCalculado - $montoDescuento;

            $porcentajeIva = $datos['porcentaje_iva'] ?? 19;
            $montoIva = round($montoNeto * ($porcentajeIva / 100));
            $montoTotal = $montoNeto + $montoIva;

            $fechaEmision = $datos['fecha_emision'] ?? date('Y-m-d');
            $validezDias = $datos['validez'] ?? 30;
            $fechaValidez = $datos['fecha_validez'] ?? date('Y-m-d', strtotime($fechaEmision . ' + ' . $validezDias . ' days'));

            $cotizacion = Cotizacion::create([
                'empresa_id' => $datos['empresa_id'],
                'cliente_id' => $datos['cliente_id'],
                'nombre_cliente' => $cliente ? $cliente->razon_social : 'Cliente Desconocido',
                'numero_cotizacion' => $datos['numero_cotizacion'] ?? 'COT-' . time(),
                'fecha_emision' => $fechaEmision,
                'fecha_validez' => $fechaValidez,
                'validez' => $validezDias,
                'subtotal' => $subtotalCalculado,
                'porcentaje_descuento' => $porcentajeDescuento,
                'monto_descuento' => $montoDescuento,
                'monto_neto' => $montoNeto,
                'porcentaje_iva' => $porcentajeIva,
                'monto_iva' => $montoIva,
                'monto_total' => $montoTotal,
                'total' => $montoTotal,
                'estado_id' => $datos['estado_id'] ?? 1,
                'es_afecta' => true,
                'notas_condiciones' => $datos['notas_condiciones'] ?? null,
            ]);

            foreach ($detalles as $detalle) {
                $subtotalLinea = $detalle['cantidad'] * $detalle['precio_unitario'];

                CotizacionDetalle::create([
                    'cotizacion_id' => $cotizacion->id,
                    'producto_nombre' => $detalle['producto_nombre'],
                    'descripcion' => $detalle['descripcion'] ?? null,
                    'cantidad' => $detalle['cantidad'],
                    'precio_unitario' => $detalle['precio_unitario'],
                    'subtotal' => $subtotalLinea,
                ]);
            }

            return $cotizacion->load(['cliente', 'detalles', 'estado']);
        });
    }

    public function obtenerPorId(int $empresaId, int $id)
    {
        $cotizacion = Cotizacion::where('empresa_id', $empresaId)
            ->with(['cliente', 'estado', 'detalles'])
            ->find($id);

        if (!$cotizacion) {
            throw new Exception("La cotización solicitada no existe o no pertenece a su empresa.");
        }

        return $cotizacion;
    }
}