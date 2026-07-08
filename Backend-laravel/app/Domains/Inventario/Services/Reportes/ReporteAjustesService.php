<?php

namespace App\Domains\Inventario\Services\Reportes;

use App\Domains\Core\Models\User;
use App\Domains\Inventario\Models\AjusteCriticoInventario;
use App\Domains\Inventario\Services\InventarioPermisoService;
use App\Domains\Inventario\Services\Reportes\Concerns\ManejaCalculosReporte;
use Illuminate\Database\Eloquent\Builder;

class ReporteAjustesService
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

        $empresaId = (int) $usuario->empresa_activa_id;
        $limit = $this->normalizarLimit($filtros['limit'] ?? self::DEFAULT_LIMIT);

        $query = AjusteCriticoInventario::query()
            ->where('empresa_id', $empresaId)
            ->with([
                'producto:id,empresa_id,sku,nombre,activo',
                'bodega:id,empresa_id,codigo,nombre,estado',
                'lote:id,empresa_id,producto_id,codigo_lote,fecha_vencimiento,activo',
                'tipo:id,codigo,nombre,tipo_movimiento,activo',
                'registradoPor:id,name,email',
            ])
            ->when(!empty($filtros['producto_id']), fn (Builder $query) => $query->where('producto_id', (int) $filtros['producto_id']))
            ->when(!empty($filtros['bodega_id']), fn (Builder $query) => $query->where('bodega_id', (int) $filtros['bodega_id']))
            ->when(!empty($filtros['lote_id']), fn (Builder $query) => $query->where('lote_id', (int) $filtros['lote_id']))
            ->when(!empty($filtros['desde']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filtros['desde']))
            ->when(!empty($filtros['hasta']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filtros['hasta']));

        $items = (clone $query)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (AjusteCriticoInventario $ajuste) => [
                'id' => (int) $ajuste->id,
                'fecha' => $ajuste->created_at->toDateTimeString(),
                'producto_id' => (int) $ajuste->producto_id,
                'producto_sku' => $ajuste->producto?->sku,
                'producto_nombre' => $ajuste->producto?->nombre,
                'bodega_id' => $ajuste->bodega_id ? (int) $ajuste->bodega_id : null,
                'bodega_nombre' => $ajuste->bodega?->nombre,
                'lote_id' => $ajuste->lote_id ? (int) $ajuste->lote_id : null,
                'lote_codigo' => $ajuste->lote?->codigo_lote,
                'tipo_codigo' => $ajuste->tipo?->codigo,
                'tipo_nombre' => $ajuste->tipo?->nombre,
                'tipo_movimiento' => $ajuste->tipo?->tipo_movimiento,
                'cantidad' => $this->redondear((float) $ajuste->cantidad),
                'costo_unitario' => $this->redondear((float) $ajuste->costo_unitario),
                'costo_total' => $this->redondear((float) $ajuste->costo_total),
                'motivo' => $ajuste->motivo,
                'referencia' => $ajuste->referencia,
                'observacion' => $ajuste->observacion,
                'registrado_por' => $ajuste->registradoPor->nombre ?? $ajuste->registradoPor?->email,
            ])
            ->values();

        $rankingProductos = (clone $query)
            ->select('producto_id')
            ->selectRaw('COUNT(*) as total_ajustes')
            ->selectRaw('SUM(costo_total) as costo_total')
            ->with('producto:id,empresa_id,sku,nombre,activo')
            ->groupBy('producto_id')
            ->orderByDesc('total_ajustes')
            ->limit(10)
            ->get()
            ->map(fn ($item) => [
                'producto_id' => (int) $item->producto_id,
                'producto_sku' => $item->producto?->sku,
                'producto_nombre' => $item->producto?->nombre,
                'total_ajustes' => (int) $item->total_ajustes,
                'costo_total' => $this->redondear((float) $item->costo_total),
            ])
            ->values();

        return [
            'data' => $items,
            'resumen' => [
                'filas' => $items->count(),
                'cantidad_total' => $this->redondear((float) $items->sum('cantidad')),
                'costo_total_ajustes' => $this->redondear((float) $items->sum('costo_total')),
                'ranking_productos' => $rankingProductos,
            ],
            'metadata' => $this->metadata($filtros, $limit),
        ];
    }
}
