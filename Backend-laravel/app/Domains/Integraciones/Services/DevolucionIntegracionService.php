<?php

namespace App\Domains\Integraciones\Services;

use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\FacturaDetalle;
use App\Domains\Comercial\Services\FacturaService;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\User;
use App\Domains\Core\Services\ContadorEmpresaService;
use App\Domains\Integraciones\Exceptions\DevolucionIntegracionException;
use App\Domains\Integraciones\Models\IntegracionDevolucionIdempotencia;
use App\Domains\Inventario\Exceptions\InventarioException;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\InventarioDevolucionDetalle;
use App\Domains\Inventario\Models\InventarioDevolucionOrden;
use App\Domains\Inventario\Models\MovimientoInventario;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\ProductoSerie;
use App\Domains\Inventario\Services\InventarioDevolucionService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Puente Fase 4 (RMA) entre la API publica de Integraciones y el motor real de devoluciones del
 * ERP: un tercero (Tenri-Web-Page) informa que unidades de una Factura de venta se devuelven, y
 * este servicio reingresa el stock real (InventarioDevolucionService::crearDesdeOrigenExterno ->
 * InventarioMovimientoService hace la entrada), marca la(s) serie(s) devueltas si el cliente las
 * informo, y emite una Nota de Credito (tipo 61) contra la factura original con el mismo
 * mecanismo que usa el resto del ERP (FacturaService::emitirNotaCreditoVenta).
 *
 * Decision de diseno explicita (ver tarea Fase 4): la venta via Integraciones (Fase 2,
 * VentaIntegracionService::confirmar) NO captura numero_serie por unidad -- tocar ese flujo ya
 * cerrado y verificado era mas riesgoso que el beneficio. La asociacion serie<->venta se completa
 * aca, en el momento de la devolucion: si el tercero informa numero_serie y el ERP no tenia esa
 * fila (porque nunca se registro en la venta), se crea recien aca, directamente en estado
 * "devuelto", con venta_referencia = factura_id como mejor esfuerzo. No es trazabilidad perfecta
 * unidad-por-unidad desde el origen, pero cubre el caso real de RMA sin bloquear la operacion.
 *
 * Mismo patron de actor no persistido que VentaIntegracionService::actorSistema() -- ver ese
 * docblock para el detalle de por que existe.
 *
 * `tipo` (`retracto`|`garantia`, opcional): distingue si la devolucion es un retracto (Ley del
 * Consumidor, obliga a restituir todo lo pagado incluido el despacho) o una garantia (solo el
 * producto). Solo `retracto` con devolucion TOTAL de la factura suma la linea de despacho a la
 * Nota de Credito -- ver el bloque que la calcula mas abajo para el detalle del caso parcial.
 *
 * `incluir_despacho` (bool, opcional): un pedido web de N productos genera N facturas separadas
 * (una por SKU, ver Fase 2 de VentaIntegracionService), asi que este servicio no puede saber por
 * si solo si la factura que recibe es todo el pedido original o solo una parte -- el canal
 * externo (Tenri-Web-Page) es quien conoce el pedido completo y decide explicitamente si el
 * despacho corresponde. Si viene informado, reemplaza la decision de "incluir despacho" pero
 * SIEMPRE sujeta a que la factura se devuelva completa (ver bloque mas abajo); si no viene, se
 * mantiene el comportamiento historico (retracto + devolucion total de la factura => incluye).
 *
 * `solo_despacho` (bool, opcional): la linea de despacho de un pedido multi-linea vive SIEMPRE en
 * la factura de la PRIMERA linea confirmada del pedido (ver Fase 2), pero el mecanismo de arriba
 * (`incluir_despacho`) solo puede sumar el despacho cuando la devolucion es TOTAL sobre esa MISMA
 * factura -- si el retracto se completa devolviendo productos de OTRAS facturas del mismo pedido,
 * la factura #1 nunca recibe items propios y su despacho queda sin devolver. Este modo permite al
 * canal externo (que si conoce el pedido completo) pedir una devolucion "solo despacho" contra esa
 * factura especifica, sin items ni movimiento de inventario -- ver `crearSoloDespacho()`.
 */
class DevolucionIntegracionService
{
    public function __construct(
        private readonly InventarioDevolucionService $devolucionService,
        private readonly FacturaService $facturaService,
        private readonly ContadorEmpresaService $contadorService,
    ) {}

    /** @return array{devolucion_id: int|null, nota_credito_id: int|null, estado: string} */
    public function crear(int $empresaId, array $datos, ?string $idempotencyKey): array
    {
        $soloDespacho = array_key_exists('solo_despacho', $datos) && $datos['solo_despacho'] !== null
            ? (bool) $datos['solo_despacho']
            : false;

        $clave = $this->resolverClaveIdempotencia($idempotencyKey, $datos, $soloDespacho);

        $existente = IntegracionDevolucionIdempotencia::where('empresa_id', $empresaId)
            ->where('clave', $clave)
            ->first();

        if ($existente !== null) {
            return array_merge($existente->respuesta_json, ['idempotente' => true]);
        }

        return DB::transaction(function () use ($empresaId, $datos, $clave, $soloDespacho) {
            $facturaId = (int) ($datos['factura_id'] ?? 0);

            /** @var Factura|null $factura */
            $factura = Factura::where('empresa_id', $empresaId)
                ->where('tipo', 'VENTA')
                ->where('id', $facturaId)
                ->lockForUpdate()
                ->first();

            if (! $factura) {
                throw ValidationException::withMessages([
                    'factura_id' => 'La factura no existe, no pertenece a la empresa, o no es una factura de venta.',
                ]);
            }

            if ($factura->estado === 'ANULADA') {
                throw ValidationException::withMessages([
                    'factura_id' => 'No se puede devolver sobre una factura ya anulada.',
                ]);
            }

            if ($soloDespacho) {
                return $this->crearSoloDespacho($empresaId, $factura, $datos, $clave);
            }

            $items = $datos['items'] ?? [];
            if (empty($items)) {
                throw ValidationException::withMessages(['items' => 'Debe informar al menos un item a devolver.']);
            }

            $tipo = $datos['tipo'] ?? null;
            $incluirDespachoSolicitado = array_key_exists('incluir_despacho', $datos) && $datos['incluir_despacho'] !== null
                ? (bool) $datos['incluir_despacho']
                : null;

            $detallesFactura = FacturaDetalle::where('factura_id', $factura->id)
                ->whereNotNull('producto_id')
                ->get()
                ->keyBy('producto_id');

            $bodega = $this->bodegaVentaPorDefecto($empresaId);

            $itemsDevolucion = [];
            $montoNetoTotal = 0.0;
            $montoIvaTotal = 0.0;
            $seriesAConfirmar = [];
            $productosEnEstaDevolucion = [];
            $todasLasLineasDevueltasCompletas = true;

            foreach ($items as $indice => $item) {
                $sku = trim((string) ($item['sku'] ?? ''));
                $cantidad = (float) ($item['cantidad'] ?? 0);

                if ($sku === '' || $cantidad <= 0) {
                    throw ValidationException::withMessages(["items.{$indice}" => 'Cada item debe informar sku y cantidad mayor a cero.']);
                }

                $producto = Producto::where('empresa_id', $empresaId)->where('sku', $sku)->first();

                if ($producto === null) {
                    throw ValidationException::withMessages(["items.{$indice}.sku" => 'El sku informado no existe o no pertenece a la empresa.']);
                }

                /** @var FacturaDetalle|null $detalleFactura */
                $detalleFactura = $detallesFactura->get($producto->id);

                if ($detalleFactura === null) {
                    throw ValidationException::withMessages(["items.{$indice}.sku" => 'El producto no fue vendido en la factura indicada.']);
                }

                $numeroSerie = isset($item['numero_serie']) ? trim((string) $item['numero_serie']) : null;
                $numeroSerie = $numeroSerie !== '' ? $numeroSerie : null;

                if ($producto->requiereSerie() && $numeroSerie === null) {
                    throw ValidationException::withMessages([
                        "items.{$indice}.numero_serie" => 'El producto requiere número de serie para procesar la devolución.',
                    ]);
                }

                $vendida = round((float) $detalleFactura->cantidad, 4);
                $yaDevuelta = $this->cantidadYaDevuelta($empresaId, $factura->id, $producto->id);
                $pendiente = round($vendida - $yaDevuelta, 4);

                if (round($cantidad, 4) > $pendiente + 0.0001) {
                    throw ValidationException::withMessages([
                        "items.{$indice}.cantidad" => "No se puede devolver más cantidad de la vendida/pendiente (pendiente: {$pendiente}).",
                    ]);
                }

                $loteId = null;
                if ($producto->manejaLotes()) {
                    $loteId = $this->resolverLoteReingreso($empresaId, (int) $producto->id);

                    if ($loteId === null) {
                        throw ValidationException::withMessages([
                            "items.{$indice}.sku" => 'No fue posible determinar el lote de reingreso para este producto (maneja lotes por partida, no hay historial de venta vía integraciones para inferirlo).',
                        ]);
                    }
                }

                $itemsDevolucion[] = [
                    'producto_id' => $producto->id,
                    'cantidad' => $cantidad,
                    'lote_id' => $loteId,
                    'costo_unitario' => (float) $producto->costo_promedio,
                    'motivo' => $this->normalizarTexto($item['motivo'] ?? null, 120),
                    'cantidad_vendida' => $vendida,
                    'cantidad_ya_devuelta' => $yaDevuelta,
                ];

                // Precio unitario derivado UNA sola vez del monto de linea real (no de un
                // "proporcion" recalculado por separado para neto e iva): mismo criterio que
                // VentaIntegracionService::resolverMontoNetoLinea, el monto de la devolucion se
                // redondea una sola vez sobre el total de esta linea, sin arrastrar redondeos
                // intermedios independientes entre el neto y el iva de la misma linea.
                $precioUnitarioNeto = $vendida > 0 ? (float) $detalleFactura->monto_item / $vendida : 0.0;
                $montoNetoLinea = round($precioUnitarioNeto * $cantidad, 2);
                $montoNetoTotal += $montoNetoLinea;

                if (! $detalleFactura->exento) {
                    $montoIvaTotal += round($montoNetoLinea * (float) config('fiscal.tasa_iva'), 2);
                }

                if ($numeroSerie !== null) {
                    $seriesAConfirmar[] = ['producto_id' => (int) $producto->id, 'numero_serie' => $numeroSerie];
                }

                $productosEnEstaDevolucion[] = (int) $producto->id;
                if (round($cantidad, 4) < $pendiente - 0.0001) {
                    $todasLasLineasDevueltasCompletas = false;
                }
            }

            // Retracto (Ley del Consumidor: restituir todo lo pagado sin retener gastos, incluido
            // el despacho) solo suma la linea de despacho si esta devolucion es TOTAL: cubre todos
            // los productos vendidos en la factura y cada uno se devuelve completo. Para un
            // retracto PARCIAL (ej. 2 de 3 productos distintos, no solo cantidad parcial de un
            // mismo producto) la ley no define con claridad si el despacho se prorratea, se
            // devuelve completo o no se devuelve -- se deja deliberadamente afuera (politica
            // conservadora) hasta que exista una decision de negocio explicita. Garantia nunca
            // incluye despacho, sea completa o parcial.
            if ($tipo === 'retracto' && $todasLasLineasDevueltasCompletas) {
                $productosFactura = $detallesFactura->keys()->map(fn ($id) => (int) $id)->all();
                $esDevolucionTotalDeLaFactura = empty(array_diff($productosFactura, $productosEnEstaDevolucion));

                // incluir_despacho es una señal del canal externo, no una orden -- la factura
                // siempre debe estar completamente devuelta para que el despacho pueda
                // incluirse, sin importar lo que pida el caller. Si no viene informado se
                // preserva el comportamiento historico (retracto + devolucion total => incluye).
                if ($esDevolucionTotalDeLaFactura && ($incluirDespachoSolicitado ?? true)) {
                    $detalleDespacho = FacturaDetalle::where('factura_id', $factura->id)
                        ->whereNull('producto_id')
                        ->first();

                    if ($detalleDespacho !== null) {
                        $montoNetoDespacho = round((float) $detalleDespacho->monto_item, 2);
                        $montoNetoTotal += $montoNetoDespacho;

                        if (! $detalleDespacho->exento) {
                            $montoIvaTotal += round($montoNetoDespacho * (float) config('fiscal.tasa_iva'), 2);
                        }
                    }
                }
            }

            $orden = $this->devolucionService->crearDesdeOrigenExterno($this->actorSistema($empresaId), [
                'bodega_id' => $bodega->id,
                'motivo' => 'devolucion_integraciones',
                'referencia' => $factura->numero_factura,
                'observacion' => 'Devolución RMA vía integración sobre factura '.$factura->numero_factura.'.',
                'origen_modulo' => 'integraciones_devolucion',
                'origen_id' => $factura->id,
                'items' => $itemsDevolucion,
            ]);

            foreach ($seriesAConfirmar as $serie) {
                $this->marcarSerieDevuelta($empresaId, $serie['producto_id'], $serie['numero_serie'], (int) $factura->id);
            }

            $montoNetoTotal = round($montoNetoTotal, 2);
            $montoIvaTotal = round($montoIvaTotal, 2);
            $montoBrutoTotal = round($montoNetoTotal + $montoIvaTotal, 2);

            $notaCreditoId = null;

            if ($montoNetoTotal > 0) {
                $numeroNc = sprintf('NC-INT-%06d', $this->contadorService->siguienteNumero($empresaId, 'nota_credito_integracion'));

                $nc = $this->facturaService->emitirNotaCreditoVenta($empresaId, (int) $factura->id, [
                    'numero_nc' => $numeroNc,
                    'monto_neto' => $montoNetoTotal,
                    'monto_iva' => $montoIvaTotal,
                    'monto_bruto' => $montoBrutoTotal,
                    'razon' => 'Devolución RMA vía integración - orden '.$orden->codigo,
                    'emitir_dte' => true,
                ]);

                $notaCreditoId = (int) $nc->id;
            }

            $respuesta = [
                'devolucion_id' => (int) $orden->id,
                'nota_credito_id' => $notaCreditoId,
                'estado' => $orden->estado,
            ];

            try {
                IntegracionDevolucionIdempotencia::create([
                    'empresa_id' => $empresaId,
                    'clave' => $clave,
                    'devolucion_orden_id' => $orden->id,
                    'respuesta_status' => 201,
                    'respuesta_json' => $respuesta,
                ]);
            } catch (QueryException $e) {
                // Misma carrera que VentaIntegracionService::confirmar: si otra request con la
                // misma clave gano el insert primero, toda esta transaccion (devolucion,
                // movimiento, NC) se revierte y el caller debe reintentar.
                throw DevolucionIntegracionException::idempotenciaEnCarrera($e);
            }

            return $respuesta;
        });
    }

    /**
     * Devolucion "solo despacho": ninguna interaccion con Inventario (no hay items, no se toca
     * stock/series/lotes), solo emite una NC por el monto exacto de la linea de despacho de ESTA
     * factura. Solo aplica a retracto, mismo espiritu que `incluir_despacho`.
     *
     * Deduplicacion sin flag nuevo: se reutiliza la regla ya existente de
     * FacturaService::emitirNotaCreditoVenta de que una factura solo puede tener una Nota de
     * Credito activa a la vez. Un segundo intento de devolver el despacho de la misma factura
     * (sin el mismo Idempotency-Key que el primero) choca con esa regla y se rechaza con un
     * mensaje claro -- no genera una segunda NC.
     *
     * @return array{devolucion_id: null, nota_credito_id: int, estado: string}
     */
    private function crearSoloDespacho(int $empresaId, Factura $factura, array $datos, string $clave): array
    {
        $tipo = $datos['tipo'] ?? null;

        if ($tipo !== 'retracto') {
            throw ValidationException::withMessages([
                'tipo' => 'El modo solo_despacho requiere tipo=retracto.',
            ]);
        }

        $detalleDespacho = FacturaDetalle::where('factura_id', $factura->id)
            ->whereNull('producto_id')
            ->first();

        if ($detalleDespacho === null) {
            throw ValidationException::withMessages([
                'factura_id' => 'Esta factura no tiene una línea de despacho para devolver.',
            ]);
        }

        $montoNetoDespacho = round((float) $detalleDespacho->monto_item, 2);

        if ($montoNetoDespacho <= 0) {
            throw ValidationException::withMessages([
                'factura_id' => 'La línea de despacho de esta factura no tiene un monto válido para devolver.',
            ]);
        }

        $montoIvaDespacho = $detalleDespacho->exento
            ? 0.0
            : round($montoNetoDespacho * (float) config('fiscal.tasa_iva'), 2);
        $montoBrutoDespacho = round($montoNetoDespacho + $montoIvaDespacho, 2);

        $numeroNc = sprintf('NC-INT-%06d', $this->contadorService->siguienteNumero($empresaId, 'nota_credito_integracion'));

        $nc = $this->facturaService->emitirNotaCreditoVenta($empresaId, (int) $factura->id, [
            'numero_nc' => $numeroNc,
            'monto_neto' => $montoNetoDespacho,
            'monto_iva' => $montoIvaDespacho,
            'monto_bruto' => $montoBrutoDespacho,
            'razon' => 'Devolución de despacho vía integración (retracto multi-línea) - factura '.$factura->numero_factura,
            'emitir_dte' => true,
        ]);

        $respuesta = [
            'devolucion_id' => null,
            'nota_credito_id' => (int) $nc->id,
            'estado' => InventarioDevolucionOrden::ESTADO_CONFIRMADA,
        ];

        try {
            IntegracionDevolucionIdempotencia::create([
                'empresa_id' => $empresaId,
                'clave' => $clave,
                'devolucion_orden_id' => null,
                'respuesta_status' => 201,
                'respuesta_json' => $respuesta,
            ]);
        } catch (QueryException $e) {
            // Misma carrera que en el flujo normal: si otra request con la misma clave gano el
            // insert primero, la NC recien creada se revierte y el caller debe reintentar.
            throw DevolucionIntegracionException::idempotenciaEnCarrera($e);
        }

        return $respuesta;
    }

    private function cantidadYaDevuelta(int $empresaId, int $facturaId, int $productoId): float
    {
        $cantidad = InventarioDevolucionDetalle::query()
            ->join('inventario_devolucion_ordenes as orden', 'orden.id', '=', 'inventario_devolucion_detalles.devolucion_orden_id')
            ->where('inventario_devolucion_detalles.empresa_id', $empresaId)
            ->where('orden.origen_modulo', 'integraciones_devolucion')
            ->where('orden.origen_id', $facturaId)
            ->where('inventario_devolucion_detalles.producto_id', $productoId)
            ->where('orden.estado', InventarioDevolucionOrden::ESTADO_CONFIRMADA)
            ->sum('inventario_devolucion_detalles.cantidad_devolver');

        return round((float) $cantidad, 4);
    }

    /**
     * Best-effort: no existe hoy un vinculo exacto movimiento<->venta por item (Fase 2 no
     * registra numero_serie ni un id de venta en el movimiento de salida), asi que se toma el
     * lote de la salida "venta_integracion" mas reciente para ese producto. Documentado como
     * limitacion conocida en la tarea de Fase 4: razonable para el caso comun (un lote activo a
     * la vez), no es trazabilidad exacta por unidad.
     */
    private function resolverLoteReingreso(int $empresaId, int $productoId): ?int
    {
        $movimiento = MovimientoInventario::where('empresa_id', $empresaId)
            ->where('producto_id', $productoId)
            ->where('tipo', MovimientoInventario::TIPO_SALIDA)
            ->where('motivo', 'venta_integracion')
            ->with('lotes')
            ->orderByDesc('fecha_movimiento')
            ->orderByDesc('id')
            ->first();

        $loteId = $movimiento?->lotes->first()?->lote_id;

        return $loteId !== null ? (int) $loteId : null;
    }

    private function marcarSerieDevuelta(int $empresaId, int $productoId, string $numeroSerie, int $facturaId): void
    {
        $serie = ProductoSerie::where('empresa_id', $empresaId)
            ->where('producto_id', $productoId)
            ->where('numero_serie', $numeroSerie)
            ->lockForUpdate()
            ->first();

        if ($serie === null) {
            ProductoSerie::create([
                'empresa_id' => $empresaId,
                'producto_id' => $productoId,
                'numero_serie' => $numeroSerie,
                'estado' => ProductoSerie::ESTADO_DEVUELTO,
                'venta_referencia' => (string) $facturaId,
            ]);

            return;
        }

        $serie->update([
            'estado' => ProductoSerie::ESTADO_DEVUELTO,
            'venta_referencia' => $serie->venta_referencia ?? (string) $facturaId,
        ]);
    }

    private function bodegaVentaPorDefecto(int $empresaId): Bodega
    {
        $bodega = Bodega::where('empresa_id', $empresaId)
            ->where('estado', 'ACTIVA')
            ->orderBy('id')
            ->first();

        if ($bodega === null) {
            throw InventarioException::regla('No hay ninguna bodega activa configurada en Inventario para reingresar stock.');
        }

        return $bodega;
    }

    private function resolverClaveIdempotencia(?string $idempotencyKey, array $datos, bool $soloDespacho = false): string
    {
        $clave = is_string($idempotencyKey) ? trim($idempotencyKey) : null;

        if ($clave !== null && $clave !== '') {
            return $clave;
        }

        if ($soloDespacho) {
            return 'devolucion-despacho-'.($datos['factura_id'] ?? '0');
        }

        return 'devolucion-'.($datos['factura_id'] ?? '0').'-'.md5(json_encode($datos['items'] ?? []) ?: '');
    }

    private function normalizarTexto(mixed $valor, int $max): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return mb_substr(trim((string) $valor), 0, $max);
    }

    /**
     * Actor no persistido (User::exists = false) usado solo para satisfacer las firmas de
     * InventarioDevolucionService/InventarioPermisoService; ver docblock de la clase.
     */
    private function actorSistema(int $empresaId): User
    {
        $usuario = new User;
        $usuario->forceFill([
            'empresa_id' => $empresaId,
            'empresa_activa_id' => $empresaId,
        ]);

        $rol = new Rol;
        $rol->forceFill([
            'nombre' => 'Integración API (sistema)',
            'jerarquia' => 100,
            'permisos' => [],
        ]);

        $usuario->setRelation('rol', $rol);

        return $usuario;
    }
}
