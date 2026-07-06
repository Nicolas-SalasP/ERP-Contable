<?php

namespace App\Domains\Inventario\Services\Reportes;

use App\Domains\Core\Models\User;
use App\Domains\Inventario\Models\MovimientoInventario;
use App\Domains\Inventario\Services\InventarioPermisoService;
use App\Domains\Inventario\Services\Reportes\Concerns\ManejaCalculosReporte;
use Illuminate\Database\Eloquent\Builder;

class ReporteMovimientosService
{
    use ManejaCalculosReporte;

    private const DEFAULT_LIMIT = 200;
    private const MAX_LIMIT = 1000;

    public function __construct(
        private readonly InventarioPermisoService $permisos,
    ) {
    }

    public function generar(User $usuario, array $filtros = []): array
    {
        $this->permisos->exigir($usuario, 'inventario.reportes.ver');

        $empresaId = (int) $usuario->empresa_id;
        $limit = $this->normalizarLimit($filtros['limit'] ?? self::DEFAULT_LIMIT);

        $query = MovimientoInventario::query()
            ->where('empresa_id', $empresaId)
            ->with([
                'producto:id,empresa_id,sku,nombre,activo',
                'bodegaOrigen:id,empresa_id,codigo,nombre,estado',
                'bodegaDestino:id,empresa_id,codigo,nombre,estado',
                'ubicacionOrigen:id,empresa_id,bodega_id,codigo,nombre,tipo,activo',
                'ubicacionDestino:id,empresa_id,bodega_id,codigo,nombre,tipo,activo',
            ]);

        $this->aplicarFiltros($query, $filtros);

        $items = (clone $query)
            ->orderByDesc('fecha_movimiento')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (MovimientoInventario $movimiento) => [
                'id' => (int) $movimiento->id,
                'fecha_movimiento' => $movimiento->fecha_movimiento?->toDateTimeString(),
                'tipo' => $movimiento->tipo,
                'producto_id' => (int) $movimiento->producto_id,
                'producto_sku' => $movimiento->producto?->sku,
                'producto_nombre' => $movimiento->producto?->nombre,
                'bodega_origen_id' => $movimiento->bodega_origen_id ? (int) $movimiento->bodega_origen_id : null,
                'bodega_origen_nombre' => $movimiento->bodegaOrigen?->nombre,
                'bodega_destino_id' => $movimiento->bodega_destino_id ? (int) $movimiento->bodega_destino_id : null,
                'bodega_destino_nombre' => $movimiento->bodegaDestino?->nombre,
                'ubicacion_origen_id' => $movimiento->ubicacion_origen_id ? (int) $movimiento->ubicacion_origen_id : null,
                'ubicacion_origen_codigo' => $movimiento->ubicacionOrigen?->codigo,
                'ubicacion_origen_nombre' => $movimiento->ubicacionOrigen?->nombre,
                'ubicacion_destino_id' => $movimiento->ubicacion_destino_id ? (int) $movimiento->ubicacion_destino_id : null,
                'ubicacion_destino_codigo' => $movimiento->ubicacionDestino?->codigo,
                'ubicacion_destino_nombre' => $movimiento->ubicacionDestino?->nombre,
                'estado_stock_origen' => $movimiento->estado_stock_origen,
                'estado_stock_destino' => $movimiento->estado_stock_destino,
                'cantidad' => $this->redondear((float) $movimiento->cantidad),
                'costo_unitario' => $this->redondear((float) $movimiento->costo_unitario),
                'costo_total' => $this->redondear((float) $movimiento->costo_total),
                'referencia' => $movimiento->referencia,
                'motivo' => $movimiento->motivo,
                'observacion' => $movimiento->observacion,
            ])
            ->values();

        $resumenPorTipo = (clone $query)
            ->select('tipo')
            ->selectRaw('COUNT(*) as total_movimientos')
            ->selectRaw('SUM(cantidad) as cantidad_total')
            ->selectRaw('SUM(costo_total) as costo_total')
            ->groupBy('tipo')
            ->toBase()
            ->get()
            ->mapWithKeys(fn ($item) => [
                $item->tipo => [
                    'total_movimientos' => (int) $item->total_movimientos,
                    'cantidad_total' => $this->redondear((float) $item->cantidad_total),
                    'costo_total' => $this->redondear((float) $item->costo_total),
                ],
            ]);

        return [
            'data' => $items,
            'resumen' => [
                'filas' => $items->count(),
                'cantidad_total' => $this->redondear((float) $items->sum('cantidad')),
                'costo_total' => $this->redondear((float) $items->sum('costo_total')),
                'por_tipo' => $resumenPorTipo,
            ],
            'metadata' => $this->metadata($filtros, $limit),
        ];
    }

    private function aplicarFiltros(Builder $query, array $filtros): void
    {
        $query
            ->when(!empty($filtros['producto_id']), fn (Builder $query) => $query->where('producto_id', (int) $filtros['producto_id']))
            ->when(!empty($filtros['bodega_id']), fn (Builder $query) => $query->where(function (Builder $subQuery) use ($filtros) {
                $bodegaId = (int) $filtros['bodega_id'];
                $subQuery->where('bodega_origen_id', $bodegaId)->orWhere('bodega_destino_id', $bodegaId);
            }))
            ->when(!empty($filtros['ubicacion_id']), fn (Builder $query) => $query->where(function (Builder $subQuery) use ($filtros) {
                $ubicacionId = (int) $filtros['ubicacion_id'];
                $subQuery->where('ubicacion_origen_id', $ubicacionId)->orWhere('ubicacion_destino_id', $ubicacionId);
            }))
            ->when(!empty($filtros['tipo']), fn (Builder $query) => $query->where('tipo', $filtros['tipo']))
            ->when(!empty($filtros['desde']), fn (Builder $query) => $query->whereDate('fecha_movimiento', '>=', $filtros['desde']))
            ->when(!empty($filtros['hasta']), fn (Builder $query) => $query->whereDate('fecha_movimiento', '<=', $filtros['hasta']));
    }
}
