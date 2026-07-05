<?php

namespace App\Domains\Inventario\Services\Reportes;

use App\Domains\Core\Models\User;
use App\Domains\Inventario\Models\TomaFisicaDetalleInventario;
use App\Domains\Inventario\Models\TomaFisicaInventario;
use App\Domains\Inventario\Services\InventarioPermisoService;
use App\Domains\Inventario\Services\Reportes\Concerns\ManejaCalculosReporte;
use Illuminate\Database\Eloquent\Builder;

class ReporteTomasFisicasService
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

        $query = TomaFisicaInventario::query()
            ->where('empresa_id', $empresaId)
            ->with(['bodega:id,empresa_id,codigo,nombre,estado'])
            ->withCount([
                'detalles',
                'detalles as detalles_contados_count' => fn ($query) => $query->whereNotNull('stock_contado'),
                'detalles as detalles_con_diferencia_count' => fn ($query) => $query->where('diferencia', '!=', 0),
                'detalles as detalles_ajustados_count' => fn ($query) => $query->whereNotNull('movimiento_ajuste_id'),
            ])
            ->when(!empty($filtros['estado']), fn (Builder $query) => $query->where('estado', $filtros['estado']))
            ->when(!empty($filtros['bodega_id']), fn (Builder $query) => $query->where('bodega_id', (int) $filtros['bodega_id']))
            ->when(!empty($filtros['desde']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filtros['desde']))
            ->when(!empty($filtros['hasta']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filtros['hasta']));

        $items = (clone $query)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (TomaFisicaInventario $toma) {
                $contados = (int) $toma->detalles_contados_count;
                $conDiferencia = (int) $toma->detalles_con_diferencia_count;
                $exactitud = $contados > 0 ? (($contados - $conDiferencia) / $contados) * 100 : 0;

                return [
                    'id' => (int) $toma->id,
                    'codigo_toma' => $toma->codigo_toma,
                    'estado' => $toma->estado,
                    'tipo' => $toma->tipo,
                    'bodega_id' => $toma->bodega_id ? (int) $toma->bodega_id : null,
                    'bodega_nombre' => $toma->bodega?->nombre,
                    'referencia' => $toma->referencia,
                    'motivo' => $toma->motivo,
                    'detalles' => (int) $toma->detalles_count,
                    'detalles_contados' => $contados,
                    'detalles_con_diferencia' => $conDiferencia,
                    'detalles_ajustados' => (int) $toma->detalles_ajustados_count,
                    'exactitud_porcentaje' => $this->redondear($exactitud, 2),
                    'fecha_inicio' => $toma->fecha_inicio?->toDateTimeString(),
                    'fecha_cierre' => $toma->fecha_cierre?->toDateTimeString(),
                    'fecha_ajuste' => $toma->fecha_ajuste?->toDateTimeString(),
                ];
            })
            ->values();

        $detallesContados = TomaFisicaDetalleInventario::query()
            ->where('empresa_id', $empresaId)
            ->whereNotNull('stock_contado')
            ->count();

        $detallesSinDiferencia = TomaFisicaDetalleInventario::query()
            ->where('empresa_id', $empresaId)
            ->whereNotNull('stock_contado')
            ->where('diferencia', '=', 0)
            ->count();

        return [
            'data' => $items,
            'resumen' => [
                'filas' => $items->count(),
                'tomas_abiertas' => $items->whereIn('estado', [
                    TomaFisicaInventario::ESTADO_BORRADOR,
                    TomaFisicaInventario::ESTADO_EN_CONTEO,
                    TomaFisicaInventario::ESTADO_CERRADA,
                ])->count(),
                'tomas_ajustadas' => $items->where('estado', TomaFisicaInventario::ESTADO_AJUSTADA)->count(),
                'diferencias_detectadas' => $items->sum('detalles_con_diferencia'),
                'exactitud_global_porcentaje' => $detallesContados > 0 ? $this->redondear(($detallesSinDiferencia / $detallesContados) * 100, 2) : 0.0,
            ],
            'metadata' => $this->metadata($filtros, $limit),
        ];
    }
}
