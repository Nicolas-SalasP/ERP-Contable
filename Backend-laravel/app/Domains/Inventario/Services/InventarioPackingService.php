<?php

namespace App\Domains\Inventario\Services;

use App\Domains\Core\Models\User;
use App\Domains\Inventario\Models\InventarioEventoIntegracion;
use App\Domains\Inventario\Models\InventarioPackingDetalle;
use App\Domains\Inventario\Models\InventarioPackingOrden;
use App\Domains\Inventario\Models\InventarioPickingAsignacion;
use App\Domains\Inventario\Models\InventarioPickingDetalle;
use App\Domains\Inventario\Models\InventarioPickingOrden;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventarioPackingService
{
    public function __construct(
        private readonly InventarioPermisoService $permisos,
        private readonly InventarioEventoIntegracionService $eventosIntegracion
    ) {
    }

    public function listar(User $usuario, array $filtros = []): LengthAwarePaginator
    {
        $this->permisos->exigir($usuario, 'inventario.packing.ver');

        return InventarioPackingOrden::query()
            ->where('empresa_id', $usuario->empresa_id)
            ->with([
                'bodega:id,empresa_id,codigo,nombre,estado',
                'pickingOrden:id,empresa_id,bodega_id,codigo,estado,referencia',
                'detalles.producto:id,empresa_id,sku,nombre,maneja_lotes',
                'detalles.ubicacionOrigen:id,empresa_id,bodega_id,codigo,nombre,tipo,activo',
                'detalles.lote:id,empresa_id,producto_id,codigo_lote,fecha_vencimiento,estado_operativo,activo',
                'detalles.pickingAsignacion:id,empresa_id,picking_detalle_id,ubicacion_origen_id,lote_id,cantidad_asignada,cantidad_pickeada,estado',
            ])
            ->when(!empty($filtros['estado']), fn (Builder $query) => $query->where('estado', $filtros['estado']))
            ->when(!empty($filtros['bodega_id']), fn (Builder $query) => $query->where('bodega_id', (int) $filtros['bodega_id']))
            ->when(!empty($filtros['picking_orden_id']), fn (Builder $query) => $query->where('picking_orden_id', (int) $filtros['picking_orden_id']))
            ->when(!empty($filtros['search']), function (Builder $query) use ($filtros) {
                $term = '%' . trim((string) $filtros['search']) . '%';
                $query->where(function (Builder $subQuery) use ($term) {
                    $subQuery->where('codigo', 'like', $term)
                        ->orWhere('observacion', 'like', $term);
                });
            })
            ->orderByDesc('fecha_creacion')
            ->orderByDesc('id')
            ->paginate($this->normalizarPerPage($filtros['per_page'] ?? 15));
    }

    public function obtener(User $usuario, int $id): InventarioPackingOrden
    {
        $this->permisos->exigir($usuario, 'inventario.packing.ver');

        $orden = InventarioPackingOrden::query()
            ->where('empresa_id', $usuario->empresa_id)
            ->find($id);

        if (!$orden) {
            throw new Exception('La orden de packing no existe o no pertenece a la empresa.');
        }

        return $this->cargarOrden($orden);
    }

    public function crear(User $usuario, array $datos): InventarioPackingOrden
    {
        $this->permisos->exigir($usuario, 'inventario.packing.crear');

        return DB::transaction(function () use ($usuario, $datos) {
            $empresaId = (int) $usuario->empresa_id;
            $picking = InventarioPickingOrden::query()
                ->where('empresa_id', $empresaId)
                ->lockForUpdate()
                ->find((int) ($datos['picking_orden_id'] ?? 0));

            if (!$picking) {
                throw ValidationException::withMessages(['picking_orden_id' => 'La orden de picking no existe o no pertenece a la empresa.']);
            }

            if ($picking->estado === InventarioPickingOrden::ESTADO_CANCELADO) {
                throw ValidationException::withMessages(['picking_orden_id' => 'No se puede generar packing desde un picking cancelado.']);
            }

            if (!in_array($picking->estado, [InventarioPickingOrden::ESTADO_PICKING_COMPLETO, InventarioPickingOrden::ESTADO_CON_DIFERENCIAS], true)) {
                throw ValidationException::withMessages(['picking_orden_id' => 'Packing solo puede generarse desde picking completo o con diferencias aceptadas.']);
            }

            if (InventarioPackingOrden::where('empresa_id', $empresaId)->where('picking_orden_id', $picking->id)->exists()) {
                throw ValidationException::withMessages(['picking_orden_id' => 'Ya existe una orden de packing para este picking.']);
            }

            $asignacionesPicking = InventarioPickingAsignacion::where('empresa_id', $empresaId)
                ->where('picking_orden_id', $picking->id)
                ->where('cantidad_pickeada', '>', 0)
                ->lockForUpdate()
                ->get();

            if ($asignacionesPicking->isEmpty()) {
                throw ValidationException::withMessages(['detalles' => 'No existen cantidades pickeadas para empacar.']);
            }

            $orden = InventarioPackingOrden::create([
                'empresa_id' => $empresaId,
                'picking_orden_id' => $picking->id,
                'bodega_id' => $picking->bodega_id,
                'codigo' => $datos['codigo'] ?? $this->generarCodigo($empresaId),
                'estado' => InventarioPackingOrden::ESTADO_PENDIENTE,
                'observacion' => $this->textoOpcional($datos['observacion'] ?? null, 2000),
                'usuario_creador_id' => $usuario->id,
                'fecha_creacion' => now(),
            ]);

            foreach ($asignacionesPicking as $asignacionPicking) {
                /** @var InventarioPickingAsignacion $asignacionPicking */
                InventarioPackingDetalle::create([
                    'empresa_id' => $empresaId,
                    'packing_orden_id' => $orden->id,
                    'picking_detalle_id' => $asignacionPicking->picking_detalle_id,
                    'picking_asignacion_id' => $asignacionPicking->id,
                    'producto_id' => $asignacionPicking->producto_id,
                    'ubicacion_origen_id' => $asignacionPicking->ubicacion_origen_id,
                    'lote_id' => $asignacionPicking->lote_id,
                    'cantidad_pickeada' => $asignacionPicking->cantidad_pickeada,
                    'cantidad_empacada' => 0,
                    'cantidad_faltante' => 0,
                    'estado' => InventarioPackingDetalle::ESTADO_PENDIENTE,
                ]);
            }

            $orden = $this->cargarOrden($orden->refresh());
            $this->publicarEventoPacking($usuario, InventarioEventoIntegracion::EVENTO_PACKING_CREADO, $orden, [
                'picking_orden_id' => $picking->id,
                'total_detalles' => $asignacionesPicking->count(),
            ]);

            return $orden;
        });
    }

    public function iniciar(User $usuario, int $id): InventarioPackingOrden
    {
        $this->permisos->exigir($usuario, 'inventario.packing.editar');

        return DB::transaction(function () use ($usuario, $id) {
            $orden = InventarioPackingOrden::where('empresa_id', $usuario->empresa_id)->lockForUpdate()->find($id);

            if (!$orden) {
                throw new Exception('La orden de packing no existe o no pertenece a la empresa.');
            }

            if (!$orden->puedeIniciarse()) {
                throw new Exception('La orden de packing no puede iniciarse en su estado actual.');
            }

            $orden->update([
                'estado' => InventarioPackingOrden::ESTADO_EN_EMPAQUE,
                'fecha_inicio' => $orden->fecha_inicio ?? now(),
            ]);

            return $this->cargarOrden($orden->refresh());
        });
    }

    public function confirmar(User $usuario, int $id, array $datos = []): InventarioPackingOrden
    {
        $this->permisos->exigir($usuario, 'inventario.packing.confirmar');

        return DB::transaction(function () use ($usuario, $id, $datos) {
            $empresaId = (int) $usuario->empresa_id;
            $orden = InventarioPackingOrden::where('empresa_id', $empresaId)->lockForUpdate()->find($id);

            if (!$orden) {
                throw new Exception('La orden de packing no existe o no pertenece a la empresa.');
            }

            if (!$orden->puedeConfirmarse()) {
                throw new Exception('La orden de packing no puede confirmarse en su estado actual.');
            }

            $detalles = InventarioPackingDetalle::where('empresa_id', $empresaId)
                ->where('packing_orden_id', $orden->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $operaciones = $this->normalizarDetallesConfirmacion($detalles, $datos['detalles'] ?? null);

            foreach ($operaciones as $item) {
                /** @var InventarioPackingDetalle $detalle */
                $detalle = $item['detalle'];
                $cantidadEmpacada = $item['cantidad_empacada'];

                if ($cantidadEmpacada > (float) $detalle->cantidad_pickeada + 0.0001) {
                    throw ValidationException::withMessages(['cantidad_empacada' => 'No se puede empacar más de lo pickeado.']);
                }

                $faltante = $this->redondearCantidad(max(0, (float) $detalle->cantidad_pickeada - $cantidadEmpacada));

                $detalle->update([
                    'cantidad_empacada' => $cantidadEmpacada,
                    'cantidad_faltante' => $faltante,
                    'estado' => $cantidadEmpacada <= 0
                        ? InventarioPackingDetalle::ESTADO_CON_DIFERENCIAS
                        : ($faltante > 0 ? InventarioPackingDetalle::ESTADO_PARCIAL : InventarioPackingDetalle::ESTADO_EMPACADO),
                    'observacion' => $item['observacion'] ?? $detalle->observacion,
                ]);
            }

            $hayDiferencias = InventarioPackingDetalle::where('empresa_id', $empresaId)
                ->where('packing_orden_id', $orden->id)
                ->where(function (Builder $query) {
                    $query->where('estado', '!=', InventarioPackingDetalle::ESTADO_EMPACADO)
                        ->orWhereColumn('cantidad_empacada', '<', 'cantidad_pickeada');
                })
                ->exists();

            $orden->update([
                'estado' => $hayDiferencias
                    ? InventarioPackingOrden::ESTADO_CON_DIFERENCIAS
                    : InventarioPackingOrden::ESTADO_EMPACADO,
                'usuario_confirmador_id' => $usuario->id,
                'fecha_confirmacion' => now(),
                'observacion' => array_key_exists('observacion', $datos)
                    ? $this->textoOpcional($datos['observacion'], 2000)
                    : $orden->observacion,
            ]);

            $orden = $this->cargarOrden($orden->refresh());
            $this->publicarEventoPacking($usuario, InventarioEventoIntegracion::EVENTO_PACKING_CONFIRMADO, $orden, [
                'hay_diferencias' => $hayDiferencias,
                'total_operaciones' => count($operaciones),
            ], $hayDiferencias ? InventarioEventoIntegracion::PRIORIDAD_ALTA : InventarioEventoIntegracion::PRIORIDAD_NORMAL);

            return $orden;
        });
    }

    public function cancelar(User $usuario, int $id, array $datos = []): InventarioPackingOrden
    {
        $this->permisos->exigir($usuario, 'inventario.packing.cancelar');

        return DB::transaction(function () use ($usuario, $id, $datos) {
            $orden = InventarioPackingOrden::where('empresa_id', $usuario->empresa_id)->lockForUpdate()->find($id);

            if (!$orden) {
                throw new Exception('La orden de packing no existe o no pertenece a la empresa.');
            }

            if (!$orden->puedeCancelarse()) {
                throw new Exception('La orden de packing no puede cancelarse en su estado actual.');
            }

            InventarioPackingDetalle::where('empresa_id', $usuario->empresa_id)
                ->where('packing_orden_id', $orden->id)
                ->update(['estado' => InventarioPackingDetalle::ESTADO_CANCELADO]);

            $orden->update([
                'estado' => InventarioPackingOrden::ESTADO_CANCELADO,
                'fecha_cancelacion' => now(),
                'observacion' => array_key_exists('observacion', $datos)
                    ? $this->textoOpcional($datos['observacion'], 2000)
                    : $orden->observacion,
            ]);

            $orden = $this->cargarOrden($orden->refresh());
            $this->publicarEventoPacking($usuario, InventarioEventoIntegracion::EVENTO_PACKING_CANCELADO, $orden, [
                'observacion_cancelacion' => $datos['observacion'] ?? null,
            ], InventarioEventoIntegracion::PRIORIDAD_ALTA);

            return $orden;
        });
    }

    public function reporte(User $usuario, array $filtros = []): array
    {
        $this->permisos->exigirAlguno($usuario, ['inventario.reportes.packing', 'inventario.packing.ver']);

        $query = InventarioPackingOrden::query()->where('empresa_id', $usuario->empresa_id);

        if (!empty($filtros['bodega_id'])) {
            $query->where('bodega_id', (int) $filtros['bodega_id']);
        }

        $porEstado = (clone $query)
            ->select('estado', DB::raw('COUNT(*) as total'))
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->toArray();

        return [
            'resumen' => [
                'total' => (clone $query)->count(),
                'pendientes' => (clone $query)->where('estado', InventarioPackingOrden::ESTADO_PENDIENTE)->count(),
                'en_empaque' => (clone $query)->where('estado', InventarioPackingOrden::ESTADO_EN_EMPAQUE)->count(),
                'empacados' => (clone $query)->where('estado', InventarioPackingOrden::ESTADO_EMPACADO)->count(),
                'con_diferencias' => (clone $query)->where('estado', InventarioPackingOrden::ESTADO_CON_DIFERENCIAS)->count(),
                'cancelados' => (clone $query)->where('estado', InventarioPackingOrden::ESTADO_CANCELADO)->count(),
            ],
            'por_estado' => $porEstado,
        ];
    }

    private function normalizarDetallesConfirmacion($detalles, mixed $payload): array
    {
        $operaciones = [];

        if ($payload === null) {
            foreach ($detalles as $detalle) {
                $operaciones[] = [
                    'detalle' => $detalle,
                    'cantidad_empacada' => $this->redondearCantidad((float) $detalle->cantidad_pickeada),
                ];
            }

            return $operaciones;
        }

        if (!is_array($payload) || empty($payload)) {
            throw ValidationException::withMessages(['detalles' => 'Debe informar detalles válidos para confirmar packing.']);
        }

        foreach ($payload as $indice => $item) {
            $detalleId = (int) ($item['id'] ?? $item['detalle_id'] ?? 0);
            $detalle = $detalles->get($detalleId);

            if (!$detalle) {
                throw ValidationException::withMessages(["detalles.{$indice}.id" => 'El detalle informado no pertenece a la orden de packing.']);
            }

            $cantidad = $item['cantidad_empacada'] ?? null;

            if (!is_numeric($cantidad) || (float) $cantidad < 0) {
                throw ValidationException::withMessages(["detalles.{$indice}.cantidad_empacada" => 'La cantidad empacada debe ser numérica y no negativa.']);
            }

            $operaciones[] = [
                'detalle' => $detalle,
                'cantidad_empacada' => $this->redondearCantidad((float) $cantidad),
                'observacion' => $item['observacion'] ?? null,
            ];
        }

        return $operaciones;
    }

    private function cargarOrden(InventarioPackingOrden $orden): InventarioPackingOrden
    {
        return $orden->load([
            'bodega:id,empresa_id,codigo,nombre,estado',
            'pickingOrden:id,empresa_id,bodega_id,codigo,estado,referencia',
            'usuarioCreador:id,nombre,email',
            'usuarioConfirmador:id,nombre,email',
            'detalles.producto:id,empresa_id,sku,nombre,maneja_lotes',
            'detalles.ubicacionOrigen:id,empresa_id,bodega_id,codigo,nombre,tipo,activo',
            'detalles.lote:id,empresa_id,producto_id,codigo_lote,fecha_vencimiento,estado_operativo,activo',
            'detalles.pickingAsignacion:id,empresa_id,picking_detalle_id,ubicacion_origen_id,lote_id,cantidad_asignada,cantidad_pickeada,estado',
        ]);
    }

    private function publicarEventoPacking(
        User $usuario,
        string $evento,
        InventarioPackingOrden $orden,
        array $metadata = [],
        string $prioridad = InventarioEventoIntegracion::PRIORIDAD_NORMAL
    ): void {
        $this->eventosIntegracion->publicarDesdeOperacion($usuario, $evento, [
            'empresa_id' => (int) $orden->empresa_id,
            'entidad_tipo' => InventarioPackingOrden::class,
            'entidad_id' => (int) $orden->id,
            'prioridad' => $prioridad,
            'payload_json' => array_merge([
                'codigo' => $orden->codigo,
                'estado' => $orden->estado,
                'bodega_id' => $orden->bodega_id,
                'picking_orden_id' => $orden->picking_orden_id,
            ], $metadata),
            'metadata_json' => [
                'observacion' => $orden->observacion,
            ],
        ], true);
    }

    private function generarCodigo(int $empresaId): string
    {
        $correlativo = InventarioPackingOrden::where('empresa_id', $empresaId)->lockForUpdate()->count() + 1;
        return 'PACK-' . now()->format('Ymd') . '-' . str_pad((string) $correlativo, 5, '0', STR_PAD_LEFT);
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
