<?php

namespace App\Domains\Inventario\Services\Reportes;

use App\Domains\Core\Models\User;
use App\Domains\Inventario\Models\ReservaDetalleInventario;
use App\Domains\Inventario\Models\ReservaInventario;
use App\Domains\Inventario\Models\StockProducto;
use App\Domains\Inventario\Services\InventarioPermisoService;
use App\Domains\Inventario\Services\Reportes\Concerns\ManejaCalculosReporte;
use Illuminate\Database\Eloquent\Builder;

class ReporteStockService
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
        $comprometido = $this->stockComprometidoMap($empresaId);

        $items = StockProducto::query()
            ->where('empresa_id', $empresaId)
            ->with([
                'producto:id,empresa_id,sku,nombre,stock_minimo,costo_promedio,activo,maneja_lotes',
                'bodega:id,empresa_id,codigo,nombre,estado',
            ])
            ->when(!empty($filtros['producto_id']), fn (Builder $query) => $query->where('producto_id', (int) $filtros['producto_id']))
            ->when(!empty($filtros['bodega_id']), fn (Builder $query) => $query->where('bodega_id', (int) $filtros['bodega_id']))
            ->orderBy('producto_id')
            ->orderBy('bodega_id')
            ->limit($limit)
            ->get()
            ->map(function (StockProducto $stock) use ($comprometido) {
                $stockActual = (float) $stock->stock_actual;
                $stockMinimo = (float) ($stock->producto->stock_minimo ?? 0);
                $key = $this->claveProductoBodega((int) $stock->producto_id, (int) $stock->bodega_id);
                $cantidadComprometida = (float) ($comprometido[$key] ?? 0);

                return [
                    'producto_id' => (int) $stock->producto_id,
                    'producto_sku' => $stock->producto?->sku,
                    'producto_nombre' => $stock->producto?->nombre,
                    'producto_activo' => (bool) ($stock->producto->activo ?? false),
                    'bodega_id' => (int) $stock->bodega_id,
                    'bodega_codigo' => $stock->bodega?->codigo,
                    'bodega_nombre' => $stock->bodega?->nombre,
                    'stock_actual' => $this->redondear($stockActual),
                    'stock_minimo' => $this->redondear($stockMinimo),
                    'stock_comprometido' => $this->redondear($cantidadComprometida),
                    'stock_disponible' => $this->redondear(max($stockActual - $cantidadComprometida, 0)),
                    'costo_promedio' => $this->redondear((float) $stock->costo_promedio),
                    'valor_total' => $this->redondear((float) $stock->valor_total),
                    'estado_stock' => $this->estadoStock($stockActual, $stockMinimo),
                ];
            })
            ->when(!empty($filtros['estado_stock']), function ($items) use ($filtros) {
                /** @var \Illuminate\Support\Collection<int, array<string, mixed>> $items */
                return $items->filter(fn (array $item) => $item['estado_stock'] === $filtros['estado_stock'])->values();
            })
            ->values();

        return [
            'data' => $items,
            'resumen' => [
                'filas' => $items->count(),
                'stock_total' => $this->redondear((float) $items->sum('stock_actual')),
                'stock_comprometido' => $this->redondear((float) $items->sum('stock_comprometido')),
                'stock_disponible' => $this->redondear((float) $items->sum('stock_disponible')),
                'valor_total' => $this->redondear((float) $items->sum('valor_total')),
                'productos_sin_stock' => $items->where('estado_stock', 'sin_stock')->count(),
                'productos_bajo_minimo' => $items->where('estado_stock', 'bajo_minimo')->count(),
            ],
            'metadata' => $this->metadata($filtros, $limit),
        ];
    }

    private function stockComprometidoMap(int $empresaId): array
    {
        return ReservaDetalleInventario::query()
            ->where('inventario_reserva_detalles.empresa_id', $empresaId)
            ->join('inventario_reservas', 'inventario_reservas.id', '=', 'inventario_reserva_detalles.reserva_id')
            ->whereIn('inventario_reservas.estado', ReservaInventario::estadosQueComprometenDisponibilidad())
            ->select([
                'inventario_reserva_detalles.producto_id',
                'inventario_reserva_detalles.bodega_id',
            ])
            ->selectRaw('SUM(cantidad_reservada - cantidad_consumida - cantidad_liberada) as cantidad_comprometida')
            ->groupBy('inventario_reserva_detalles.producto_id', 'inventario_reserva_detalles.bodega_id')
            ->toBase()
            ->get()
            ->mapWithKeys(fn ($item) => [
                $this->claveProductoBodega((int) $item->producto_id, (int) $item->bodega_id) => max((float) $item->cantidad_comprometida, 0),
            ])
            ->all();
    }

    private function estadoStock(float $stockActual, float $stockMinimo): string
    {
        if ($stockActual <= 0) {
            return 'sin_stock';
        }

        if ($stockMinimo > 0 && $stockActual <= $stockMinimo) {
            return 'bajo_minimo';
        }

        return 'ok';
    }
}
