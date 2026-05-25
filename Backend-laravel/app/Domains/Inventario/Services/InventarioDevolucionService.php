<?php

namespace App\Domains\Inventario\Services;

use App\Domains\Core\Models\User;
use App\Domains\Inventario\Models\InventarioAuditoriaEvento;
use App\Domains\Inventario\Models\InventarioDespachoDetalle;
use App\Domains\Inventario\Models\InventarioDespachoOrden;
use App\Domains\Inventario\Models\InventarioDevolucionDetalle;
use App\Domains\Inventario\Models\InventarioDevolucionOrden;
use App\Domains\Inventario\Models\InventarioUbicacion;
use App\Domains\Inventario\Models\MovimientoInventario;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventarioDevolucionService
{
    public function __construct(
        private readonly InventarioPermisoService $permisos,
        private readonly InventarioMovimientoService $movimientoService,
        private readonly InventarioAuditoriaService $auditoria
    ) {
    }

    public function listar(User $usuario, array $filtros = []): LengthAwarePaginator
    {
        $this->permisos->exigir($usuario, 'inventario.devoluciones.ver');

        return InventarioDevolucionOrden::query()
            ->where('empresa_id', $usuario->empresa_id)
            ->with($this->relacionesListado())
            ->when(!empty($filtros['estado']), fn (Builder $query) => $query->where('estado', $filtros['estado']))
            ->when(!empty($filtros['tipo']), fn (Builder $query) => $query->where('tipo', $filtros['tipo']))
            ->when(!empty($filtros['despacho_orden_id']), fn (Builder $query) => $query->where('despacho_orden_id', (int) $filtros['despacho_orden_id']))
            ->when(!empty($filtros['bodega_id']), fn (Builder $query) => $query->where('bodega_id', (int) $filtros['bodega_id']))
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

    public function obtener(User $usuario, int $id): InventarioDevolucionOrden
    {
        $this->permisos->exigir($usuario, 'inventario.devoluciones.ver');

        $orden = InventarioDevolucionOrden::query()
            ->where('empresa_id', $usuario->empresa_id)
            ->find($id);

        if (!$orden) {
            throw new Exception('La devolución/reversa no existe o no pertenece a la empresa.');
        }

        return $this->cargarOrden($orden);
    }

    public function reversable(User $usuario, int $despachoId): array
    {
        $this->permisos->exigir($usuario, 'inventario.devoluciones.ver');

        $despacho = InventarioDespachoOrden::query()
            ->where('empresa_id', $usuario->empresa_id)
            ->with([
                'bodega:id,empresa_id,codigo,nombre,estado',
                'detalles.producto:id,empresa_id,sku,nombre,maneja_lotes,costo_promedio',
                'detalles.ubicacionOrigen:id,empresa_id,bodega_id,codigo,nombre,tipo,activo',
                'detalles.lote:id,empresa_id,producto_id,codigo_lote,fecha_vencimiento,estado_operativo,activo',
            ])
            ->find($despachoId);

        if (!$despacho) {
            throw new Exception('La orden de despacho no existe o no pertenece a la empresa.');
        }

        $this->validarDespachoReversible($despacho);

        $detalles = $despacho->detalles->map(function (InventarioDespachoDetalle $detalle) use ($despacho) {
            $despachada = $this->redondearCantidad((float) $detalle->cantidad_despachada);
            $yaReversada = $this->cantidadConfirmadaReversada((int) $despacho->empresa_id, (int) $detalle->id);
            $pendienteReservada = $this->cantidadPendienteReservada((int) $despacho->empresa_id, (int) $detalle->id);
            $reversable = $this->redondearCantidad(max(0, $despachada - $yaReversada - $pendienteReservada));

            return [
                'despacho_detalle_id' => (int) $detalle->id,
                'producto_id' => (int) $detalle->producto_id,
                'producto' => $detalle->producto,
                'bodega_id' => (int) $detalle->bodega_id,
                'ubicacion_origen_id' => $detalle->ubicacion_origen_id ? (int) $detalle->ubicacion_origen_id : null,
                'ubicacion_origen' => $detalle->ubicacionOrigen,
                'lote_id' => $detalle->lote_id ? (int) $detalle->lote_id : null,
                'lote' => $detalle->lote,
                'cantidad_despachada_original' => $despachada,
                'cantidad_ya_reversada' => $yaReversada,
                'cantidad_pendiente_reservada' => $pendienteReservada,
                'cantidad_reversable' => $reversable,
                'reversable' => $reversable > 0,
            ];
        })->values();

        return [
            'despacho' => $despacho,
            'detalles' => $detalles,
            'total_reversable' => $this->redondearCantidad((float) $detalles->sum('cantidad_reversable')),
        ];
    }

    public function crear(User $usuario, array $datos): InventarioDevolucionOrden
    {
        $this->permisos->exigir($usuario, 'inventario.devoluciones.crear');

        return DB::transaction(function () use ($usuario, $datos) {
            $empresaId = (int) $usuario->empresa_id;
            $tipo = (string) $datos['tipo'];
            $despacho = InventarioDespachoOrden::query()
                ->where('empresa_id', $empresaId)
                ->lockForUpdate()
                ->find((int) $datos['despacho_orden_id']);

            if (!$despacho) {
                throw ValidationException::withMessages(['despacho_orden_id' => 'La orden de despacho no existe o no pertenece a la empresa.']);
            }

            $this->validarDespachoReversible($despacho);
            $this->validarReversaTotalDuplicada($empresaId, (int) $despacho->id, $tipo);

            $detallesDespacho = InventarioDespachoDetalle::query()
                ->where('empresa_id', $empresaId)
                ->where('despacho_orden_id', $despacho->id)
                ->with(['producto:id,empresa_id,sku,nombre,maneja_lotes,costo_promedio', 'movimiento:id,empresa_id,costo_unitario', 'ubicacionOrigen'])
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $payloadDetalles = $this->normalizarDetallesCreacion($tipo, $datos['detalles'] ?? [], $detallesDespacho, $datos['ubicacion_destino_id'] ?? null);

            if (empty($payloadDetalles)) {
                throw ValidationException::withMessages(['detalles' => 'Debe existir al menos un detalle reversable/devolvible.']);
            }

            $orden = InventarioDevolucionOrden::create([
                'empresa_id' => $empresaId,
                'despacho_orden_id' => $despacho->id,
                'bodega_id' => $despacho->bodega_id,
                'codigo' => $datos['codigo'] ?? $this->generarCodigo($empresaId),
                'tipo' => $tipo,
                'estado' => InventarioDevolucionOrden::ESTADO_PENDIENTE,
                'motivo' => $this->textoOpcional($datos['motivo'] ?? $this->motivoDefault($tipo), 120) ?? $this->motivoDefault($tipo),
                'referencia' => $this->textoOpcional($datos['referencia'] ?? $despacho->referencia ?? null, 120),
                'observacion' => $this->textoOpcional($datos['observacion'] ?? null, 2000),
                'origen_modulo' => $this->textoOpcional($datos['origen_modulo'] ?? 'inventario_despacho', 80),
                'origen_id' => $datos['origen_id'] ?? $despacho->id,
                'usuario_creador_id' => $usuario->id,
                'fecha_creacion' => now(),
            ]);

            foreach ($payloadDetalles as $item) {
                /** @var InventarioDespachoDetalle $detalle */
                $detalle = $detallesDespacho->get($item['despacho_detalle_id']);
                if (!$detalle) {
                    throw ValidationException::withMessages(['detalles' => 'Uno de los detalles no pertenece al despacho indicado.']);
                }

                $despachada = $this->redondearCantidad((float) $detalle->cantidad_despachada);
                $yaReversada = $this->cantidadConfirmadaReversada($empresaId, (int) $detalle->id);
                $pendienteReservada = $this->cantidadPendienteReservada($empresaId, (int) $detalle->id);
                $reversable = $this->redondearCantidad(max(0, $despachada - $yaReversada - $pendienteReservada));
                $cantidadDevolver = $this->redondearCantidad((float) $item['cantidad_devolver']);

                if ($cantidadDevolver <= 0) {
                    throw ValidationException::withMessages(['detalles' => 'La cantidad a devolver/reversar debe ser mayor a cero.']);
                }

                if ($cantidadDevolver > $reversable + 0.0001) {
                    throw ValidationException::withMessages(['detalles' => 'No se puede devolver/reversar más de lo pendiente reversable.']);
                }

                $ubicacionDestinoId = $this->resolverUbicacionDestinoId($tipo, $detalle, $item['ubicacion_destino_id'] ?? null);

                InventarioDevolucionDetalle::create([
                    'empresa_id' => $empresaId,
                    'devolucion_orden_id' => $orden->id,
                    'despacho_detalle_id' => $detalle->id,
                    'producto_id' => $detalle->producto_id,
                    'bodega_id' => $detalle->bodega_id,
                    'ubicacion_destino_id' => $ubicacionDestinoId,
                    'lote_id' => $detalle->lote_id,
                    'cantidad_despachada_original' => $despachada,
                    'cantidad_ya_reversada' => $yaReversada,
                    'cantidad_devolver' => $cantidadDevolver,
                    'cantidad_aceptada' => 0,
                    'cantidad_rechazada' => 0,
                    'estado' => InventarioDevolucionDetalle::ESTADO_PENDIENTE,
                    'motivo' => $this->textoOpcional($item['motivo'] ?? null, 120),
                    'observacion' => $this->textoOpcional($item['observacion'] ?? null, 2000),
                ]);
            }

            $this->auditarDevolucion($usuario, InventarioAuditoriaEvento::ACCION_DEVOLUCION_CREADA, $orden, 'Devolución/reversa post-despacho creada.', [
                'tipo' => $orden->tipo,
                'despacho_orden_id' => $despacho->id,
                'total_detalles' => count($payloadDetalles),
            ], $tipo === InventarioDevolucionOrden::TIPO_DIFERENCIA_POST_DESPACHO ? InventarioAuditoriaEvento::SEVERIDAD_WARNING : InventarioAuditoriaEvento::SEVERIDAD_INFO);

            return $this->cargarOrden($orden->refresh());
        });
    }

    public function confirmar(User $usuario, int $id, array $datos = []): InventarioDevolucionOrden
    {
        $this->permisos->exigir($usuario, 'inventario.devoluciones.confirmar');

        return DB::transaction(function () use ($usuario, $id, $datos) {
            $empresaId = (int) $usuario->empresa_id;
            $orden = InventarioDevolucionOrden::query()
                ->where('empresa_id', $empresaId)
                ->lockForUpdate()
                ->find($id);

            if (!$orden) {
                throw new Exception('La devolución/reversa no existe o no pertenece a la empresa.');
            }

            if (!$orden->puedeConfirmarse()) {
                throw new Exception('Solo se pueden confirmar devoluciones/reversas pendientes.');
            }

            $despacho = InventarioDespachoOrden::query()
                ->where('empresa_id', $empresaId)
                ->lockForUpdate()
                ->find($orden->despacho_orden_id);

            if (!$despacho) {
                throw new Exception('El despacho asociado no existe o no pertenece a la empresa.');
            }

            $this->validarDespachoReversible($despacho);

            $overrides = $this->mapearDetallesConfirmacion($datos['detalles'] ?? []);
            $detalles = InventarioDevolucionDetalle::query()
                ->where('empresa_id', $empresaId)
                ->where('devolucion_orden_id', $orden->id)
                ->with(['despachoDetalle.movimiento', 'despachoDetalle.producto'])
                ->lockForUpdate()
                ->get();

            if ($detalles->isEmpty()) {
                throw new Exception('La devolución/reversa no tiene detalles para confirmar.');
            }

            $hayDiferencias = false;

            foreach ($detalles as $detalle) {
                $despachoDetalle = $detalle->despachoDetalle;
                if (!$despachoDetalle || (int) $despachoDetalle->empresa_id !== $empresaId || (int) $despachoDetalle->despacho_orden_id !== (int) $despacho->id) {
                    throw new Exception('Un detalle de devolución no pertenece al despacho asociado.');
                }

                $yaReversada = $this->cantidadConfirmadaReversada($empresaId, (int) $despachoDetalle->id);
                $despachada = $this->redondearCantidad((float) $despachoDetalle->cantidad_despachada);
                $reversable = $this->redondearCantidad(max(0, $despachada - $yaReversada));
                $cantidadDevolver = $this->redondearCantidad((float) $detalle->cantidad_devolver);

                if ($cantidadDevolver > $reversable + 0.0001) {
                    throw ValidationException::withMessages(['detalles' => 'La cantidad pendiente reversable cambió. Actualiza la devolución/reversa antes de confirmar.']);
                }

                $override = $overrides[$detalle->id] ?? [];
                $cantidadAceptada = $this->resolverCantidadAceptada($orden, $detalle, $override);
                $cantidadRechazada = $this->redondearCantidad(max(0, $cantidadDevolver - $cantidadAceptada));

                if ($cantidadAceptada > $cantidadDevolver + 0.0001) {
                    throw ValidationException::withMessages(['detalles' => 'No se puede aceptar más cantidad que la solicitada para devolución/reversa.']);
                }

                $movimientoId = null;
                $ubicacionDestinoId = $override['ubicacion_destino_id'] ?? $detalle->ubicacion_destino_id;

                if ($cantidadAceptada > 0) {
                    if (!$ubicacionDestinoId) {
                        throw ValidationException::withMessages(['ubicacion_destino_id' => 'Debe informar ubicación destino para reingresar stock físico.']);
                    }

                    $this->validarUbicacionDestino((int) $ubicacionDestinoId, $empresaId, (int) $detalle->bodega_id);
                    $movimiento = $this->registrarMovimientoEntrada($usuario, $orden, $detalle, $despachoDetalle, $cantidadAceptada, (int) $ubicacionDestinoId, $override['observacion'] ?? null);
                    $movimientoId = (int) $movimiento->id;
                }

                $estadoDetalle = $this->resolverEstadoDetalle($cantidadAceptada, $cantidadDevolver);
                if ($estadoDetalle !== InventarioDevolucionDetalle::ESTADO_ACEPTADO) {
                    $hayDiferencias = true;
                }

                $detalle->update([
                    'ubicacion_destino_id' => $ubicacionDestinoId,
                    'cantidad_ya_reversada' => $yaReversada,
                    'cantidad_aceptada' => $cantidadAceptada,
                    'cantidad_rechazada' => $cantidadRechazada,
                    'estado' => $estadoDetalle,
                    'observacion' => $this->textoOpcional($override['observacion'] ?? $detalle->observacion, 2000),
                    'movimiento_inventario_id' => $movimientoId,
                ]);
            }

            $orden->update([
                'estado' => $hayDiferencias ? InventarioDevolucionOrden::ESTADO_CON_DIFERENCIAS : InventarioDevolucionOrden::ESTADO_CONFIRMADA,
                'usuario_confirmador_id' => $usuario->id,
                'fecha_confirmacion' => now(),
                'observacion' => $this->textoOpcional($datos['observacion'] ?? $orden->observacion, 2000),
            ]);

            $this->auditarDevolucion($usuario, $this->accionConfirmacion($orden), $orden, 'Devolución/reversa post-despacho confirmada.', [
                'tipo' => $orden->tipo,
                'despacho_orden_id' => $despacho->id,
                'hay_diferencias' => $hayDiferencias,
                'total_detalles' => $detalles->count(),
                'cantidad_aceptada_total' => $this->redondearCantidad((float) $detalles->sum('cantidad_aceptada')),
            ], InventarioAuditoriaEvento::SEVERIDAD_CRITICAL);

            return $this->cargarOrden($orden->refresh());
        });
    }

    public function cancelar(User $usuario, int $id, array $datos = []): InventarioDevolucionOrden
    {
        $this->permisos->exigir($usuario, 'inventario.devoluciones.cancelar');

        return DB::transaction(function () use ($usuario, $id, $datos) {
            $orden = InventarioDevolucionOrden::query()
                ->where('empresa_id', $usuario->empresa_id)
                ->lockForUpdate()
                ->find($id);

            if (!$orden) {
                throw new Exception('La devolución/reversa no existe o no pertenece a la empresa.');
            }

            if (!$orden->puedeCancelarse()) {
                throw new Exception('Solo se pueden cancelar devoluciones/reversas pendientes.');
            }

            InventarioDevolucionDetalle::where('empresa_id', $usuario->empresa_id)
                ->where('devolucion_orden_id', $orden->id)
                ->update(['estado' => InventarioDevolucionDetalle::ESTADO_CANCELADO]);

            $orden->update([
                'estado' => InventarioDevolucionOrden::ESTADO_CANCELADA,
                'fecha_cancelacion' => now(),
                'observacion' => $this->textoOpcional($datos['observacion'] ?? $orden->observacion, 2000),
            ]);

            $this->auditarDevolucion($usuario, InventarioAuditoriaEvento::ACCION_DEVOLUCION_CANCELADA, $orden, 'Devolución/reversa post-despacho cancelada.', [
                'tipo' => $orden->tipo,
                'observacion_cancelacion' => $datos['observacion'] ?? null,
            ], InventarioAuditoriaEvento::SEVERIDAD_WARNING);

            return $this->cargarOrden($orden->refresh());
        });
    }

    public function reporte(User $usuario, array $filtros = []): array
    {
        $this->permisos->exigirAlguno($usuario, [
            'inventario.reportes.devoluciones',
            'inventario.devoluciones.ver',
        ]);

        $base = InventarioDevolucionOrden::query()
            ->where('empresa_id', $usuario->empresa_id)
            ->when(!empty($filtros['bodega_id']), fn (Builder $query) => $query->where('bodega_id', (int) $filtros['bodega_id']))
            ->when(!empty($filtros['tipo']), fn (Builder $query) => $query->where('tipo', $filtros['tipo']))
            ->when(!empty($filtros['estado']), fn (Builder $query) => $query->where('estado', $filtros['estado']))
            ->when(!empty($filtros['desde']), fn (Builder $query) => $query->whereDate('fecha_creacion', '>=', $filtros['desde']))
            ->when(!empty($filtros['hasta']), fn (Builder $query) => $query->whereDate('fecha_creacion', '<=', $filtros['hasta']));

        $resumenPorEstado = (clone $base)
            ->select('estado', DB::raw('COUNT(*) as total'))
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->toArray();

        $resumenPorTipo = (clone $base)
            ->select('tipo', DB::raw('COUNT(*) as total'))
            ->groupBy('tipo')
            ->pluck('total', 'tipo')
            ->toArray();

        $ordenIds = (clone $base)->pluck('id');
        $totales = InventarioDevolucionDetalle::query()
            ->where('empresa_id', $usuario->empresa_id)
            ->whereIn('devolucion_orden_id', $ordenIds)
            ->selectRaw('COALESCE(SUM(cantidad_devolver), 0) as cantidad_solicitada')
            ->selectRaw('COALESCE(SUM(cantidad_aceptada), 0) as cantidad_aceptada')
            ->selectRaw('COALESCE(SUM(cantidad_rechazada), 0) as cantidad_rechazada')
            ->first();

        return [
            'total_ordenes' => (clone $base)->count(),
            'por_estado' => $resumenPorEstado,
            'por_tipo' => $resumenPorTipo,
            'cantidad_solicitada' => $this->redondearCantidad((float) ($totales->cantidad_solicitada ?? 0)),
            'cantidad_aceptada' => $this->redondearCantidad((float) ($totales->cantidad_aceptada ?? 0)),
            'cantidad_rechazada' => $this->redondearCantidad((float) ($totales->cantidad_rechazada ?? 0)),
            'ultimas' => (clone $base)->with($this->relacionesListado())->orderByDesc('fecha_creacion')->limit(20)->get(),
        ];
    }

    private function accionConfirmacion(InventarioDevolucionOrden $orden): string
    {
        return match ($orden->tipo) {
            InventarioDevolucionOrden::TIPO_REVERSA_TOTAL => InventarioAuditoriaEvento::ACCION_REVERSA_TOTAL_CONFIRMADA,
            InventarioDevolucionOrden::TIPO_REVERSA_PARCIAL => InventarioAuditoriaEvento::ACCION_REVERSA_PARCIAL_CONFIRMADA,
            InventarioDevolucionOrden::TIPO_DIFERENCIA_POST_DESPACHO => InventarioAuditoriaEvento::ACCION_DIFERENCIA_POST_DESPACHO_REGISTRADA,
            default => InventarioAuditoriaEvento::ACCION_DEVOLUCION_CONFIRMADA,
        };
    }

    private function auditarDevolucion(
        User $usuario,
        string $accion,
        InventarioDevolucionOrden $orden,
        string $descripcion,
        array $metadata = [],
        string $severidad = InventarioAuditoriaEvento::SEVERIDAD_INFO
    ): void {
        $this->auditoria->registrarEvento($usuario, [
            'empresa_id' => (int) $orden->empresa_id,
            'accion' => $accion,
            'entidad_tipo' => InventarioDevolucionOrden::class,
            'entidad_id' => (int) $orden->id,
            'severidad' => $severidad,
            'descripcion' => $descripcion,
            'referencia' => $orden->referencia ?? $orden->codigo,
            'motivo' => $orden->motivo,
            'observacion' => $orden->observacion,
            'origen_modulo' => $orden->origen_modulo,
            'origen_id' => $orden->origen_id,
            'metadata_json' => array_merge([
                'codigo' => $orden->codigo,
                'estado' => $orden->estado,
                'tipo' => $orden->tipo,
                'despacho_orden_id' => $orden->despacho_orden_id,
                'bodega_id' => $orden->bodega_id,
            ], $metadata),
        ]);
    }

    private function cargarOrden(InventarioDevolucionOrden $orden): InventarioDevolucionOrden
    {
        return $orden->load($this->relacionesDetalle());
    }

    private function relacionesListado(): array
    {
        return [
            'bodega:id,empresa_id,codigo,nombre,estado',
            'despacho:id,empresa_id,bodega_id,codigo,estado,referencia,motivo,fecha_confirmacion',
            'detalles.producto:id,empresa_id,sku,nombre,maneja_lotes',
            'detalles.ubicacionDestino:id,empresa_id,bodega_id,codigo,nombre,tipo,activo',
            'detalles.lote:id,empresa_id,producto_id,codigo_lote,fecha_vencimiento,estado_operativo,activo',
        ];
    }

    private function relacionesDetalle(): array
    {
        return array_merge($this->relacionesListado(), [
            'detalles.despachoDetalle:id,empresa_id,despacho_orden_id,producto_id,bodega_id,ubicacion_origen_id,lote_id,cantidad_despachada,cantidad_faltante,estado,movimiento_inventario_id',
            'detalles.despachoDetalle.ubicacionOrigen:id,empresa_id,bodega_id,codigo,nombre,tipo,activo',
            'detalles.movimiento:id,empresa_id,tipo,cantidad,bodega_destino_id,ubicacion_destino_id,costo_unitario,costo_total,referencia,motivo,fecha_movimiento',
        ]);
    }

    private function normalizarDetallesCreacion(string $tipo, array $detallesPayload, $detallesDespacho, mixed $ubicacionDestinoGlobal): array
    {
        if ($tipo === InventarioDevolucionOrden::TIPO_REVERSA_TOTAL) {
            return $detallesDespacho
                ->map(function (InventarioDespachoDetalle $detalle) use ($ubicacionDestinoGlobal) {
                    $reversable = $this->redondearCantidad(
                        (float) $detalle->cantidad_despachada
                        - $this->cantidadConfirmadaReversada((int) $detalle->empresa_id, (int) $detalle->id)
                        - $this->cantidadPendienteReservada((int) $detalle->empresa_id, (int) $detalle->id)
                    );

                    if ($reversable <= 0) {
                        return null;
                    }

                    return [
                        'despacho_detalle_id' => (int) $detalle->id,
                        'cantidad_devolver' => $reversable,
                        'ubicacion_destino_id' => $ubicacionDestinoGlobal ?: $detalle->ubicacion_origen_id,
                    ];
                })
                ->filter()
                ->values()
                ->all();
        }

        if (empty($detallesPayload)) {
            throw ValidationException::withMessages(['detalles' => 'Debe informar detalles para devolución, reversa parcial o diferencia post-despacho.']);
        }

        return array_map(function (array $item) use ($ubicacionDestinoGlobal) {
            $detalleId = $item['despacho_detalle_id'] ?? $item['detalle_id'] ?? $item['id'] ?? null;
            if (!$detalleId) {
                throw ValidationException::withMessages(['detalles' => 'Cada detalle debe informar despacho_detalle_id.']);
            }

            if (!isset($item['cantidad_devolver']) || !is_numeric($item['cantidad_devolver'])) {
                throw ValidationException::withMessages(['detalles' => 'Cada detalle debe informar cantidad_devolver numérica.']);
            }

            return [
                'despacho_detalle_id' => (int) $detalleId,
                'cantidad_devolver' => $this->redondearCantidad((float) $item['cantidad_devolver']),
                'ubicacion_destino_id' => $item['ubicacion_destino_id'] ?? $ubicacionDestinoGlobal,
                'motivo' => $item['motivo'] ?? null,
                'observacion' => $item['observacion'] ?? null,
            ];
        }, $detallesPayload);
    }

    private function resolverUbicacionDestinoId(string $tipo, InventarioDespachoDetalle $detalle, mixed $ubicacionDestinoId): ?int
    {
        $ubicacionDestinoId = $ubicacionDestinoId ?: $detalle->ubicacion_origen_id;

        if ($tipo === InventarioDevolucionOrden::TIPO_DIFERENCIA_POST_DESPACHO && !$ubicacionDestinoId) {
            return null;
        }

        if (!$ubicacionDestinoId) {
            throw ValidationException::withMessages(['ubicacion_destino_id' => 'Debe informar ubicación destino para devolución/reversa física.']);
        }

        $this->validarUbicacionDestino((int) $ubicacionDestinoId, (int) $detalle->empresa_id, (int) $detalle->bodega_id);

        return (int) $ubicacionDestinoId;
    }

    private function validarUbicacionDestino(int $ubicacionId, int $empresaId, int $bodegaId): void
    {
        $existe = InventarioUbicacion::query()
            ->where('id', $ubicacionId)
            ->where('empresa_id', $empresaId)
            ->where('bodega_id', $bodegaId)
            ->where('activo', true)
            ->exists();

        if (!$existe) {
            throw ValidationException::withMessages(['ubicacion_destino_id' => 'La ubicación destino no existe, no está activa o no pertenece a la bodega/empresa.']);
        }
    }

    private function validarDespachoReversible(InventarioDespachoOrden $despacho): void
    {
        if (!in_array($despacho->estado, [InventarioDespachoOrden::ESTADO_DESPACHADO, InventarioDespachoOrden::ESTADO_CON_DIFERENCIAS], true)) {
            throw ValidationException::withMessages(['despacho_orden_id' => 'Solo se puede devolver/reversar sobre despachos confirmados o con diferencias.']);
        }
    }

    private function validarReversaTotalDuplicada(int $empresaId, int $despachoId, string $tipo): void
    {
        if ($tipo !== InventarioDevolucionOrden::TIPO_REVERSA_TOTAL) {
            return;
        }

        $existe = InventarioDevolucionOrden::query()
            ->where('empresa_id', $empresaId)
            ->where('despacho_orden_id', $despachoId)
            ->where('tipo', InventarioDevolucionOrden::TIPO_REVERSA_TOTAL)
            ->whereIn('estado', [InventarioDevolucionOrden::ESTADO_PENDIENTE, InventarioDevolucionOrden::ESTADO_CONFIRMADA, InventarioDevolucionOrden::ESTADO_CON_DIFERENCIAS])
            ->exists();

        if ($existe) {
            throw ValidationException::withMessages(['tipo' => 'Ya existe una reversa total pendiente o confirmada para este despacho.']);
        }
    }

    private function cantidadConfirmadaReversada(int $empresaId, int $despachoDetalleId): float
    {
        $cantidad = InventarioDevolucionDetalle::query()
            ->join('inventario_devolucion_ordenes as orden', 'orden.id', '=', 'inventario_devolucion_detalles.devolucion_orden_id')
            ->where('inventario_devolucion_detalles.empresa_id', $empresaId)
            ->where('inventario_devolucion_detalles.despacho_detalle_id', $despachoDetalleId)
            ->whereIn('orden.estado', [InventarioDevolucionOrden::ESTADO_CONFIRMADA, InventarioDevolucionOrden::ESTADO_CON_DIFERENCIAS])
            ->sum('inventario_devolucion_detalles.cantidad_aceptada');

        return $this->redondearCantidad((float) $cantidad);
    }


    private function cantidadPendienteReservada(int $empresaId, int $despachoDetalleId): float
    {
        $cantidad = InventarioDevolucionDetalle::query()
            ->join('inventario_devolucion_ordenes as orden', 'orden.id', '=', 'inventario_devolucion_detalles.devolucion_orden_id')
            ->where('inventario_devolucion_detalles.empresa_id', $empresaId)
            ->where('inventario_devolucion_detalles.despacho_detalle_id', $despachoDetalleId)
            ->where('orden.estado', InventarioDevolucionOrden::ESTADO_PENDIENTE)
            ->sum('inventario_devolucion_detalles.cantidad_devolver');

        return $this->redondearCantidad((float) $cantidad);
    }

    private function mapearDetallesConfirmacion(array $detallesPayload): array
    {
        $map = [];

        foreach ($detallesPayload as $item) {
            $id = $item['devolucion_detalle_id'] ?? $item['detalle_id'] ?? $item['id'] ?? null;
            if (!$id) {
                continue;
            }

            $map[(int) $id] = $item;
        }

        return $map;
    }

    private function resolverCantidadAceptada(InventarioDevolucionOrden $orden, InventarioDevolucionDetalle $detalle, array $override): float
    {
        if (isset($override['cantidad_aceptada'])) {
            if (!is_numeric($override['cantidad_aceptada'])) {
                throw ValidationException::withMessages(['cantidad_aceptada' => 'La cantidad aceptada debe ser numérica.']);
            }

            $cantidadAceptada = $this->redondearCantidad((float) $override['cantidad_aceptada']);
            if ($cantidadAceptada < 0) {
                throw ValidationException::withMessages(['cantidad_aceptada' => 'La cantidad aceptada no puede ser negativa.']);
            }

            return $cantidadAceptada;
        }

        if ($orden->tipo === InventarioDevolucionOrden::TIPO_DIFERENCIA_POST_DESPACHO) {
            return 0.0;
        }

        return $this->redondearCantidad((float) $detalle->cantidad_devolver);
    }

    private function registrarMovimientoEntrada(
        User $usuario,
        InventarioDevolucionOrden $orden,
        InventarioDevolucionDetalle $detalle,
        InventarioDespachoDetalle $despachoDetalle,
        float $cantidadAceptada,
        int $ubicacionDestinoId,
        ?string $observacionDetalle = null
    ): MovimientoInventario {
        $movimientoOriginal = $despachoDetalle->movimiento;
        $costoUnitario = $movimientoOriginal?->costo_unitario !== null
            ? (float) $movimientoOriginal->costo_unitario
            : (float) ($despachoDetalle->producto?->costo_promedio ?? 0);

        return $this->movimientoService->registrarMovimiento([
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $detalle->producto_id,
            'bodega_destino_id' => $detalle->bodega_id,
            'ubicacion_destino_id' => $ubicacionDestinoId,
            'lote_id' => $detalle->lote_id,
            'cantidad' => $cantidadAceptada,
            'costo_unitario' => $costoUnitario,
            'referencia' => $orden->codigo,
            'motivo' => MovimientoInventario::MOTIVO_DEVOLUCION,
            'observacion' => trim(sprintf(
                '%s post-despacho %s. %s',
                strtolower(str_replace('_', ' ', $orden->tipo)),
                $orden->despacho?->codigo ?? ('#' . $orden->despacho_orden_id),
                (string) ($observacionDetalle ?: $detalle->observacion ?: $orden->observacion ?: '')
            )),
            'fecha_movimiento' => now(),
            '_origen_operativo' => 'inventario_devolucion',
        ], (int) $usuario->empresa_id, (int) $usuario->id);
    }

    private function resolverEstadoDetalle(float $cantidadAceptada, float $cantidadDevolver): string
    {
        if ($cantidadAceptada <= 0) {
            return InventarioDevolucionDetalle::ESTADO_RECHAZADO;
        }

        if ($cantidadAceptada + 0.0001 >= $cantidadDevolver) {
            return InventarioDevolucionDetalle::ESTADO_ACEPTADO;
        }

        return InventarioDevolucionDetalle::ESTADO_PARCIAL;
    }

    private function generarCodigo(int $empresaId): string
    {
        $prefijo = 'DEV-' . now()->format('Ymd') . '-';
        $totalDia = InventarioDevolucionOrden::where('empresa_id', $empresaId)
            ->where('codigo', 'like', $prefijo . '%')
            ->count() + 1;

        return $prefijo . str_pad((string) $totalDia, 5, '0', STR_PAD_LEFT);
    }

    private function motivoDefault(string $tipo): string
    {
        return match ($tipo) {
            InventarioDevolucionOrden::TIPO_REVERSA_TOTAL => 'reversa_total_despacho',
            InventarioDevolucionOrden::TIPO_REVERSA_PARCIAL => 'reversa_parcial_despacho',
            InventarioDevolucionOrden::TIPO_DIFERENCIA_POST_DESPACHO => 'diferencia_post_despacho',
            default => 'devolucion_post_despacho',
        };
    }

    private function textoOpcional(mixed $valor, int $max): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return mb_substr(trim((string) $valor), 0, $max);
    }

    private function normalizarPerPage(mixed $perPage): int
    {
        $perPage = (int) $perPage;

        if ($perPage <= 0) {
            return 15;
        }

        return min($perPage, 100);
    }

    private function redondearCantidad(float $value): float
    {
        return round($value, 4);
    }
}
