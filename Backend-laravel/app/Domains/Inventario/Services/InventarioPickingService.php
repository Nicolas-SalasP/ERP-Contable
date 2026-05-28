<?php

namespace App\Domains\Inventario\Services;

use App\Domains\Core\Models\User;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\InventarioEventoIntegracion;
use App\Domains\Inventario\Models\InventarioPickingAsignacion;
use App\Domains\Inventario\Models\InventarioPickingDetalle;
use App\Domains\Inventario\Models\InventarioPickingOrden;
use App\Domains\Inventario\Models\InventarioUbicacion;
use App\Domains\Inventario\Models\LoteInventario;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\ReservaDetalleInventario;
use App\Domains\Inventario\Models\ReservaInventario;
use App\Domains\Inventario\Models\StockUbicacionInventario;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventarioPickingService
{
    public function __construct(
        private readonly InventarioPermisoService $permisos,
        private readonly InventarioPickingAsignacionService $asignacionService,
        private readonly InventarioStockUbicacionService $stockUbicacionService,
        private readonly InventarioEventoIntegracionService $eventosIntegracion
    ) {
    }

    public function listar(User $usuario, array $filtros = []): LengthAwarePaginator
    {
        $this->permisos->exigir($usuario, 'inventario.picking.ver');

        return InventarioPickingOrden::query()
            ->where('empresa_id', $usuario->empresa_id)
            ->with([
                'bodega:id,empresa_id,codigo,nombre,estado',
                'usuarioCreador:id,nombre,email',
                'usuarioAsignado:id,nombre,email',
                'detalles.producto:id,empresa_id,sku,nombre,maneja_lotes',
                'detalles.ubicacionOrigen:id,empresa_id,bodega_id,codigo,nombre,tipo,activo',
                'detalles.lote:id,empresa_id,producto_id,codigo_lote,fecha_vencimiento,estado_operativo,activo',
                'detalles.asignaciones.ubicacionOrigen:id,empresa_id,bodega_id,codigo,nombre,tipo,activo',
                'detalles.asignaciones.lote:id,empresa_id,producto_id,codigo_lote,fecha_vencimiento,estado_operativo,activo',
                'packing:id,empresa_id,picking_orden_id,codigo,estado',
            ])
            ->when(!empty($filtros['estado']), fn (Builder $query) => $query->where('estado', $filtros['estado']))
            ->when(!empty($filtros['bodega_id']), fn (Builder $query) => $query->where('bodega_id', (int) $filtros['bodega_id']))
            ->when(!empty($filtros['referencia']), fn (Builder $query) => $query->where('referencia', 'like', '%' . trim((string) $filtros['referencia']) . '%'))
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

    public function obtener(User $usuario, int $id): InventarioPickingOrden
    {
        $this->permisos->exigir($usuario, 'inventario.picking.ver');

        $orden = InventarioPickingOrden::query()
            ->where('empresa_id', $usuario->empresa_id)
            ->find($id);

        if (!$orden) {
            throw new Exception('La orden de picking no existe o no pertenece a la empresa.');
        }

        return $this->cargarOrden($orden);
    }

    public function crear(User $usuario, array $datos): InventarioPickingOrden
    {
        $this->permisos->exigir($usuario, 'inventario.picking.crear');

        return DB::transaction(function () use ($usuario, $datos) {
            $empresaId = (int) $usuario->empresa_id;
            $bodega = $this->obtenerBodegaActivaEmpresa((int) ($datos['bodega_id'] ?? 0), $empresaId, 'bodega_id');
            $detalles = $this->normalizarDetallesCreacion($datos['detalles'] ?? [], $empresaId, (int) $bodega->id);

            $orden = InventarioPickingOrden::create([
                'empresa_id' => $empresaId,
                'bodega_id' => (int) $bodega->id,
                'codigo' => $datos['codigo'] ?? $this->generarCodigo($empresaId),
                'estado' => InventarioPickingOrden::ESTADO_PENDIENTE,
                'prioridad' => $datos['prioridad'] ?? InventarioPickingOrden::PRIORIDAD_NORMAL,
                'referencia' => $this->textoOpcional($datos['referencia'] ?? null, 120),
                'motivo' => $this->textoOpcional($datos['motivo'] ?? 'picking_interno', 120),
                'observacion' => $this->textoOpcional($datos['observacion'] ?? null, 2000),
                'origen_modulo' => $this->textoOpcional($datos['origen_modulo'] ?? null, 80),
                'origen_id' => $datos['origen_id'] ?? null,
                'usuario_creador_id' => $usuario->id,
                'usuario_asignado_id' => $datos['usuario_asignado_id'] ?? null,
                'fecha_creacion' => now(),
            ]);

            foreach ($detalles as $detalle) {
                InventarioPickingDetalle::create([
                    'empresa_id' => $empresaId,
                    'picking_orden_id' => $orden->id,
                    'producto_id' => $detalle['producto']->id,
                    'bodega_id' => $bodega->id,
                    'ubicacion_origen_id' => $detalle['ubicacion']?->id,
                    'lote_id' => $detalle['lote']?->id,
                    'cantidad_solicitada' => $detalle['cantidad'],
                    'cantidad_asignada' => 0,
                    'cantidad_pickeada' => 0,
                    'cantidad_faltante' => 0,
                    'estado' => InventarioPickingDetalle::ESTADO_PENDIENTE,
                    'observacion' => $this->textoOpcional($detalle['observacion'] ?? null, 2000),
                ]);
            }

            $orden = $this->cargarOrden($orden->refresh());
            $this->publicarEventoPicking($usuario, InventarioEventoIntegracion::EVENTO_PICKING_CREADO, $orden, [
                'total_detalles' => count($detalles),
            ]);

            return $orden;
        });
    }

    public function asignar(User $usuario, int $id): InventarioPickingOrden
    {
        $this->permisos->exigir($usuario, 'inventario.picking.editar');

        return DB::transaction(function () use ($usuario, $id) {
            $empresaId = (int) $usuario->empresa_id;
            $orden = InventarioPickingOrden::where('empresa_id', $empresaId)->lockForUpdate()->find($id);

            if (!$orden) {
                throw new Exception('La orden de picking no existe o no pertenece a la empresa.');
            }

            if (!$orden->puedeAsignarse()) {
                throw new Exception('La orden de picking no puede asignarse en su estado actual o ya tiene reserva interna.');
            }

            $detalles = InventarioPickingDetalle::where('empresa_id', $empresaId)
                ->where('picking_orden_id', $orden->id)
                ->lockForUpdate()
                ->get();

            if ($detalles->isEmpty()) {
                throw ValidationException::withMessages(['detalles' => 'La orden no tiene detalles para asignar.']);
            }

            $reserva = ReservaInventario::create([
                'empresa_id' => $empresaId,
                'codigo_reserva' => $this->generarCodigoReservaPicking($empresaId, (string) $orden->codigo),
                'estado' => ReservaInventario::ESTADO_ACTIVA,
                'referencia' => $orden->referencia ?? $orden->codigo,
                'motivo' => 'picking_interno',
                'observacion' => 'Reserva interna generada por orden de picking ' . $orden->codigo . '. No genera DTE/SII ni asiento contable.',
                'origen_modulo' => 'INVENTARIO_PICKING',
                'origen_id' => $orden->id,
                'reservado_por' => $usuario->id,
                'fecha_reserva' => now(),
            ]);

            foreach ($detalles as $indice => $detalle) {
                /** @var InventarioPickingDetalle $detalle */
                $producto = $this->obtenerProductoActivoEmpresa((int) $detalle->producto_id, $empresaId, "detalles.{$indice}.producto_id");
                $this->obtenerBodegaActivaEmpresa((int) $detalle->bodega_id, $empresaId, "detalles.{$indice}.bodega_id");

                $cantidadSolicitada = $this->redondearCantidad((float) $detalle->cantidad_solicitada);

                $sugerencias = $this->asignacionService->construirAsignaciones(
                    empresaId: $empresaId,
                    producto: $producto,
                    bodegaId: (int) $detalle->bodega_id,
                    cantidad: $cantidadSolicitada,
                    loteId: $detalle->lote_id ? (int) $detalle->lote_id : null,
                    campo: "detalles.{$indice}.cantidad"
                );

                $cantidadAsignadaTotal = 0.0;
                $primerStock = null;
                $primerReservaDetalle = null;

                foreach ($sugerencias as $sugerencia) {
                    /** @var StockUbicacionInventario $stockSugerido */
                    $stockSugerido = $sugerencia['stock'];
                    $cantidadAsignada = $this->redondearCantidad((float) $sugerencia['cantidad']);

                    if ($cantidadAsignada <= 0) {
                        continue;
                    }

                    $this->stockUbicacionService->reservar(
                        empresaId: $empresaId,
                        productoId: (int) $detalle->producto_id,
                        bodegaId: (int) $detalle->bodega_id,
                        ubicacionId: (int) $stockSugerido->ubicacion_id,
                        loteId: $stockSugerido->lote_id ? (int) $stockSugerido->lote_id : null,
                        cantidad: $cantidadAsignada,
                        campo: "detalles.{$indice}.cantidad"
                    );

                    $reservaDetalle = ReservaDetalleInventario::create([
                        'empresa_id' => $empresaId,
                        'reserva_id' => $reserva->id,
                        'producto_id' => $detalle->producto_id,
                        'bodega_id' => $detalle->bodega_id,
                        'ubicacion_id' => $stockSugerido->ubicacion_id,
                        'lote_id' => $stockSugerido->lote_id,
                        'estado_stock' => StockUbicacionInventario::ESTADO_DISPONIBLE,
                        'cantidad_reservada' => $cantidadAsignada,
                        'cantidad_consumida' => 0,
                        'cantidad_liberada' => 0,
                    ]);

                    InventarioPickingAsignacion::create([
                        'empresa_id' => $empresaId,
                        'picking_orden_id' => $orden->id,
                        'picking_detalle_id' => $detalle->id,
                        'reserva_detalle_id' => $reservaDetalle->id,
                        'producto_id' => $detalle->producto_id,
                        'bodega_id' => $detalle->bodega_id,
                        'ubicacion_origen_id' => $stockSugerido->ubicacion_id,
                        'lote_id' => $stockSugerido->lote_id,
                        'cantidad_asignada' => $cantidadAsignada,
                        'cantidad_pickeada' => 0,
                        'cantidad_faltante' => 0,
                        'estado' => InventarioPickingAsignacion::ESTADO_PENDIENTE,
                    ]);

                    $cantidadAsignadaTotal = $this->redondearCantidad($cantidadAsignadaTotal + $cantidadAsignada);
                    $primerStock ??= $stockSugerido;
                    $primerReservaDetalle ??= $reservaDetalle;
                }

                $detalle->update([
                    'reserva_detalle_id' => $primerReservaDetalle?->id,
                    'ubicacion_origen_id' => $primerStock?->ubicacion_id,
                    'lote_id' => $primerStock?->lote_id,
                    'cantidad_asignada' => $cantidadAsignadaTotal,
                    'cantidad_faltante' => $this->redondearCantidad(max(0, $cantidadSolicitada - $cantidadAsignadaTotal)),
                    'estado' => $cantidadAsignadaTotal <= 0
                        ? InventarioPickingDetalle::ESTADO_SIN_STOCK
                        : ($cantidadAsignadaTotal >= $cantidadSolicitada
                            ? InventarioPickingDetalle::ESTADO_PENDIENTE
                            : InventarioPickingDetalle::ESTADO_SIN_STOCK),
                ]);
            }

            $orden->update([
                'reserva_id' => $reserva->id,
                'fecha_asignacion' => now(),
                'estado' => $this->ordenTieneDiferenciasAsignacion($orden->id, $empresaId)
                    ? InventarioPickingOrden::ESTADO_CON_DIFERENCIAS
                    : InventarioPickingOrden::ESTADO_PENDIENTE,
            ]);

            return $this->cargarOrden($orden->refresh());
        });
    }

    public function iniciar(User $usuario, int $id): InventarioPickingOrden
    {
        $this->permisos->exigir($usuario, 'inventario.picking.editar');

        return DB::transaction(function () use ($usuario, $id) {
            $orden = InventarioPickingOrden::where('empresa_id', $usuario->empresa_id)->lockForUpdate()->find($id);

            if (!$orden) {
                throw new Exception('La orden de picking no existe o no pertenece a la empresa.');
            }

            if (!$orden->puedeIniciarse()) {
                throw new Exception('La orden debe estar asignada y pendiente para iniciar picking.');
            }

            $orden->update([
                'estado' => InventarioPickingOrden::ESTADO_EN_PREPARACION,
                'fecha_inicio' => $orden->fecha_inicio ?? now(),
                'usuario_asignado_id' => $orden->usuario_asignado_id ?? $usuario->id,
            ]);

            return $this->cargarOrden($orden->refresh());
        });
    }

    public function confirmar(User $usuario, int $id, array $datos = []): InventarioPickingOrden
    {
        $this->permisos->exigir($usuario, 'inventario.picking.confirmar');

        return DB::transaction(function () use ($usuario, $id, $datos) {
            $empresaId = (int) $usuario->empresa_id;
            $orden = InventarioPickingOrden::where('empresa_id', $empresaId)->lockForUpdate()->find($id);

            if (!$orden) {
                throw new Exception('La orden de picking no existe o no pertenece a la empresa.');
            }

            if (!$orden->puedeConfirmarse()) {
                throw new Exception('La orden de picking no puede confirmarse en su estado actual.');
            }

            $detalles = InventarioPickingDetalle::where('empresa_id', $empresaId)
                ->where('picking_orden_id', $orden->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $asignaciones = InventarioPickingAsignacion::where('empresa_id', $empresaId)
                ->where('picking_orden_id', $orden->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($asignaciones->isEmpty()) {
                throw ValidationException::withMessages(['asignaciones' => 'La orden no tiene asignaciones de picking para confirmar.']);
            }

            $operaciones = $this->normalizarConfirmacionMultiubicacion($detalles, $asignaciones, $datos['detalles'] ?? null);

            foreach ($operaciones as $item) {
                /** @var InventarioPickingAsignacion $asignacion */
                $asignacion = $item['asignacion'];
                $cantidadPickeada = $item['cantidad_pickeada'];

                if ($cantidadPickeada > (float) $asignacion->cantidad_asignada + 0.0001) {
                    throw ValidationException::withMessages([
                        'cantidad_pickeada' => 'No se puede pickear más que la cantidad asignada/reservada por ubicación/lote.',
                    ]);
                }

                $faltanteAsignacion = $this->redondearCantidad(max(0, (float) $asignacion->cantidad_asignada - $cantidadPickeada));

                $asignacion->update([
                    'cantidad_pickeada' => $cantidadPickeada,
                    'cantidad_faltante' => $faltanteAsignacion,
                    'estado' => $cantidadPickeada <= 0
                        ? InventarioPickingAsignacion::ESTADO_SIN_STOCK
                        : ($faltanteAsignacion > 0 ? InventarioPickingAsignacion::ESTADO_PARCIAL : InventarioPickingAsignacion::ESTADO_COMPLETO),
                    'observacion' => $item['observacion'] ?? $asignacion->observacion,
                    'fecha_confirmacion' => now(),
                ]);
            }

            foreach ($detalles as $detalle) {
                /** @var InventarioPickingDetalle $detalle */
                $asignacionesDetalle = InventarioPickingAsignacion::where('empresa_id', $empresaId)
                    ->where('picking_detalle_id', $detalle->id)
                    ->get();

                $cantidadAsignada = $this->redondearCantidad((float) $asignacionesDetalle->sum('cantidad_asignada'));
                $cantidadPickeada = $this->redondearCantidad((float) $asignacionesDetalle->sum('cantidad_pickeada'));
                $faltante = $this->redondearCantidad(max(0, (float) $detalle->cantidad_solicitada - $cantidadPickeada));

                $detalle->update([
                    'cantidad_asignada' => $cantidadAsignada,
                    'cantidad_pickeada' => $cantidadPickeada,
                    'cantidad_faltante' => $faltante,
                    'estado' => $cantidadPickeada <= 0
                        ? InventarioPickingDetalle::ESTADO_SIN_STOCK
                        : ($faltante > 0 ? InventarioPickingDetalle::ESTADO_PARCIAL : InventarioPickingDetalle::ESTADO_COMPLETO),
                    'fecha_confirmacion' => now(),
                ]);
            }

            $hayDiferencias = InventarioPickingDetalle::where('empresa_id', $empresaId)
                ->where('picking_orden_id', $orden->id)
                ->where(function (Builder $query) {
                    $query->where('estado', '!=', InventarioPickingDetalle::ESTADO_COMPLETO)
                        ->orWhereColumn('cantidad_pickeada', '<', 'cantidad_solicitada');
                })
                ->exists();

            $orden->update([
                'estado' => $hayDiferencias
                    ? InventarioPickingOrden::ESTADO_CON_DIFERENCIAS
                    : InventarioPickingOrden::ESTADO_PICKING_COMPLETO,
                'fecha_confirmacion' => now(),
                'observacion' => array_key_exists('observacion', $datos)
                    ? $this->textoOpcional($datos['observacion'], 2000)
                    : $orden->observacion,
            ]);

            $orden = $this->cargarOrden($orden->refresh());
            $this->publicarEventoPicking($usuario, InventarioEventoIntegracion::EVENTO_PICKING_CONFIRMADO, $orden, [
                'hay_diferencias' => $hayDiferencias,
                'total_operaciones' => count($operaciones),
            ], $hayDiferencias ? InventarioEventoIntegracion::PRIORIDAD_ALTA : InventarioEventoIntegracion::PRIORIDAD_NORMAL);

            return $orden;
        });
    }

    public function cancelar(User $usuario, int $id, array $datos = []): InventarioPickingOrden
    {
        $this->permisos->exigir($usuario, 'inventario.picking.cancelar');

        return DB::transaction(function () use ($usuario, $id, $datos) {
            $empresaId = (int) $usuario->empresa_id;
            $orden = InventarioPickingOrden::where('empresa_id', $empresaId)->lockForUpdate()->find($id);

            if (!$orden) {
                throw new Exception('La orden de picking no existe o no pertenece a la empresa.');
            }

            if (!$orden->puedeCancelarse()) {
                throw new Exception('La orden de picking no puede cancelarse en su estado actual.');
            }

            $detalles = InventarioPickingDetalle::where('empresa_id', $empresaId)
                ->where('picking_orden_id', $orden->id)
                ->lockForUpdate()
                ->get();

            $asignaciones = InventarioPickingAsignacion::where('empresa_id', $empresaId)
                ->where('picking_orden_id', $orden->id)
                ->lockForUpdate()
                ->get();

            foreach ($asignaciones as $asignacion) {
                /** @var InventarioPickingAsignacion $asignacion */
                $pendienteLiberar = $this->redondearCantidad((float) $asignacion->cantidad_asignada - (float) $asignacion->cantidad_pickeada);

                if ($pendienteLiberar > 0) {
                    $this->stockUbicacionService->liberarReserva(
                        empresaId: $empresaId,
                        productoId: (int) $asignacion->producto_id,
                        bodegaId: (int) $asignacion->bodega_id,
                        ubicacionId: (int) $asignacion->ubicacion_origen_id,
                        loteId: $asignacion->lote_id ? (int) $asignacion->lote_id : null,
                        cantidad: $pendienteLiberar
                    );
                }

                if ($asignacion->reserva_detalle_id) {
                    ReservaDetalleInventario::where('empresa_id', $empresaId)
                        ->where('id', $asignacion->reserva_detalle_id)
                        ->update([
                            'cantidad_liberada' => DB::raw('cantidad_liberada + ' . $pendienteLiberar),
                        ]);
                }

                $asignacion->update([
                    'cantidad_faltante' => $this->redondearCantidad(max(0, (float) $asignacion->cantidad_asignada - (float) $asignacion->cantidad_pickeada)),
                    'estado' => InventarioPickingAsignacion::ESTADO_CANCELADO,
                ]);
            }

            foreach ($detalles as $detalle) {
                /** @var InventarioPickingDetalle $detalle */
                $cantidadCancelada = $this->redondearCantidad((float) $detalle->cantidad_asignada - (float) $detalle->cantidad_pickeada);

                $detalle->update([
                    'cantidad_cancelada' => $this->redondearCantidad((float) $detalle->cantidad_cancelada + max(0, $cantidadCancelada)),
                    'estado' => InventarioPickingDetalle::ESTADO_CANCELADO,
                ]);
            }

            if ($orden->reserva_id) {
                ReservaInventario::where('empresa_id', $empresaId)
                    ->where('id', $orden->reserva_id)
                    ->update(['estado' => ReservaInventario::ESTADO_CANCELADA]);
            }

            $orden->update([
                'estado' => InventarioPickingOrden::ESTADO_CANCELADO,
                'fecha_cancelacion' => now(),
                'observacion' => array_key_exists('observacion', $datos)
                    ? $this->textoOpcional($datos['observacion'], 2000)
                    : $orden->observacion,
            ]);

            $orden = $this->cargarOrden($orden->refresh());
            $this->publicarEventoPicking($usuario, InventarioEventoIntegracion::EVENTO_PICKING_CANCELADO, $orden, [
                'observacion_cancelacion' => $datos['observacion'] ?? null,
            ], InventarioEventoIntegracion::PRIORIDAD_ALTA);

            return $orden;
        });
    }

    public function reporte(User $usuario, array $filtros = []): array
    {
        $this->permisos->exigirAlguno($usuario, ['inventario.reportes.picking', 'inventario.picking.ver']);

        $query = InventarioPickingOrden::query()->where('empresa_id', $usuario->empresa_id);

        if (!empty($filtros['bodega_id'])) {
            $query->where('bodega_id', (int) $filtros['bodega_id']);
        }

        $porEstado = (clone $query)
            ->select('estado', DB::raw('COUNT(*) as total'))
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->toArray();

        $resumen = [
            'total' => (clone $query)->count(),
            'pendientes' => (clone $query)->where('estado', InventarioPickingOrden::ESTADO_PENDIENTE)->count(),
            'en_preparacion' => (clone $query)->where('estado', InventarioPickingOrden::ESTADO_EN_PREPARACION)->count(),
            'completos' => (clone $query)->where('estado', InventarioPickingOrden::ESTADO_PICKING_COMPLETO)->count(),
            'con_diferencias' => (clone $query)->where('estado', InventarioPickingOrden::ESTADO_CON_DIFERENCIAS)->count(),
            'cancelados' => (clone $query)->where('estado', InventarioPickingOrden::ESTADO_CANCELADO)->count(),
        ];

        return [
            'resumen' => $resumen,
            'por_estado' => $porEstado,
        ];
    }

    private function normalizarDetallesCreacion(array $detalles, int $empresaId, int $bodegaId): array
    {
        if (empty($detalles)) {
            throw ValidationException::withMessages(['detalles' => 'Debe informar al menos un detalle para la orden de picking.']);
        }

        $normalizados = [];

        foreach ($detalles as $indice => $detalle) {
            $producto = $this->obtenerProductoActivoEmpresa((int) ($detalle['producto_id'] ?? 0), $empresaId, "detalles.{$indice}.producto_id");
            $ubicacion = null;

            if (!empty($detalle['bodega_id']) && (int) $detalle['bodega_id'] !== $bodegaId) {
                throw ValidationException::withMessages(["detalles.{$indice}.bodega_id" => 'El detalle debe pertenecer a la misma bodega de la orden.']);
            }

            if (!empty($detalle['ubicacion_origen_id'])) {
                $ubicacion = $this->obtenerUbicacionActivaEmpresaBodega((int) $detalle['ubicacion_origen_id'], $empresaId, $bodegaId, "detalles.{$indice}.ubicacion_origen_id");
            }

            $lote = $this->normalizarLote($producto, $detalle['lote_id'] ?? null, $empresaId, "detalles.{$indice}.lote_id");
            $cantidad = $this->validarCantidadPositiva($detalle['cantidad_solicitada'] ?? $detalle['cantidad'] ?? null, "detalles.{$indice}.cantidad");

            $normalizados[] = compact('producto', 'ubicacion', 'lote', 'cantidad') + [
                'observacion' => $detalle['observacion'] ?? null,
            ];
        }

        return $normalizados;
    }

    private function normalizarConfirmacionMultiubicacion($detalles, $asignaciones, mixed $payload): array
    {
        if ($payload === null) {
            return $asignaciones->map(fn (InventarioPickingAsignacion $asignacion) => [
                'asignacion' => $asignacion,
                'cantidad_pickeada' => $this->redondearCantidad((float) $asignacion->cantidad_asignada),
            ])->values()->all();
        }

        if (!is_array($payload) || empty($payload)) {
            throw ValidationException::withMessages(['detalles' => 'Debe informar detalles válidos para confirmar picking.']);
        }

        $operaciones = [];

        foreach ($payload as $indice => $item) {
            $detalleId = (int) ($item['id'] ?? $item['detalle_id'] ?? 0);
            $detalle = $detalles->get($detalleId);

            if (!$detalle) {
                throw ValidationException::withMessages(["detalles.{$indice}.id" => 'El detalle informado no pertenece a la orden de picking.']);
            }

            if (isset($item['asignaciones']) && is_array($item['asignaciones'])) {
                foreach ($item['asignaciones'] as $subIndice => $subItem) {
                    $asignacionId = (int) ($subItem['id'] ?? $subItem['asignacion_id'] ?? 0);
                    $asignacion = $asignaciones->get($asignacionId);

                    if (!$asignacion || (int) $asignacion->picking_detalle_id !== (int) $detalle->id) {
                        throw ValidationException::withMessages(["detalles.{$indice}.asignaciones.{$subIndice}.id" => 'La asignación informada no pertenece al detalle de picking.']);
                    }

                    $cantidad = $subItem['cantidad_pickeada'] ?? null;

                    if (!is_numeric($cantidad) || (float) $cantidad < 0) {
                        throw ValidationException::withMessages(["detalles.{$indice}.asignaciones.{$subIndice}.cantidad_pickeada" => 'La cantidad pickeada debe ser numérica y no negativa.']);
                    }

                    $operaciones[] = [
                        'asignacion' => $asignacion,
                        'cantidad_pickeada' => $this->redondearCantidad((float) $cantidad),
                        'observacion' => $subItem['observacion'] ?? null,
                    ];
                }

                continue;
            }

            $cantidad = $item['cantidad_pickeada'] ?? null;

            if (!is_numeric($cantidad) || (float) $cantidad < 0) {
                throw ValidationException::withMessages(["detalles.{$indice}.cantidad_pickeada" => 'La cantidad pickeada debe ser numérica y no negativa.']);
            }

            $operaciones = array_merge(
                $operaciones,
                $this->distribuirCantidadPickeadaEnAsignaciones($detalle, $asignaciones, $this->redondearCantidad((float) $cantidad), $indice)
            );
        }

        return $operaciones;
    }

    private function distribuirCantidadPickeadaEnAsignaciones(InventarioPickingDetalle $detalle, $asignaciones, float $cantidadTotal, int $indiceDetalle): array
    {
        if ($cantidadTotal > (float) $detalle->cantidad_asignada + 0.0001) {
            throw ValidationException::withMessages([
                "detalles.{$indiceDetalle}.cantidad_pickeada" => 'No se puede pickear más que la cantidad asignada/reservada.',
            ]);
        }

        $pendiente = $cantidadTotal;
        $operaciones = [];

        $asignacionesDetalle = $asignaciones
            ->filter(fn (InventarioPickingAsignacion $asignacion) => (int) $asignacion->picking_detalle_id === (int) $detalle->id)
            ->values();

        foreach ($asignacionesDetalle as $asignacion) {
            $cantidad = $this->redondearCantidad(min($pendiente, (float) $asignacion->cantidad_asignada));

            $operaciones[] = [
                'asignacion' => $asignacion,
                'cantidad_pickeada' => max(0, $cantidad),
            ];

            $pendiente = $this->redondearCantidad($pendiente - $cantidad);
        }

        return $operaciones;
    }

    private function normalizarDetallesConfirmacion($detalles, mixed $payload): array
    {
        $operaciones = [];

        if ($payload === null) {
            foreach ($detalles as $detalle) {
                $operaciones[] = [
                    'detalle' => $detalle,
                    'cantidad_pickeada' => $this->redondearCantidad((float) $detalle->cantidad_asignada),
                ];
            }

            return $operaciones;
        }

        if (!is_array($payload) || empty($payload)) {
            throw ValidationException::withMessages(['detalles' => 'Debe informar detalles válidos para confirmar picking.']);
        }

        foreach ($payload as $indice => $item) {
            $detalleId = (int) ($item['id'] ?? $item['detalle_id'] ?? 0);
            $detalle = $detalles->get($detalleId);

            if (!$detalle) {
                throw ValidationException::withMessages(["detalles.{$indice}.id" => 'El detalle informado no pertenece a la orden de picking.']);
            }

            $cantidad = $item['cantidad_pickeada'] ?? null;

            if (!is_numeric($cantidad) || (float) $cantidad < 0) {
                throw ValidationException::withMessages(["detalles.{$indice}.cantidad_pickeada" => 'La cantidad pickeada debe ser numérica y no negativa.']);
            }

            $operaciones[] = [
                'detalle' => $detalle,
                'cantidad_pickeada' => $this->redondearCantidad((float) $cantidad),
                'observacion' => $item['observacion'] ?? null,
            ];
        }

        return $operaciones;
    }

    private function cargarOrden(InventarioPickingOrden $orden): InventarioPickingOrden
    {
        return $orden->load([
            'bodega:id,empresa_id,codigo,nombre,estado',
            'reserva:id,empresa_id,codigo_reserva,estado,referencia,origen_modulo,origen_id',
            'usuarioCreador:id,nombre,email',
            'usuarioAsignado:id,nombre,email',
            'detalles.producto:id,empresa_id,sku,nombre,maneja_lotes',
            'detalles.bodega:id,empresa_id,codigo,nombre,estado',
            'detalles.ubicacionOrigen:id,empresa_id,bodega_id,codigo,nombre,tipo,activo',
            'detalles.lote:id,empresa_id,producto_id,codigo_lote,fecha_vencimiento,estado_operativo,activo',
            'detalles.asignaciones.ubicacionOrigen:id,empresa_id,bodega_id,codigo,nombre,tipo,activo',
            'detalles.asignaciones.lote:id,empresa_id,producto_id,codigo_lote,fecha_vencimiento,estado_operativo,activo',
            'detalles.asignaciones.reservaDetalle:id,empresa_id,reserva_id,cantidad_reservada,cantidad_consumida,cantidad_liberada',
            'packing:id,empresa_id,picking_orden_id,codigo,estado',
        ]);
    }

    private function ordenTieneDiferenciasAsignacion(int $ordenId, int $empresaId): bool
    {
        return InventarioPickingDetalle::where('empresa_id', $empresaId)
            ->where('picking_orden_id', $ordenId)
            ->where(function (Builder $query) {
                $query->whereColumn('cantidad_asignada', '<', 'cantidad_solicitada')
                    ->orWhere('estado', InventarioPickingDetalle::ESTADO_SIN_STOCK);
            })
            ->exists();
    }

    private function obtenerProductoActivoEmpresa(int $productoId, int $empresaId, string $campo): Producto
    {
        $producto = Producto::query()->where('empresa_id', $empresaId)->find($productoId);

        if (!$producto) {
            throw ValidationException::withMessages([$campo => 'El producto informado no existe o no pertenece a la empresa.']);
        }

        if (!$producto->activo) {
            throw ValidationException::withMessages([$campo => 'El producto informado está inactivo.']);
        }

        return $producto;
    }

    private function obtenerBodegaActivaEmpresa(int $bodegaId, int $empresaId, string $campo): Bodega
    {
        $bodega = Bodega::query()->where('empresa_id', $empresaId)->find($bodegaId);

        if (!$bodega) {
            throw ValidationException::withMessages([$campo => 'La bodega informada no existe o no pertenece a la empresa.']);
        }

        if ($bodega->estado !== 'ACTIVA') {
            throw ValidationException::withMessages([$campo => 'La bodega informada está inactiva.']);
        }

        return $bodega;
    }

    private function obtenerUbicacionActivaEmpresaBodega(int $ubicacionId, int $empresaId, int $bodegaId, string $campo): InventarioUbicacion
    {
        $ubicacion = InventarioUbicacion::query()
            ->where('empresa_id', $empresaId)
            ->where('bodega_id', $bodegaId)
            ->find($ubicacionId);

        if (!$ubicacion) {
            throw ValidationException::withMessages([$campo => 'La ubicación no existe, no pertenece a la empresa o no pertenece a la bodega.']);
        }

        if (!$ubicacion->activo) {
            throw ValidationException::withMessages([$campo => 'La ubicación informada está inactiva.']);
        }

        return $ubicacion;
    }

    private function normalizarLote(Producto $producto, mixed $loteId, int $empresaId, string $campo): ?LoteInventario
    {
        if (!$producto->maneja_lotes && empty($loteId)) {
            return null;
        }

        if (!$producto->maneja_lotes && !empty($loteId)) {
            throw ValidationException::withMessages([$campo => 'El producto no maneja lotes, por lo tanto no debe informar lote_id.']);
        }

        if ($producto->maneja_lotes && empty($loteId)) {
            throw ValidationException::withMessages([$campo => 'El producto maneja lotes, debe informar lote_id.']);
        }

        $lote = LoteInventario::query()
            ->where('empresa_id', $empresaId)
            ->where('producto_id', $producto->id)
            ->find((int) $loteId);

        if (!$lote || !$lote->activo) {
            throw ValidationException::withMessages([$campo => 'El lote informado no existe, está inactivo o no pertenece al producto/empresa.']);
        }

        if ($lote->estaVencido() || $lote->estaBloqueadoOperativamente()) {
            throw ValidationException::withMessages([$campo => 'No se permite picking desde un lote vencido, en cuarentena o bloqueado.']);
        }

        return $lote;
    }

    private function validarCantidadPositiva(mixed $cantidad, string $campo): float
    {
        if (!is_numeric($cantidad)) {
            throw ValidationException::withMessages([$campo => 'La cantidad debe ser numérica.']);
        }

        $cantidad = $this->redondearCantidad((float) $cantidad);

        if ($cantidad <= 0) {
            throw ValidationException::withMessages([$campo => 'La cantidad debe ser mayor a cero.']);
        }

        return $cantidad;
    }

    private function publicarEventoPicking(
        User $usuario,
        string $evento,
        InventarioPickingOrden $orden,
        array $metadata = [],
        string $prioridad = InventarioEventoIntegracion::PRIORIDAD_NORMAL
    ): void {
        $this->eventosIntegracion->publicarDesdeOperacion($usuario, $evento, [
            'empresa_id' => (int) $orden->empresa_id,
            'entidad_tipo' => InventarioPickingOrden::class,
            'entidad_id' => (int) $orden->id,
            'prioridad' => $prioridad,
            'payload_json' => array_merge([
                'codigo' => $orden->codigo,
                'estado' => $orden->estado,
                'bodega_id' => $orden->bodega_id,
                'reserva_id' => $orden->reserva_id,
                'referencia' => $orden->referencia,
                'origen_modulo' => $orden->origen_modulo,
                'origen_id' => $orden->origen_id,
            ], $metadata),
            'metadata_json' => [
                'referencia' => $orden->referencia,
                'motivo' => $orden->motivo,
                'observacion' => $orden->observacion,
            ],
            'origen_modulo' => $orden->origen_modulo,
            'origen_id' => $orden->origen_id,
        ], true);
    }

    private function generarCodigo(int $empresaId): string
    {
        $correlativo = InventarioPickingOrden::where('empresa_id', $empresaId)->lockForUpdate()->count() + 1;
        return 'PICK-' . now()->format('Ymd') . '-' . str_pad((string) $correlativo, 5, '0', STR_PAD_LEFT);
    }

    private function generarCodigoReservaPicking(int $empresaId, string $codigoPicking): string
    {
        $base = 'RES-' . $codigoPicking;
        $codigo = $base;
        $i = 1;

        while (ReservaInventario::where('empresa_id', $empresaId)->where('codigo_reserva', $codigo)->exists()) {
            $codigo = $base . '-' . $i++;
        }

        return $codigo;
    }

    private function textoOpcional(mixed $valor, int $max): ?string
    {
        if ($valor === null || trim((string) $valor) === '') {
            return null;
        }

        return mb_substr(trim((string) $valor), 0, $max);
    }

    private function normalizarPerPage(mixed $perPage): int
    {
        $perPage = (int) $perPage;
        return $perPage <= 0 ? 15 : min($perPage, 100);
    }

    private function redondearCantidad(float $value): float
    {
        return round($value, 4);
    }
}
