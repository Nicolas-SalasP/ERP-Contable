<?php

namespace App\Domains\Inventario\Services\Reportes;

use App\Domains\Core\Models\User;
use App\Domains\Inventario\Models\StockLoteInventario;
use App\Domains\Inventario\Services\InventarioPermisoService;
use App\Domains\Inventario\Services\Reportes\Concerns\ManejaCalculosReporte;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class ReporteLotesService
{
    use ManejaCalculosReporte;

    private const DEFAULT_LIMIT = 200;
    private const MAX_LIMIT = 1000;
    private const DEFAULT_DIAS_VENCIMIENTO = 30;

    public function __construct(
        private readonly InventarioPermisoService $permisos,
    ) {
    }

    public function generar(User $usuario, array $filtros = []): array
    {
        $this->permisos->exigir($usuario, 'inventario.reportes.ver');

        $empresaId = (int) $usuario->empresa_id;
        $limit = $this->normalizarLimit($filtros['limit'] ?? self::DEFAULT_LIMIT);
        $diasVencimiento = (int) ($filtros['dias_vencimiento'] ?? self::DEFAULT_DIAS_VENCIMIENTO);
        $hoy = CarbonImmutable::today();
        $hasta = $hoy->addDays(max($diasVencimiento, 0));

        $items = StockLoteInventario::query()
            ->where('empresa_id', $empresaId)
            ->with([
                'producto:id,empresa_id,sku,nombre,activo',
                'bodega:id,empresa_id,codigo,nombre,estado',
                'lote:id,empresa_id,producto_id,codigo_lote,fecha_fabricacion,fecha_vencimiento,activo,observacion',
            ])
            ->when(!empty($filtros['producto_id']), fn (Builder $query) => $query->where('producto_id', (int) $filtros['producto_id']))
            ->when(!empty($filtros['bodega_id']), fn (Builder $query) => $query->where('bodega_id', (int) $filtros['bodega_id']))
            ->when(!empty($filtros['lote_id']), fn (Builder $query) => $query->where('lote_id', (int) $filtros['lote_id']))
            ->whereHas('lote')
            ->orderByDesc('stock_actual')
            ->limit($limit)
            ->get()
            ->map(function (StockLoteInventario $stock) use ($hoy, $hasta) {
                $fechaVencimiento = $stock->lote?->fecha_vencimiento ? CarbonImmutable::parse($stock->lote->fecha_vencimiento->toDateString()) : null;
                $estado = $this->estadoLote($fechaVencimiento, $hoy, $hasta, (bool) ($stock->lote->activo ?? false));

                return [
                    'producto_id' => (int) $stock->producto_id,
                    'producto_sku' => $stock->producto?->sku,
                    'producto_nombre' => $stock->producto?->nombre,
                    'bodega_id' => (int) $stock->bodega_id,
                    'bodega_codigo' => $stock->bodega?->codigo,
                    'bodega_nombre' => $stock->bodega?->nombre,
                    'lote_id' => (int) $stock->lote_id,
                    'codigo_lote' => $stock->lote?->codigo_lote,
                    'fecha_fabricacion' => $stock->lote?->fecha_fabricacion?->toDateString(),
                    'fecha_vencimiento' => $stock->lote?->fecha_vencimiento?->toDateString(),
                    'dias_para_vencer' => $fechaVencimiento ? $hoy->diffInDays($fechaVencimiento, false) : null,
                    'stock_actual' => $this->redondear((float) $stock->stock_actual),
                    'estado_lote' => $estado,
                    'activo' => (bool) ($stock->lote->activo ?? false),
                ];
            })
            ->when(!empty($filtros['estado_lote']), function ($items) use ($filtros) {
                /** @var \Illuminate\Support\Collection<int, array<string, mixed>> $items */
                return $items->filter(fn (array $item) => $item['estado_lote'] === $filtros['estado_lote'])->values();
            })
            ->values();

        return [
            'data' => $items,
            'resumen' => [
                'filas' => $items->count(),
                'stock_total_lotes' => $this->redondear((float) $items->sum('stock_actual')),
                'lotes_vencidos' => $items->where('estado_lote', 'vencido')->count(),
                'lotes_por_vencer' => $items->where('estado_lote', 'por_vencer')->count(),
                'lotes_activos' => $items->where('activo', true)->count(),
            ],
            'metadata' => $this->metadata($filtros, $limit) + [
                'dias_vencimiento' => $diasVencimiento,
            ],
        ];
    }

    private function estadoLote(?CarbonImmutable $fechaVencimiento, CarbonImmutable $hoy, CarbonImmutable $hasta, bool $activo): string
    {
        if (!$activo) {
            return 'inactivo';
        }

        if (!$fechaVencimiento) {
            return 'sin_vencimiento';
        }

        if ($fechaVencimiento->lt($hoy)) {
            return 'vencido';
        }

        if ($fechaVencimiento->lte($hasta)) {
            return 'por_vencer';
        }

        return 'vigente';
    }
}
