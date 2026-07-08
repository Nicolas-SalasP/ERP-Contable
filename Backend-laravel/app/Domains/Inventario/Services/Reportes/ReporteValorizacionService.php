<?php

namespace App\Domains\Inventario\Services\Reportes;

use App\Domains\Core\Models\User;
use App\Domains\Inventario\Models\StockProducto;
use App\Domains\Inventario\Services\InventarioPermisoService;
use App\Domains\Inventario\Services\Reportes\Concerns\ManejaCalculosReporte;

class ReporteValorizacionService
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

        $query = StockProducto::query()
            ->where('inventario_stock.empresa_id', $empresaId)
            ->join('inventario_productos', 'inventario_productos.id', '=', 'inventario_stock.producto_id')
            ->leftJoin('inventario_bodegas', 'inventario_bodegas.id', '=', 'inventario_stock.bodega_id')
            ->when(!empty($filtros['producto_id']), fn ($query) => $query->where('inventario_stock.producto_id', (int) $filtros['producto_id']))
            ->when(!empty($filtros['bodega_id']), fn ($query) => $query->where('inventario_stock.bodega_id', (int) $filtros['bodega_id']));

        $porProducto = (clone $query)
            ->select([
                'inventario_stock.producto_id',
                'inventario_productos.sku as producto_sku',
                'inventario_productos.nombre as producto_nombre',
            ])
            ->selectRaw('SUM(inventario_stock.stock_actual) as stock_total')
            ->selectRaw('AVG(inventario_stock.costo_promedio) as costo_promedio_ponderado')
            ->selectRaw('SUM(inventario_stock.valor_total) as valor_total')
            ->groupBy('inventario_stock.producto_id', 'inventario_productos.sku', 'inventario_productos.nombre')
            ->orderByDesc('valor_total')
            ->limit($limit)
            ->toBase()
            ->get()
            ->map(fn ($item) => [
                'producto_id' => (int) $item->producto_id,
                'producto_sku' => $item->producto_sku,
                'producto_nombre' => $item->producto_nombre,
                'stock_total' => $this->redondear((float) $item->stock_total),
                'costo_promedio_ponderado' => $this->redondear((float) $item->costo_promedio_ponderado),
                'valor_total' => $this->redondear((float) $item->valor_total),
                'estado_valorizacion' => (float) $item->valor_total <= 0 && (float) $item->stock_total > 0 ? 'valor_cero_o_inconsistente' : 'ok',
            ])
            ->values();

        $porBodega = (clone $query)
            ->select([
                'inventario_stock.bodega_id',
                'inventario_bodegas.codigo as bodega_codigo',
                'inventario_bodegas.nombre as bodega_nombre',
            ])
            ->selectRaw('SUM(inventario_stock.stock_actual) as stock_total')
            ->selectRaw('SUM(inventario_stock.valor_total) as valor_total')
            ->groupBy('inventario_stock.bodega_id', 'inventario_bodegas.codigo', 'inventario_bodegas.nombre')
            ->orderByDesc('valor_total')
            ->limit($limit)
            ->toBase()
            ->get()
            ->map(fn ($item) => [
                'bodega_id' => (int) $item->bodega_id,
                'bodega_codigo' => $item->bodega_codigo,
                'bodega_nombre' => $item->bodega_nombre,
                'stock_total' => $this->redondear((float) $item->stock_total),
                'valor_total' => $this->redondear((float) $item->valor_total),
            ])
            ->values();

        return [
            'data' => [
                'por_producto' => $porProducto,
                'por_bodega' => $porBodega,
                'ranking_productos_por_valor' => $porProducto->take(10)->values(),
                'productos_valor_cero_o_inconsistente' => $porProducto
                    ->where('estado_valorizacion', 'valor_cero_o_inconsistente')
                    ->values(),
            ],
            'resumen' => [
                'valor_total' => $this->redondear((float) $porProducto->sum('valor_total')),
                'stock_total' => $this->redondear((float) $porProducto->sum('stock_total')),
                'productos_con_valor' => $porProducto->where('valor_total', '>', 0)->count(),
                'productos_valor_cero_o_inconsistente' => $porProducto->where('estado_valorizacion', 'valor_cero_o_inconsistente')->count(),
            ],
            'metadata' => $this->metadata($filtros, $limit),
        ];
    }
}
