<?php

namespace App\Domains\Comercial\Services;

use App\Domains\Comercial\Exceptions\ComercialException;
use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Cotizacion;
use App\Domains\Comercial\Models\CotizacionDetalle;
use App\Domains\Comercial\Models\EstadoCotizacion;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Contabilidad\Services\AsientoContableService;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\MovimientoInventario;
use App\Domains\Inventario\Services\InventarioMovimientoService;
use Illuminate\Support\Facades\DB;

class CotizacionService
{
    public function obtenerPorEmpresa(int $empresaId, int $perPage = 100)
    {
        // Paginado server-side: ->get() sin límite cargaba TODAS las cotizaciones en memoria en empresas con histórico grande.
        return Cotizacion::where('empresa_id', $empresaId)
            ->with(['cliente', 'estado'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function crearCotizacion(array $datos, array $detalles): Cotizacion
    {
        return DB::transaction(function () use ($datos, $detalles) {

            // Defensa en profundidad: no depender solo del EmpresaScope global.
            $cliente = Cliente::where('empresa_id', $datos['empresa_id'])
                ->find($datos['cliente_id']);

            if (! $cliente || $cliente->estado === 'INACTIVO') {
                throw ComercialException::regla('No se puede emitir una cotización a un cliente inactivo.');
            }

            if (! empty($datos['numero_cotizacion'])) {
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
            $porcentajeIva = $esAfecta ? ($datos['porcentaje_iva'] ?? (config('fiscal.tasa_iva') * 100)) : 0;
            $montoIva = $esAfecta ? round($montoNeto * ($porcentajeIva / 100)) : 0;
            $montoTotal = $montoNeto + $montoIva;

            $fechaEmision = $datos['fecha_emision'] ?? date('Y-m-d');
            $validezDias = $datos['validez'] ?? 30;
            $fechaValidez = $datos['fecha_validez'] ?? date('Y-m-d', strtotime($fechaEmision.' + '.$validezDias.' days'));

            $cotizacion = Cotizacion::create([
                'empresa_id' => $datos['empresa_id'],
                'cliente_id' => $datos['cliente_id'],
                'nombre_cliente' => $cliente->razon_social,
                'numero_cotizacion' => $datos['numero_cotizacion'] ?? 'COT-'.time(),
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
                'metodo_pago' => $datos['metodo_pago'] ?? null,
                'condiciones_pago' => $datos['condiciones_pago'] ?? null,
                'plazo_entrega' => $datos['plazo_entrega'] ?? null,
                'comentarios' => $datos['comentarios'] ?? null,
                'garantia' => $datos['garantia'] ?? null,
            ]);

            if (! isset($datos['numero_cotizacion'])) {
                $cotizacion->update([
                    'numero_cotizacion' => 'COT-'.str_pad((string) $cotizacion->id, 6, '0', STR_PAD_LEFT),
                ]);
            }

            foreach ($detalles as $detalle) {
                $subtotalLinea = $detalle['cantidad'] * $detalle['precio_unitario'];

                CotizacionDetalle::create([
                    'cotizacion_id' => $cotizacion->id,
                    'producto_id' => $detalle['producto_id'] ?? null,
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

        if (! $cotizacion) {
            throw ComercialException::noEncontrado('La cotización solicitada no existe o no pertenece a su empresa.');
        }

        return $cotizacion;
    }

    public function actualizarEstado(int $empresaId, int $id, string $nombreEstado)
    {
        $cotizacion = Cotizacion::where('empresa_id', $empresaId)->find($id);

        if (! $cotizacion) {
            throw ComercialException::noEncontrado('La cotización solicitada no existe o no pertenece a su empresa.');
        }

        $estado = EstadoCotizacion::where('nombre', $nombreEstado)->first();

        if (! $estado) {
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
                throw ComercialException::regla('No se puede editar una cotización que ya ha sido aprobada o facturada.');
            }

            $camposOpcionales = ['fecha_validez', 'porcentaje_descuento', 'notas_condiciones', 'metodo_pago', 'condiciones_pago', 'plazo_entrega', 'comentarios', 'garantia'];
            foreach ($camposOpcionales as $campo) {
                if (array_key_exists($campo, $datos)) {
                    $cotizacion->$campo = $datos[$campo];
                }
            }

            if (isset($datos['detalles'])) {
                $cotizacion->detalles()->delete();

                $subtotal = 0;
                foreach ($datos['detalles'] as $item) {
                    $lineSubtotal = $item['cantidad'] * $item['precio_unitario'];
                    $subtotal += $lineSubtotal;

                    $cotizacion->detalles()->create([
                        'producto_id' => $item['producto_id'] ?? null,
                        'producto_nombre' => $item['producto_nombre'],
                        'cantidad' => $item['cantidad'],
                        'precio_unitario' => $item['precio_unitario'],
                        'subtotal' => $lineSubtotal,
                    ]);
                }

                $descuento = $subtotal * (($cotizacion->porcentaje_descuento ?? 0) / 100);
                $neto = $subtotal - $descuento;
                $iva = $cotizacion->es_afecta ? round($neto * (($cotizacion->porcentaje_iva ?? (config('fiscal.tasa_iva') * 100)) / 100)) : 0;

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

    public function convertirEnFactura(int $empresaId, int $cotizacionId, ?string $fechaEmision = null): Factura
    {
        return DB::transaction(function () use ($empresaId, $cotizacionId, $fechaEmision) {
            $fecha = $fechaEmision ?? date('Y-m-d');
            // Lock pesimista: evita que doble clic o reintento de red dupliquen la factura de venta generada.
            $cotizacion = Cotizacion::where('empresa_id', $empresaId)
                ->with('estado', 'cliente', 'detalles')
                ->lockForUpdate()
                ->find($cotizacionId);

            if (! $cotizacion) {
                throw ComercialException::noEncontrado('Cotizacion no encontrada.');
            }

            $estadoAprobada = EstadoCotizacion::where('nombre', 'Aceptada')->first();
            if (! $estadoAprobada || $cotizacion->estado_id !== $estadoAprobada->id) {
                throw ComercialException::regla(
                    'Solo cotizaciones ACEPTADAS pueden ser facturadas. '.
                    'Estado actual: '.($cotizacion->estado->nombre ?? '?')
                );
            }

            $cliente = $cotizacion->cliente;
            if (! $cliente) {
                throw ComercialException::regla('Cotizacion no tiene cliente vinculado.');
            }

            $proveedor = Proveedor::where('empresa_id', $empresaId)
                ->where('rut', $cliente->rut)
                ->lockForUpdate()
                ->first();

            if (! $proveedor) {
                $proveedor = Proveedor::create([
                    'empresa_id' => $empresaId,
                    'rut' => $cliente->rut,
                    'razon_social' => $cliente->razon_social,
                    'codigo_interno' => 'CLI-'.$cliente->id,
                    'pais_iso' => 'CL',
                    'moneda_defecto' => 'CLP',
                ]);
            }

            $codigoUnico = Factura::generarCodigoUnico();
            $factura = Factura::create([
                'empresa_id' => $empresaId,
                'codigo_unico' => $codigoUnico,
                'proveedor_id' => $proveedor->id,
                // cliente_id es la relación real usada por el mapeo a DTE SII (FacturaAComercialDteMapper) y ArAgingService; proveedor_id es solo una entidad espejo, no la reemplaza.
                'cliente_id' => $cliente->id,
                'numero_factura' => 'FV-'.$cotizacion->numero_cotizacion,
                'tipo' => 'VENTA',
                'tipo_documento' => 'FACTURA',
                'fecha_emision' => $fecha,
                'monto_neto' => $cotizacion->monto_neto,
                'monto_iva' => $cotizacion->monto_iva,
                'monto_bruto' => $cotizacion->monto_total ?? $cotizacion->total,
                'estado' => 'REGISTRADA',
                'cotizacion_id' => $cotizacion->id,
            ]);

            // Centralización contable de la VENTA: DEBE Cuentas por Cobrar al cliente; HABER Ingresos por Venta + IVA Débito Fiscal.
            $neto = (float) $cotizacion->monto_neto;
            $iva = (float) $cotizacion->monto_iva;
            $totalCxC = $neto + $iva;

            $cuentaCxC = PlanCuenta::where('empresa_id', $empresaId)->where('codigo', '152005')->first();
            $cuentaVentas = PlanCuenta::where('empresa_id', $empresaId)->where('codigo', '501105')->first();
            $cuentaIvaDebito = PlanCuenta::where('empresa_id', $empresaId)->where('codigo', '353360')->first();

            if (! $cuentaCxC || ! $cuentaVentas || ! $cuentaIvaDebito) {
                throw ComercialException::regla(
                    'Configuración Contable Incompleta: para facturar una venta se requieren las cuentas de Clientes (152005), Ventas (501105) e IVA Débito (353360) en el plan de cuentas.'
                );
            }

            $detallesVenta = [
                ['cuenta_contable' => '152005', 'debe' => $totalCxC, 'haber' => 0, 'glosa_detalle' => "CxC Cliente Venta {$factura->numero_factura}"],
                ['cuenta_contable' => '501105', 'debe' => 0, 'haber' => $neto, 'glosa_detalle' => "Ingreso por Venta {$factura->numero_factura}"],
            ];
            if ($iva > 0) {
                $detallesVenta[] = ['cuenta_contable' => '353360', 'debe' => 0, 'haber' => $iva, 'glosa_detalle' => "IVA Débito Venta {$factura->numero_factura}"];
            }

            // Salida de inventario por cada línea con producto de Inventario vinculado (producto_id).
            // Las líneas sin producto_id (servicios/ítems sin stock) no disparan nada aquí, a propósito.
            // Si InventarioMovimientoService::registrarMovimiento lanza excepción (p.ej. stock
            // insuficiente), se propaga y revierte TODA la venta (misma transacción DB::transaction
            // de este método): no queda factura, asiento ni movimiento huérfano.
            $costoVentaTotal = 0.0;
            foreach ($cotizacion->detalles as $detalleCotizacion) {
                if (empty($detalleCotizacion->producto_id)) {
                    continue;
                }

                // No existe hoy un campo de "bodega por defecto de la empresa"; se usa la primera
                // bodega activa de la empresa (ordenada por id) como bodega de salida de venta.
                // Documentado en HALLAZGOS-COLATERALES.md: decisión de menor blast radius
                // mientras no exista una configuración explícita de bodega de ventas.
                $bodegaVenta = Bodega::where('empresa_id', $empresaId)
                    ->where('estado', 'ACTIVA')
                    ->orderBy('id')
                    ->first();

                if (! $bodegaVenta) {
                    throw ComercialException::regla(
                        'No hay ninguna bodega activa configurada en Inventario para descontar el stock de esta venta.'
                    );
                }

                $movimientoSalida = app(InventarioMovimientoService::class)->registrarMovimiento([
                    'tipo' => MovimientoInventario::TIPO_SALIDA,
                    'producto_id' => $detalleCotizacion->producto_id,
                    'bodega_origen_id' => $bodegaVenta->id,
                    'cantidad' => $detalleCotizacion->cantidad,
                    'referencia' => "Venta {$factura->numero_factura}",
                    'motivo' => 'venta',
                    'origen_modulo' => 'ventas',
                    'origen_id' => $factura->id,
                ], $empresaId, auth()->id());

                $detalleCotizacion->update(['movimiento_inventario_id' => $movimientoSalida->id]);

                $costoVentaTotal += (float) $movimientoSalida->costo_total;
            }

            // Línea adicional de Costo de Venta / Costo de Mercadería Vendida (COGS): débito Costo de
            // Venta (601205, ya existente en el plan de cuentas maestro para este propósito), crédito
            // Inventario (151005, cuenta de activo de Inventario ya existente) por el costo real que
            // InventarioMovimientoService calculó (FIFO/PMP), no un estimado. Solo se agrega si hubo
            // al menos una salida de inventario asociada a esta venta.
            if ($costoVentaTotal > 0) {
                $cuentaCostoVenta = PlanCuenta::where('empresa_id', $empresaId)->where('codigo', '601205')->first();
                $cuentaInventario = PlanCuenta::where('empresa_id', $empresaId)->where('codigo', '151005')->first();

                if (! $cuentaCostoVenta || ! $cuentaInventario) {
                    throw ComercialException::regla(
                        'Configuración Contable Incompleta: para facturar una venta con productos de Inventario se requieren las cuentas de Costo de Venta (601205) e Inventario (151005) en el plan de cuentas.'
                    );
                }

                $detallesVenta[] = ['cuenta_contable' => '601205', 'debe' => $costoVentaTotal, 'haber' => 0, 'glosa_detalle' => "Costo de Venta {$factura->numero_factura}"];
                $detallesVenta[] = ['cuenta_contable' => '151005', 'debe' => 0, 'haber' => $costoVentaTotal, 'glosa_detalle' => "Salida de Inventario Venta {$factura->numero_factura}"];
            }

            $asientoVenta = app(AsientoContableService::class)->registrarAsiento([
                'empresa_id' => $empresaId,
                'fecha' => $fecha,
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
