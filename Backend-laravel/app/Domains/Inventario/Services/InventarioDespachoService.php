<?php

namespace App\Domains\Inventario\Services;

use App\Domains\Core\Models\User;
use App\Domains\Inventario\Models\InventarioDespachoDetalle;
use App\Domains\Inventario\Models\InventarioAuditoriaEvento;
use App\Domains\Inventario\Models\InventarioEventoIntegracion;
use App\Domains\Inventario\Models\InventarioDespachoOrden;
use App\Domains\Inventario\Models\InventarioPackingDetalle;
use App\Domains\Inventario\Models\InventarioPackingOrden;
use App\Domains\Inventario\Models\InventarioPickingOrden;
use App\Domains\Inventario\Models\MovimientoInventario;
use App\Domains\Inventario\Models\ReservaConsumoInventario;
use App\Domains\Inventario\Models\ReservaDetalleInventario;
use App\Domains\Inventario\Models\ReservaInventario;
use App\Domains\Inventario\Models\StockUbicacionInventario;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventarioDespachoService
{
    public function __construct(
        private readonly InventarioPermisoService $permisos,
        private readonly InventarioMovimientoService $movimientoService,
        private readonly InventarioStockUbicacionService $stockUbicacionService,
        private readonly InventarioAuditoriaService $auditoria,
        private readonly InventarioEventoIntegracionService $eventosIntegracion
    ) {
    }

    public function listar(User $usuario, array $filtros = []): LengthAwarePaginator
    {
        $this->permisos->exigir($usuario, 'inventario.despachos.ver');

        return InventarioDespachoOrden::query()
            ->where('empresa_id', $usuario->empresa_id)
            ->with([
                'bodega:id,empresa_id,codigo,nombre,estado',
                'packingOrden:id,empresa_id,picking_orden_id,bodega_id,codigo,estado,fecha_confirmacion',
                'pickingOrden:id,empresa_id,bodega_id,reserva_id,codigo,estado,referencia,prioridad',
                'reserva:id,empresa_id,codigo_reserva,estado,referencia,motivo',
                'detalles.producto:id,empresa_id,sku,nombre,maneja_lotes',
                'detalles.ubicacionOrigen:id,empresa_id,bodega_id,codigo,nombre,tipo,activo',
                'detalles.lote:id,empresa_id,producto_id,codigo_lote,fecha_vencimiento,estado_operativo,activo',
                'detalles.movimiento:id,empresa_id,tipo,cantidad,referencia,motivo,fecha_movimiento',
            ])
            ->when(!empty($filtros['estado']), fn (Builder $query) => $query->where('estado', $filtros['estado']))
            ->when(!empty($filtros['bodega_id']), fn (Builder $query) => $query->where('bodega_id', (int) $filtros['bodega_id']))
            ->when(!empty($filtros['packing_orden_id']), fn (Builder $query) => $query->where('packing_orden_id', (int) $filtros['packing_orden_id']))
            ->when(!empty($filtros['search']), function (Builder $query) use ($filtros) {
                $term = '%' . trim((string) $filtros['search']) . '%';
                $query->where(function (Builder $subQuery) use ($term) {
                    $subQuery->where('codigo', 'like', $term)
                        ->orWhere('referencia', 'like', $term)
                        ->orWhere('motivo', 'like', $term)
                        ->orWhere('observacion', 'like', $term);
                });
            })
            ->orderByDesc('fecha_creacion')
            ->orderByDesc('id')
            ->paginate($this->normalizarPerPage($filtros['per_page'] ?? 15));
    }

    public function obtener(User $usuario, int $id): InventarioDespachoOrden
    {
        $this->permisos->exigir($usuario, 'inventario.despachos.ver');

        $orden = InventarioDespachoOrden::query()
            ->where('empresa_id', $usuario->empresa_id)
            ->find($id);

        if (!$orden) {
            throw new Exception('La orden de despacho no existe o no pertenece a la empresa.');
        }

        return $this->cargarOrden($orden);
    }

    public function crearDesdePacking(User $usuario, array $datos): InventarioDespachoOrden
    {
        $this->permisos->exigir($usuario, 'inventario.despachos.crear');

        return DB::transaction(function () use ($usuario, $datos) {
            $empresaId = (int) $usuario->empresa_id;
            $packing = InventarioPackingOrden::query()
                ->where('empresa_id', $empresaId)
                ->with(['pickingOrden'])
                ->lockForUpdate()
                ->find((int) ($datos['packing_orden_id'] ?? 0));

            if (!$packing) {
                throw ValidationException::withMessages(['packing_orden_id' => 'La orden de packing no existe o no pertenece a la empresa.']);
            }

            if ($packing->estado === InventarioPackingOrden::ESTADO_CANCELADO) {
                throw ValidationException::withMessages(['packing_orden_id' => 'No se puede despachar desde un packing cancelado.']);
            }

            if ($packing->estado !== InventarioPackingOrden::ESTADO_EMPACADO) {
                throw ValidationException::withMessages(['packing_orden_id' => 'Despacho solo puede generarse desde packing empacado.']);
            }

            $picking = $packing->pickingOrden;

            if (!$picking || (int) $picking->empresa_id !== $empresaId) {
                throw ValidationException::withMessages(['picking_orden_id' => 'El picking asociado al packing no pertenece a la empresa.']);
            }

            if ($picking->estado === InventarioPickingOrden::ESTADO_CANCELADO) {
                throw ValidationException::withMessages(['picking_orden_id' => 'No se puede generar despacho desde un picking cancelado.']);
            }

            if (InventarioDespachoOrden::where('empresa_id', $empresaId)->where('packing_orden_id', $packing->id)->exists()) {
                throw ValidationException::withMessages(['packing_orden_id' => 'Ya existe una orden de despacho para este packing.']);
            }

            $detallesPacking = InventarioPackingDetalle::query()
                ->where('empresa_id', $empresaId)
                ->where('packing_orden_id', $packing->id)
                ->where('cantidad_empacada', '>', 0)
                ->with(['pickingAsignacion', 'pickingDetalle'])
                ->lockForUpdate()
                ->get();

            if ($detallesPacking->isEmpty()) {
                throw ValidationException::withMessages(['detalles' => 'No existen cantidades empacadas para despachar.']);
            }

            $orden = InventarioDespachoOrden::create([
                'empresa_id' => $empresaId,
                'packing_orden_id' => $packing->id,
                'picking_orden_id' => $picking->id,
                'reserva_id' => $picking->reserva_id,
                'bodega_id' => $packing->bodega_id,
                'codigo' => $datos['codigo'] ?? $this->generarCodigo($empresaId),
                'estado' => InventarioDespachoOrden::ESTADO_PENDIENTE,
                'prioridad' => $datos['prioridad'] ?? $picking->prioridad ?? InventarioDespachoOrden::PRIORIDAD_NORMAL,
                'referencia' => $this->textoOpcional($datos['referencia'] ?? $picking->referencia ?? null, 120),
                'motivo' => $this->textoOpcional($datos['motivo'] ?? 'despacho_interno', 120),
                'observacion' => $this->textoOpcional($datos['observacion'] ?? null, 2000),
                'origen_modulo' => $this->textoOpcional($datos['origen_modulo'] ?? 'inventario_packing', 80),
                'origen_id' => $datos['origen_id'] ?? $packing->id,
                'usuario_creador_id' => $usuario->id,
                'fecha_creacion' => now(),
            ]);

            foreach ($detallesPacking as $detallePacking) {
                /** @var InventarioPackingDetalle $detallePacking */
                $asignacion = $detallePacking->pickingAsignacion;
                $reservaDetalleId = $asignacion?->reserva_detalle_id
                    ?? $detallePacking->pickingDetalle?->reserva_detalle_id;

                InventarioDespachoDetalle::create([
                    'empresa_id' => $empresaId,
                    'despacho_orden_id' => $orden->id,
                    'packing_detalle_id' => $detallePacking->id,
                    'picking_detalle_id' => $detallePacking->picking_detalle_id,
                    'picking_asignacion_id' => $detallePacking->picking_asignacion_id,
                    'reserva_detalle_id' => $reservaDetalleId,
                    'producto_id' => $detallePacking->producto_id,
                    'bodega_id' => $packing->bodega_id,
                    'ubicacion_origen_id' => $detallePacking->ubicacion_origen_id,
                    'lote_id' => $detallePacking->lote_id,
                    'cantidad_pickeada' => $detallePacking->cantidad_pickeada,
                    'cantidad_empacada' => $detallePacking->cantidad_empacada,
                    'cantidad_despachada' => 0,
                    'cantidad_faltante' => 0,
                    'estado' => InventarioDespachoDetalle::ESTADO_PENDIENTE,
                ]);
            }

            $this->auditarDespacho($usuario, InventarioAuditoriaEvento::ACCION_DESPACHO_CREADO, $orden, 'Orden de despacho creada desde packing empacado.', [
                'packing_orden_id' => $packing->id,
                'picking_orden_id' => $picking->id,
                'total_detalles' => $detallesPacking->count(),
            ]);

            return $this->cargarOrden($orden->refresh());
        });
    }

    public function iniciar(User $usuario, int $id): InventarioDespachoOrden
    {
        $this->permisos->exigir($usuario, 'inventario.despachos.editar');

        return DB::transaction(function () use ($usuario, $id) {
            $orden = InventarioDespachoOrden::where('empresa_id', $usuario->empresa_id)->lockForUpdate()->find($id);

            if (!$orden) {
                throw new Exception('La orden de despacho no existe o no pertenece a la empresa.');
            }

            if (!$orden->puedeIniciarse()) {
                throw new Exception('La orden de despacho no puede iniciarse en su estado actual.');
            }

            $orden->update([
                'estado' => InventarioDespachoOrden::ESTADO_EN_DESPACHO,
                'fecha_inicio' => $orden->fecha_inicio ?? now(),
            ]);

            $this->auditarDespacho($usuario, InventarioAuditoriaEvento::ACCION_DESPACHO_INICIADO, $orden, 'Orden de despacho iniciada.');

            return $this->cargarOrden($orden->refresh());
        });
    }

    public function confirmar(User $usuario, int $id, array $datos = []): InventarioDespachoOrden
    {
        $this->permisos->exigir($usuario, 'inventario.despachos.confirmar');

        return DB::transaction(function () use ($usuario, $id, $datos) {
            $empresaId = (int) $usuario->empresa_id;
            $orden = InventarioDespachoOrden::where('empresa_id', $empresaId)
                ->with(['packingOrden', 'pickingOrden'])
                ->lockForUpdate()
                ->find($id);

            if (!$orden) {
                throw new Exception('La orden de despacho no existe o no pertenece a la empresa.');
            }

            if (!$orden->puedeConfirmarse()) {
                throw new Exception('La orden de despacho no puede confirmarse en su estado actual.');
            }

            if ($orden->packingOrden?->estado !== InventarioPackingOrden::ESTADO_EMPACADO) {
                throw ValidationException::withMessages(['packing_orden_id' => 'El packing asociado ya no está empacado.']);
            }

            if ($orden->pickingOrden?->estado === InventarioPickingOrden::ESTADO_CANCELADO) {
                throw ValidationException::withMessages(['picking_orden_id' => 'El picking asociado está cancelado.']);
            }

            $detalles = InventarioDespachoDetalle::where('empresa_id', $empresaId)
                ->where('despacho_orden_id', $orden->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $operaciones = $this->normalizarDetallesConfirmacion($detalles, $datos['detalles'] ?? null);
            $hayDiferencias = false;

            foreach ($operaciones as $item) {
                /** @var InventarioDespachoDetalle $detalle */
                $detalle = $item['detalle'];
                $cantidadDespachada = $item['cantidad_despachada'];
                $cantidadEmpacada = $this->redondearCantidad((float) $detalle->cantidad_empacada);

                if ($cantidadDespachada > $cantidadEmpacada + 0.0001) {
                    throw ValidationException::withMessages(['cantidad_despachada' => 'No se puede despachar más de lo empacado.']);
                }

                $faltante = $this->redondearCantidad(max(0, $cantidadEmpacada - $cantidadDespachada));
                $movimientoId = null;

                if ($cantidadDespachada > 0) {
                    $reservaDetalle = $this->obtenerReservaDetalleBloqueado($detalle);
                    $movimiento = $this->registrarSalidaDespacho($usuario, $orden, $detalle, $cantidadDespachada, $reservaDetalle, $item['observacion'] ?? null);
                    $movimientoId = (int) $movimiento->id;

                    if ($reservaDetalle) {
                        $this->consumirReservaDetalle($usuario, $orden, $detalle, $reservaDetalle, $movimiento, $cantidadDespachada);
                    }
                }

                if ($faltante > 0) {
                    $hayDiferencias = true;
                    $reservaDetalle = $this->obtenerReservaDetalleBloqueado($detalle);

                    if ($reservaDetalle) {
                        $this->liberarReservaDetalle($detalle, $reservaDetalle, $faltante);
                    }
                }

                $detalle->update([
                    'movimiento_inventario_id' => $movimientoId ?? $detalle->movimiento_inventario_id,
                    'cantidad_despachada' => $cantidadDespachada,
                    'cantidad_faltante' => $faltante,
                    'estado' => $cantidadDespachada <= 0
                        ? InventarioDespachoDetalle::ESTADO_CON_DIFERENCIAS
                        : ($faltante > 0 ? InventarioDespachoDetalle::ESTADO_PARCIAL : InventarioDespachoDetalle::ESTADO_DESPACHADO),
                    'observacion' => $item['observacion'] ?? $detalle->observacion,
                ]);
            }

            $orden->update([
                'estado' => $hayDiferencias ? InventarioDespachoOrden::ESTADO_CON_DIFERENCIAS : InventarioDespachoOrden::ESTADO_DESPACHADO,
                'usuario_confirmador_id' => $usuario->id,
                'fecha_confirmacion' => now(),
                'observacion' => array_key_exists('observacion', $datos)
                    ? $this->textoOpcional($datos['observacion'], 2000)
                    : $orden->observacion,
            ]);

            if ($orden->reserva_id !== null) {
                $reserva = ReservaInventario::where('empresa_id', $empresaId)->where('id', $orden->reserva_id)->lockForUpdate()->first();
                if ($reserva) {
                    $this->actualizarEstadoReserva($reserva);
                }
            }

            $this->auditarDespacho($usuario, InventarioAuditoriaEvento::ACCION_DESPACHO_CONFIRMADO, $orden, 'Orden de despacho confirmada con impacto de stock.', [
                'hay_diferencias' => $hayDiferencias,
                'detalles_confirmados' => count($operaciones),
            ], InventarioAuditoriaEvento::SEVERIDAD_CRITICAL);

            return $this->cargarOrden($orden->refresh());
        });
    }

    public function cancelar(User $usuario, int $id, array $datos = []): InventarioDespachoOrden
    {
        $this->permisos->exigir($usuario, 'inventario.despachos.cancelar');

        return DB::transaction(function () use ($usuario, $id, $datos) {
            $orden = InventarioDespachoOrden::where('empresa_id', $usuario->empresa_id)->lockForUpdate()->find($id);

            if (!$orden) {
                throw new Exception('La orden de despacho no existe o no pertenece a la empresa.');
            }

            if (!$orden->puedeCancelarse()) {
                throw new Exception('La orden de despacho no puede cancelarse en su estado actual.');
            }

            InventarioDespachoDetalle::where('empresa_id', $usuario->empresa_id)
                ->where('despacho_orden_id', $orden->id)
                ->where('estado', InventarioDespachoDetalle::ESTADO_PENDIENTE)
                ->update(['estado' => InventarioDespachoDetalle::ESTADO_CANCELADO]);

            $orden->update([
                'estado' => InventarioDespachoOrden::ESTADO_CANCELADO,
                'fecha_cancelacion' => now(),
                'observacion' => array_key_exists('observacion', $datos)
                    ? $this->textoOpcional($datos['observacion'], 2000)
                    : $orden->observacion,
            ]);

            $this->auditarDespacho($usuario, InventarioAuditoriaEvento::ACCION_DESPACHO_CANCELADO, $orden, 'Orden de despacho cancelada.', [
                'observacion_cancelacion' => $datos['observacion'] ?? null,
            ], InventarioAuditoriaEvento::SEVERIDAD_WARNING);

            return $this->cargarOrden($orden->refresh());
        });
    }

    public function reporte(User $usuario, array $filtros = []): array
    {
        $this->permisos->exigir($usuario, 'inventario.reportes.despachos');

        $base = InventarioDespachoOrden::query()
            ->where('empresa_id', $usuario->empresa_id)
            ->when(!empty($filtros['bodega_id']), fn (Builder $query) => $query->where('bodega_id', (int) $filtros['bodega_id']))
            ->when(!empty($filtros['desde']), fn (Builder $query) => $query->whereDate('fecha_creacion', '>=', $filtros['desde']))
            ->when(!empty($filtros['hasta']), fn (Builder $query) => $query->whereDate('fecha_creacion', '<=', $filtros['hasta']));

        $porEstado = (clone $base)
            ->select('estado', DB::raw('count(*) as total'))
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->toArray();

        $detalles = InventarioDespachoDetalle::query()
            ->where('empresa_id', $usuario->empresa_id)
            ->whereIn('despacho_orden_id', (clone $base)->pluck('id'));

        return [
            'total_ordenes' => (clone $base)->count(),
            'por_estado' => $porEstado,
            'cantidad_empacada' => $this->redondearCantidad((float) (clone $detalles)->sum('cantidad_empacada')),
            'cantidad_despachada' => $this->redondearCantidad((float) (clone $detalles)->sum('cantidad_despachada')),
            'cantidad_faltante' => $this->redondearCantidad((float) (clone $detalles)->sum('cantidad_faltante')),
            'ultimos' => (clone $base)->with('bodega:id,empresa_id,codigo,nombre,estado')->orderByDesc('fecha_creacion')->limit(10)->get(),
        ];
    }

    private function registrarSalidaDespacho(
        User $usuario,
        InventarioDespachoOrden $orden,
        InventarioDespachoDetalle $detalle,
        float $cantidad,
        ?ReservaDetalleInventario $reservaDetalle,
        ?string $observacionDetalle
    ): MovimientoInventario {
        $payload = [
            'tipo' => MovimientoInventario::TIPO_SALIDA,
            'producto_id' => (int) $detalle->producto_id,
            'bodega_origen_id' => (int) $detalle->bodega_id,
            'cantidad' => $cantidad,
            'referencia' => $orden->referencia ?? $orden->codigo,
            'motivo' => $orden->motivo ?: 'despacho_interno',
            'observacion' => $observacionDetalle
                ?: 'Salida logística interna no tributaria asociada al despacho ' . $orden->codigo,
            'fecha_movimiento' => now(),
            '_origen_operativo' => 'inventario_despacho',
        ];

        if ($detalle->lote_id !== null) {
            $payload['lote_id'] = (int) $detalle->lote_id;
        }

        if ($detalle->ubicacion_origen_id !== null) {
            $payload['ubicacion_origen_id'] = (int) $detalle->ubicacion_origen_id;
            $payload['estado_stock_origen'] = StockUbicacionInventario::ESTADO_DISPONIBLE;

            if ($reservaDetalle !== null) {
                $payload['_ubicacion_reserva_ya_controlada'] = true;
            }
        }

        return $this->movimientoService->registrarMovimiento($payload, (int) $usuario->empresa_id, (int) $usuario->id);
    }

    private function obtenerReservaDetalleBloqueado(InventarioDespachoDetalle $detalle): ?ReservaDetalleInventario
    {
        if ($detalle->reserva_detalle_id === null) {
            return null;
        }

        $reservaDetalle = ReservaDetalleInventario::where('empresa_id', $detalle->empresa_id)
            ->where('id', $detalle->reserva_detalle_id)
            ->lockForUpdate()
            ->first();

        if (!$reservaDetalle) {
            throw ValidationException::withMessages(['reserva_detalle_id' => 'El detalle de reserva asociado al despacho no existe.']);
        }

        if ((int) $reservaDetalle->producto_id !== (int) $detalle->producto_id
            || (int) $reservaDetalle->bodega_id !== (int) $detalle->bodega_id
            || (int) ($reservaDetalle->ubicacion_id ?? 0) !== (int) ($detalle->ubicacion_origen_id ?? 0)
            || (int) ($reservaDetalle->lote_id ?? 0) !== (int) ($detalle->lote_id ?? 0)) {
            throw ValidationException::withMessages(['reserva_detalle_id' => 'La reserva asociada no coincide con producto, bodega, ubicación o lote del despacho.']);
        }

        return $reservaDetalle;
    }

    private function consumirReservaDetalle(
        User $usuario,
        InventarioDespachoOrden $orden,
        InventarioDespachoDetalle $detalle,
        ReservaDetalleInventario $reservaDetalle,
        MovimientoInventario $movimiento,
        float $cantidad
    ): void {
        if (!$reservaDetalle->puedeConsumir($cantidad)) {
            throw ValidationException::withMessages(['cantidad_despachada' => 'No se puede consumir más que la cantidad pendiente de la reserva.']);
        }

        $reservaDetalle->update([
            'cantidad_consumida' => $this->redondearCantidad((float) $reservaDetalle->cantidad_consumida + $cantidad),
        ]);

        if ($detalle->ubicacion_origen_id !== null) {
            $this->stockUbicacionService->consumirReserva(
                empresaId: (int) $detalle->empresa_id,
                productoId: (int) $detalle->producto_id,
                bodegaId: (int) $detalle->bodega_id,
                ubicacionId: (int) $detalle->ubicacion_origen_id,
                loteId: $detalle->lote_id ? (int) $detalle->lote_id : null,
                cantidad: $cantidad,
                campo: 'cantidad_despachada'
            );
        }

        ReservaConsumoInventario::create([
            'empresa_id' => (int) $detalle->empresa_id,
            'reserva_id' => (int) $reservaDetalle->reserva_id,
            'reserva_detalle_id' => (int) $reservaDetalle->id,
            'movimiento_inventario_id' => (int) $movimiento->id,
            'producto_id' => (int) $detalle->producto_id,
            'bodega_id' => (int) $detalle->bodega_id,
            'ubicacion_id' => $detalle->ubicacion_origen_id,
            'lote_id' => $detalle->lote_id,
            'estado_stock' => StockUbicacionInventario::ESTADO_DISPONIBLE,
            'cantidad_consumida' => $cantidad,
            'consumido_por' => $usuario->id,
            'fecha_consumo' => now(),
        ]);
    }

    private function liberarReservaDetalle(
        InventarioDespachoDetalle $detalle,
        ReservaDetalleInventario $reservaDetalle,
        float $cantidad
    ): void {
        $cantidadALiberar = min($cantidad, $reservaDetalle->cantidadPendiente());

        if ($cantidadALiberar <= 0) {
            return;
        }

        if (!$reservaDetalle->puedeLiberar($cantidadALiberar)) {
            throw ValidationException::withMessages(['cantidad_faltante' => 'No se puede liberar más que la cantidad pendiente de la reserva.']);
        }

        $reservaDetalle->update([
            'cantidad_liberada' => $this->redondearCantidad((float) $reservaDetalle->cantidad_liberada + $cantidadALiberar),
        ]);

        if ($detalle->ubicacion_origen_id !== null) {
            $this->stockUbicacionService->liberarReserva(
                empresaId: (int) $detalle->empresa_id,
                productoId: (int) $detalle->producto_id,
                bodegaId: (int) $detalle->bodega_id,
                ubicacionId: (int) $detalle->ubicacion_origen_id,
                loteId: $detalle->lote_id ? (int) $detalle->lote_id : null,
                cantidad: $cantidadALiberar,
                campo: 'cantidad_faltante'
            );
        }
    }

    private function actualizarEstadoReserva(ReservaInventario $reserva): void
    {
        $reserva->load('detalles');

        $totalReservado = $this->redondearCantidad((float) $reserva->detalles->sum('cantidad_reservada'));
        $totalConsumido = $this->redondearCantidad((float) $reserva->detalles->sum('cantidad_consumida'));
        $totalLiberado = $this->redondearCantidad((float) $reserva->detalles->sum('cantidad_liberada'));
        $totalPendiente = $this->redondearCantidad($totalReservado - $totalConsumido - $totalLiberado);

        $nuevoEstado = ReservaInventario::ESTADO_ACTIVA;

        if ($totalPendiente <= 0) {
            $nuevoEstado = $totalConsumido > 0
                ? ReservaInventario::ESTADO_CONSUMIDA
                : ReservaInventario::ESTADO_CANCELADA;
        } elseif ($totalConsumido > 0) {
            $nuevoEstado = ReservaInventario::ESTADO_PARCIALMENTE_CONSUMIDA;
        } elseif ($totalLiberado > 0) {
            $nuevoEstado = ReservaInventario::ESTADO_PARCIALMENTE_LIBERADA;
        }

        if ($reserva->estado !== $nuevoEstado) {
            $reserva->update(['estado' => $nuevoEstado]);
        }
    }

    private function normalizarDetallesConfirmacion($detalles, ?array $detallesPayload): array
    {
        if ($detalles->isEmpty()) {
            throw ValidationException::withMessages(['detalles' => 'La orden de despacho no tiene detalles para confirmar.']);
        }

        if ($detallesPayload === null) {
            return $detalles->map(fn (InventarioDespachoDetalle $detalle) => [
                'detalle' => $detalle,
                'cantidad_despachada' => $this->redondearCantidad((float) $detalle->cantidad_empacada),
                'observacion' => null,
            ])->values()->all();
        }

        if (empty($detallesPayload)) {
            throw ValidationException::withMessages(['detalles' => 'Debe informar al menos un detalle para confirmar el despacho.']);
        }

        $normalizados = [];

        foreach ($detallesPayload as $indice => $item) {
            $detalleId = (int) ($item['id'] ?? $item['detalle_id'] ?? 0);
            $detalle = $detalles->get($detalleId);

            if (!$detalle) {
                throw ValidationException::withMessages(["detalles.{$indice}.id" => 'El detalle informado no pertenece al despacho.']);
            }

            if (!array_key_exists('cantidad_despachada', $item) || !is_numeric($item['cantidad_despachada'])) {
                throw ValidationException::withMessages(["detalles.{$indice}.cantidad_despachada" => 'La cantidad despachada debe ser numérica.']);
            }

            $cantidad = $this->redondearCantidad((float) $item['cantidad_despachada']);

            if ($cantidad < 0) {
                throw ValidationException::withMessages(["detalles.{$indice}.cantidad_despachada" => 'La cantidad despachada no puede ser negativa.']);
            }

            $normalizados[] = [
                'detalle' => $detalle,
                'cantidad_despachada' => $cantidad,
                'observacion' => $this->textoOpcional($item['observacion'] ?? null, 2000),
            ];
        }

        if (count($normalizados) !== $detalles->count()) {
            throw ValidationException::withMessages([
                'detalles' => 'Debe informar todos los detalles del despacho al confirmar con cantidades manuales.',
            ]);
        }

        return $normalizados;
    }

    private function cargarOrden(InventarioDespachoOrden $orden): InventarioDespachoOrden
    {
        return $orden->load([
            'bodega:id,empresa_id,codigo,nombre,estado',
            'packingOrden:id,empresa_id,picking_orden_id,bodega_id,codigo,estado,fecha_confirmacion',
            'pickingOrden:id,empresa_id,bodega_id,reserva_id,codigo,estado,referencia,prioridad',
            'reserva:id,empresa_id,codigo_reserva,estado,referencia,motivo',
            'detalles.producto:id,empresa_id,sku,nombre,maneja_lotes',
            'detalles.ubicacionOrigen:id,empresa_id,bodega_id,codigo,nombre,tipo,activo',
            'detalles.lote:id,empresa_id,producto_id,codigo_lote,fecha_vencimiento,estado_operativo,activo',
            'detalles.packingDetalle:id,empresa_id,packing_orden_id,cantidad_pickeada,cantidad_empacada,estado',
            'detalles.movimiento:id,empresa_id,tipo,cantidad,referencia,motivo,fecha_movimiento',
        ]);
    }

    private function auditarDespacho(
        User $usuario,
        string $accion,
        InventarioDespachoOrden $orden,
        string $descripcion,
        array $metadata = [],
        string $severidad = InventarioAuditoriaEvento::SEVERIDAD_INFO
    ): void {
        $metadataBase = array_merge([
            'codigo' => $orden->codigo,
            'estado' => $orden->estado,
            'bodega_id' => $orden->bodega_id,
            'packing_orden_id' => $orden->packing_orden_id,
            'picking_orden_id' => $orden->picking_orden_id,
            'reserva_id' => $orden->reserva_id,
        ], $metadata);

        $this->auditoria->registrarEvento($usuario, [
            'empresa_id' => (int) $orden->empresa_id,
            'accion' => $accion,
            'entidad_tipo' => InventarioDespachoOrden::class,
            'entidad_id' => (int) $orden->id,
            'severidad' => $severidad,
            'descripcion' => $descripcion,
            'referencia' => $orden->referencia ?? $orden->codigo,
            'motivo' => $orden->motivo,
            'observacion' => $orden->observacion,
            'origen_modulo' => $orden->origen_modulo,
            'origen_id' => $orden->origen_id,
            'metadata_json' => $metadataBase,
        ]);

        $eventoIntegracion = match ($accion) {
            InventarioAuditoriaEvento::ACCION_DESPACHO_CREADO => InventarioEventoIntegracion::EVENTO_DESPACHO_CREADO,
            InventarioAuditoriaEvento::ACCION_DESPACHO_INICIADO => InventarioEventoIntegracion::EVENTO_DESPACHO_INICIADO,
            InventarioAuditoriaEvento::ACCION_DESPACHO_CONFIRMADO => InventarioEventoIntegracion::EVENTO_DESPACHO_CONFIRMADO,
            InventarioAuditoriaEvento::ACCION_DESPACHO_CANCELADO => InventarioEventoIntegracion::EVENTO_DESPACHO_CANCELADO,
            default => null,
        };

        if ($eventoIntegracion !== null) {
            $this->eventosIntegracion->publicarDesdeOperacion($usuario, $eventoIntegracion, [
                'empresa_id' => (int) $orden->empresa_id,
                'entidad_tipo' => InventarioDespachoOrden::class,
                'entidad_id' => (int) $orden->id,
                'prioridad' => $severidad === InventarioAuditoriaEvento::SEVERIDAD_CRITICAL
                    ? InventarioEventoIntegracion::PRIORIDAD_CRITICA
                    : InventarioEventoIntegracion::PRIORIDAD_ALTA,
                'payload_json' => $metadataBase,
                'metadata_json' => [
                    'descripcion' => $descripcion,
                    'referencia' => $orden->referencia ?? $orden->codigo,
                    'motivo' => $orden->motivo,
                ],
                'origen_modulo' => $orden->origen_modulo,
                'origen_id' => $orden->origen_id,
            ], true);
        }
    }

    private function generarCodigo(int $empresaId): string
    {
        $secuencia = InventarioDespachoOrden::where('empresa_id', $empresaId)->count() + 1;

        return 'DESP-' . now()->format('Ymd') . '-' . str_pad((string) $secuencia, 5, '0', STR_PAD_LEFT);
    }

    private function textoOpcional(mixed $valor, int $max): ?string
    {
        if ($valor === null) {
            return null;
        }

        $valor = trim((string) $valor);

        if ($valor === '') {
            return null;
        }

        return mb_substr($valor, 0, $max);
    }

    private function normalizarPerPage(mixed $perPage): int
    {
        $perPage = (int) $perPage;

        if ($perPage <= 0) {
            return 15;
        }

        return min($perPage, 200);
    }

    private function redondearCantidad(float $value): float
    {
        return round($value, 4);
    }
}
