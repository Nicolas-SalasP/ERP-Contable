<?php

namespace App\Domains\Comercial\Services;

use App\Domains\Comercial\Exceptions\ComercialException;

use App\Domains\Comercial\Models\Cotizacion;
use App\Domains\Comercial\Models\CotizacionDetalle;
use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\EstadoCotizacion;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Contabilidad\Services\AsientoContableService;
use Illuminate\Support\Facades\DB;

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

            // Defensa en profundidad: ademas del EmpresaScope, filtramos explicitamente
            // por la empresa del documento para no depender solo del scope global.
            $cliente = Cliente::where('empresa_id', $datos['empresa_id'])
                ->find($datos['cliente_id']);

            if (!$cliente || $cliente->estado === 'INACTIVO') {
                throw ComercialException::regla("No se puede emitir una cotización a un cliente inactivo.");
            }

            if (!empty($datos['numero_cotizacion'])) {
                $existe = Cotizacion::where('empresa_id', $datos['empresa_id'])
                    ->where('numero_cotizacion', $datos['numero_cotizacion'])
                    ->exists();
                if ($existe) {
                    throw ComercialException::regla("El número de cotización {$datos['numero_cotizacion']} ya existe.");
                }
            }

            $subtotalCalculado = 0;
            foreach ($detalles as $det) {
                $subtotalCalculado += ($det['cantidad'] * $det['precio_unitario']);
            }

            $porcentajeDescuento = $datos['porcentaje_descuento'] ?? 0;
            $montoDescuento = round($subtotalCalculado * ($porcentajeDescuento / 100));
            $montoNeto = $subtotalCalculado - $montoDescuento;
            $esAfecta = isset($datos['es_afecta']) ? (bool) $datos['es_afecta'] : true;
            $porcentajeIva = $esAfecta ? ($datos['porcentaje_iva'] ?? 19) : 0;
            $montoIva = $esAfecta ? round($montoNeto * ($porcentajeIva / 100)) : 0;
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
                'es_afecta' => $esAfecta,
                'notas_condiciones' => $datos['notas_condiciones'] ?? null,
            ]);

            if (!isset($datos['numero_cotizacion'])) {
                $cotizacion->update([
                    'numero_cotizacion' => 'COT-' . str_pad($cotizacion->id, 6, '0', STR_PAD_LEFT)
                ]);
            }

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
            throw ComercialException::noEncontrado("La cotización solicitada no existe o no pertenece a su empresa.");
        }

        return $cotizacion;
    }

    public function actualizarEstado(int $empresaId, int $id, string $nombreEstado)
    {
        $cotizacion = Cotizacion::where('empresa_id', $empresaId)->find($id);

        if (!$cotizacion) {
            throw ComercialException::noEncontrado("La cotización solicitada no existe o no pertenece a su empresa.");
        }

        $estado = EstadoCotizacion::where('nombre', $nombreEstado)->first();

        if (!$estado) {
            throw ComercialException::regla("El estado '$nombreEstado' no es válido en el sistema.");
        }

        $cotizacion->estado_id = $estado->id;
        $cotizacion->save();

        return $cotizacion->load(['cliente', 'estado']);
    }

    public function actualizarCotizacion(int $empresaId, int $cotiId, array $datos)
    {
        return DB::transaction(function () use ($empresaId, $cotiId, $datos) {
            $cotizacion = Cotizacion::where('empresa_id', $empresaId)->findOrFail($cotiId);

            if (in_array($cotizacion->estado_id, [3, 5])) {
                throw ComercialException::regla("No se puede editar una cotización que ya ha sido aprobada o facturada.");
            }

            if (isset($datos['fecha_validez'])) {
                $cotizacion->fecha_validez = $datos['fecha_validez'];
            }
            if (isset($datos['porcentaje_descuento'])) {
                $cotizacion->porcentaje_descuento = $datos['porcentaje_descuento'];
            }

            if (isset($datos['detalles'])) {
                $cotizacion->detalles()->delete();

                $subtotal = 0;
                foreach ($datos['detalles'] as $item) {
                    $lineSubtotal = $item['cantidad'] * $item['precio_unitario'];
                    $subtotal += $lineSubtotal;

                    $cotizacion->detalles()->create([
                        'producto_nombre' => $item['producto_nombre'],
                        'cantidad' => $item['cantidad'],
                        'precio_unitario' => $item['precio_unitario'],
                        'subtotal' => $lineSubtotal
                    ]);
                }

                $descuento = $subtotal * (($cotizacion->porcentaje_descuento ?? 0) / 100);
                $neto = $subtotal - $descuento;
                $iva = $cotizacion->es_afecta ? round($neto * (($cotizacion->porcentaje_iva ?? 19) / 100)) : 0;

                $cotizacion->subtotal = $subtotal;
                $cotizacion->monto_descuento = $descuento;
                $cotizacion->monto_neto = $neto;
                $cotizacion->monto_iva = $iva;
                $cotizacion->monto_total = $neto + $iva;
                $cotizacion->total = $neto + $iva;
            }

            $cotizacion->save();

            return $cotizacion->load('detalles');
        });
    }

    public function convertirEnFactura(int $empresaId, int $cotizacionId): Factura
    {
        return DB::transaction(function () use ($empresaId, $cotizacionId) {
            $cotizacion = Cotizacion::where('empresa_id', $empresaId)
                ->with('estado', 'cliente')
                ->find($cotizacionId);

            if (!$cotizacion) {
                throw ComercialException::noEncontrado("Cotizacion no encontrada.");
            }

            $estadoAprobada = EstadoCotizacion::where('nombre', 'Aprobada')->first();
            if (!$estadoAprobada || $cotizacion->estado_id !== $estadoAprobada->id) {
                throw ComercialException::regla(
                    "Solo cotizaciones APROBADAS pueden ser facturadas. " .
                    "Estado actual: " . ($cotizacion->estado->nombre ?? '?')
                );
            }

            $cliente = $cotizacion->cliente;
            if (!$cliente) {
                throw ComercialException::regla("Cotizacion no tiene cliente vinculado.");
            }

            $proveedor = \App\Domains\Comercial\Models\Proveedor::where('empresa_id', $empresaId)
                ->where('rut', $cliente->rut)
                ->first();

            if (!$proveedor) {
                $proveedor = \App\Domains\Comercial\Models\Proveedor::create([
                    'empresa_id' => $empresaId,
                    'rut' => $cliente->rut,
                    'razon_social' => $cliente->razon_social,
                    'codigo_interno' => 'CLI-' . $cliente->id,
                    'pais_iso' => 'CL',
                    'moneda_defecto' => 'CLP',
                ]);
            }

            $codigoUnico = Factura::generarCodigoUnico();
            $factura = Factura::create([
                'empresa_id' => $empresaId,
                'codigo_unico' => $codigoUnico,
                'proveedor_id' => $proveedor->id,
                'numero_factura' => 'FV-' . $cotizacion->numero_cotizacion,
                'tipo' => 'VENTA',
                'tipo_documento' => 'FACTURA',
                'fecha_emision' => date('Y-m-d'),
                'monto_neto' => $cotizacion->monto_neto,
                'monto_iva' => $cotizacion->monto_iva,
                'monto_bruto' => $cotizacion->monto_total ?? $cotizacion->total,
                'estado' => 'REGISTRADA',
            ]);

            // Centralización contable de la VENTA (antes la factura de venta no
            // impactaba el mayor): DEBE Cuentas por Cobrar al cliente; HABER
            // Ingresos por Venta + IVA Débito Fiscal.
            $neto = (float) $cotizacion->monto_neto;
            $iva = (float) $cotizacion->monto_iva;
            $totalCxC = $neto + $iva;

            $cuentaCxC = PlanCuenta::where('empresa_id', $empresaId)->where('codigo', '152005')->first();
            $cuentaVentas = PlanCuenta::where('empresa_id', $empresaId)->where('codigo', '501105')->first();
            $cuentaIvaDebito = PlanCuenta::where('empresa_id', $empresaId)->where('codigo', '353360')->first();

            if (!$cuentaCxC || !$cuentaVentas || !$cuentaIvaDebito) {
                throw ComercialException::regla(
                    "Configuración Contable Incompleta: para facturar una venta se requieren las cuentas de Clientes (152005), Ventas (501105) e IVA Débito (353360) en el plan de cuentas."
                );
            }

            $detallesVenta = [
                ['cuenta_contable' => '152005', 'debe' => $totalCxC, 'haber' => 0, 'glosa_detalle' => "CxC Cliente Venta {$factura->numero_factura}"],
                ['cuenta_contable' => '501105', 'debe' => 0, 'haber' => $neto, 'glosa_detalle' => "Ingreso por Venta {$factura->numero_factura}"],
            ];
            if ($iva > 0) {
                $detallesVenta[] = ['cuenta_contable' => '353360', 'debe' => 0, 'haber' => $iva, 'glosa_detalle' => "IVA Débito Venta {$factura->numero_factura}"];
            }

            $asientoVenta = app(AsientoContableService::class)->registrarAsiento([
                'empresa_id' => $empresaId,
                'fecha' => date('Y-m-d'),
                'glosa' => "Centralización Venta - {$factura->numero_factura}",
                'tipo_asiento' => 'ingreso',
                'origen_modulo' => 'ventas',
                'origen_id' => $factura->id,
            ], $detallesVenta);

            $factura->update(['comprobante_contable' => $asientoVenta->numero_comprobante]);

            $estadoFacturada = EstadoCotizacion::where('nombre', 'Facturada')->first();
            if ($estadoFacturada) {
                $cotizacion->estado_id = $estadoFacturada->id;
                $cotizacion->save();
            }

            return $factura;
        });
    }
}