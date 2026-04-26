<?php

namespace App\Domains\Comercial\Services;

use App\Domains\Comercial\Models\Cotizacion;
use App\Domains\Comercial\Models\CotizacionDetalle;
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
            $cotizacion = Cotizacion::create([
                'empresa_id'           => $datos['empresa_id'],
                'cliente_id'           => $datos['cliente_id'],
                'numero_cotizacion'    => $datos['numero_cotizacion'] ?? 'COT-' . time(),
                'fecha_emision'        => $datos['fecha_emision'],
                'fecha_validez'        => $datos['fecha_validez'],
                'subtotal'             => $datos['subtotal'],
                'porcentaje_descuento' => $datos['porcentaje_descuento'] ?? 0,
                'monto_descuento'      => $datos['monto_descuento'] ?? 0,
                'monto_neto'           => $datos['monto_neto'],
                'porcentaje_iva'       => $datos['porcentaje_iva'] ?? 19,
                'monto_iva'            => $datos['monto_iva'],
                'monto_total'          => $datos['monto_total'],
                'estado_id'            => $datos['estado_id'] ?? 1,
                'notas_condiciones'    => $datos['notas_condiciones'] ?? null,
            ]);

            foreach ($detalles as $detalle) {
                CotizacionDetalle::create([
                    'cotizacion_id'   => $cotizacion->id,
                    'descripcion'     => $detalle['descripcion'],
                    'cantidad'        => $detalle['cantidad'],
                    'precio_unitario' => $detalle['precio_unitario'],
                    'subtotal'        => $detalle['subtotal'],
                ]);
            }

            return $cotizacion->load(['cliente', 'detalles', 'estado']);
        });
    }
}