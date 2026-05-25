<?php

namespace App\Domains\Inventario\Services;

use App\Domains\Core\Models\User;
use App\Domains\Inventario\Models\AjusteCriticoInventario;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\InventarioAlertaEstado;
use App\Domains\Inventario\Models\LoteInventario;
use App\Domains\Inventario\Models\MovimientoInventario;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\ReservaDetalleInventario;
use App\Domains\Inventario\Models\ReservaInventario;
use App\Domains\Inventario\Models\StockLoteInventario;
use App\Domains\Inventario\Models\StockProducto;
use App\Domains\Inventario\Models\TomaFisicaDetalleInventario;
use App\Domains\Inventario\Models\TomaFisicaInventario;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class InventarioDashboardService
{
    private const DIAS_ALERTA_VENCIMIENTO = 30;

    public function __construct(
        private readonly InventarioPermisoService $permisos,
        private readonly InventarioReposicionService $reposicionService,
        private readonly InventarioAlertaService $alertaService
    ) {
    }

    public function obtener(User $usuario): array
    {
        $this->permisos->exigirAlguno($usuario, [
            'inventario.dashboard.ver',
            'inventario.reportes.ver',
            'inventario.productos.ver',
            'inventario.bodegas.ver',
            'inventario.movimientos.ver',
            'inventario.kardex.ver',
            'inventario.valorizacion.ver',
            'inventario.lotes.ver',
            'inventario.reservas.ver',
            'inventario.disponibilidad.ver',
            'inventario.ubicaciones.ver',
            'inventario.stock_ubicaciones.ver',
            'inventario.picking.ver',
            'inventario.packing.ver',
            'inventario.despachos.ver',
            'inventario.devoluciones.ver',
            'inventario.auditoria.ver',
            'inventario.eventos_integracion.ver',
            'inventario.tomas_fisicas.ver',
            'inventario.alertas.ver',
            'inventario.reglas_reposicion.ver',
        ]);

        $empresaId = (int) $usuario->empresa_id;
        $stockTotal = $this->stockTotal($empresaId);
        $valorTotalInventario = $this->stockValorizado($empresaId);
        $totalProductos = $this->totalProductos($empresaId);
        $productosBajoMinimo = $this->productosBajoMinimo($empresaId);
        $productosSinStock = $this->productosSinStock($empresaId);

        $alertas = $this->alertasEjecutivas($empresaId);
        $sugerenciasReposicion = $this->sugerenciasReposicion($empresaId);

        return [
            'resumen' => [
                'productos' => $totalProductos,
                'productos_activos' => $totalProductos,
                'bodegas' => $this->totalBodegas($empresaId),
                'stock_total' => $stockTotal,
                'stock_valorizado' => $valorTotalInventario,
                'valor_total_inventario' => $valorTotalInventario,
                'productos_bajo_minimo' => $productosBajoMinimo,
                'productos_sin_stock' => $productosSinStock,
                'productos_sin_movimiento' => $this->productosSinMovimiento($empresaId),
                'lotes_vencidos' => $this->lotesVencidos($empresaId),
                'lotes_por_vencer' => $this->lotesPorVencer($empresaId),
                'reservas_activas' => $this->totalReservasActivas($empresaId),
                'tomas_abiertas' => $this->totalTomasAbiertas($empresaId),
                'tomas_pendientes' => $this->totalTomasPendientes($empresaId),
                'alertas_criticas' => $alertas['criticas'],
                'alertas_total' => $alertas['total'],
                'sugerencias_reposicion' => $sugerenciasReposicion['total'],
                'exactitud_toma_fisica' => $this->exactitudTomaFisica($empresaId),
                'rotacion_simple' => $this->rotacionSimple($empresaId, $valorTotalInventario),
                'porcentaje_productos_bajo_minimo' => $this->porcentaje($productosBajoMinimo, $totalProductos),
                'porcentaje_productos_sin_stock' => $this->porcentaje($productosSinStock, $totalProductos),
            ],
            'kpis' => [
                'valor_total_inventario' => $valorTotalInventario,
                'cantidad_total_stock' => $stockTotal,
                'porcentaje_productos_bajo_minimo' => $this->porcentaje($productosBajoMinimo, $totalProductos),
                'porcentaje_productos_sin_stock' => $this->porcentaje($productosSinStock, $totalProductos),
                'lotes_vencidos' => $this->lotesVencidos($empresaId),
                'lotes_por_vencer' => $this->lotesPorVencer($empresaId),
                'reservas_activas' => $this->totalReservasActivas($empresaId),
                'alertas_criticas' => $alertas['criticas'],
                'exactitud_toma_fisica' => $this->exactitudTomaFisica($empresaId),
                'rotacion_simple' => $this->rotacionSimple($empresaId, $valorTotalInventario),
            ],
            'stock_por_bodega' => $this->stockPorBodega($empresaId),
            'stock_por_lote' => $this->stockPorLote($empresaId),
            'alertas_criticas' => $alertas['items'],
            'sugerencias_reposicion' => $sugerenciasReposicion['items'],
            'ultimos_movimientos' => $this->ultimosMovimientos($empresaId),
            'ajustes_criticos_recientes' => $this->ajustesCriticosRecientes($empresaId),
            'tomas_recientes' => $this->tomasRecientes($empresaId),
            'metadata' => [
                'generado_en' => now()->toISOString(),
                'dias_alerta_vencimiento' => self::DIAS_ALERTA_VENCIMIENTO,
                'nota_rotacion_simple' => 'Rotación simple calculada como costo total de salidas de los últimos 30 días dividido por valor total actual de inventario. Si el valor actual es 0, se retorna 0.',
            ],
        ];
    }

    private function totalProductos(int $empresaId): int
    {
        return Producto::query()
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->count();
    }

    private function totalBodegas(int $empresaId): int
    {
        return Bodega::query()
            ->where('empresa_id', $empresaId)
            ->where('estado', 'ACTIVA')
            ->count();
    }

    private function stockTotal(int $empresaId): float
    {
        return $this->redondear((float) StockProducto::query()
            ->where('empresa_id', $empresaId)
            ->sum('stock_actual'));
    }

    private function stockValorizado(int $empresaId): float
    {
        return $this->redondear((float) StockProducto::query()
            ->where('empresa_id', $empresaId)
            ->sum('valor_total'));
    }

    private function productosBajoMinimo(int $empresaId): int
    {
        return Producto::query()
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->where('stock_minimo', '>', 0)
            ->whereIn('id', function ($query) use ($empresaId) {
                $query->select('producto_id')
                    ->from('inventario_stock')
                    ->where('empresa_id', $empresaId)
                    ->groupBy('producto_id')
                    ->havingRaw('SUM(stock_actual) <= (SELECT stock_minimo FROM inventario_productos WHERE inventario_productos.id = inventario_stock.producto_id)');
            })
            ->count();
    }

    private function productosSinStock(int $empresaId): int
    {
        return Producto::query()
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->where(function (Builder $query) use ($empresaId) {
                $query
                    ->whereDoesntHave('stocks')
                    ->orWhereIn('id', function ($subQuery) use ($empresaId) {
                        $subQuery->select('producto_id')
                            ->from('inventario_stock')
                            ->where('empresa_id', $empresaId)
                            ->groupBy('producto_id')
                            ->havingRaw('SUM(stock_actual) <= 0');
                    });
            })
            ->count();
    }

    private function productosSinMovimiento(int $empresaId): int
    {
        return Producto::query()
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->whereDoesntHave('stocks', function (Builder $query) {
                $query->where('stock_actual', '>', 0);
            })
            ->whereDoesntHave('movimientosLotes')
            ->whereNotIn('id', function ($query) use ($empresaId) {
                $query->select('producto_id')
                    ->from('inventario_movimientos')
                    ->where('empresa_id', $empresaId);
            })
            ->count();
    }

    private function totalReservasActivas(int $empresaId): int
    {
        return ReservaInventario::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('estado', ReservaInventario::estadosQueComprometenDisponibilidad())
            ->count();
    }

    private function totalTomasAbiertas(int $empresaId): int
    {
        return TomaFisicaInventario::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('estado', [
                TomaFisicaInventario::ESTADO_BORRADOR,
                TomaFisicaInventario::ESTADO_EN_CONTEO,
                TomaFisicaInventario::ESTADO_CERRADA,
            ])
            ->count();
    }

    private function totalTomasPendientes(int $empresaId): int
    {
        return TomaFisicaInventario::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('estado', [
                TomaFisicaInventario::ESTADO_BORRADOR,
                TomaFisicaInventario::ESTADO_EN_CONTEO,
                TomaFisicaInventario::ESTADO_CERRADA,
            ])
            ->count();
    }

    private function lotesVencidos(int $empresaId): int
    {
        return StockLoteInventario::query()
            ->where('empresa_id', $empresaId)
            ->where('stock_actual', '>', 0)
            ->whereHas('lote', function (Builder $query) {
                $query
                    ->where('activo', true)
                    ->whereNotNull('fecha_vencimiento')
                    ->whereDate('fecha_vencimiento', '<', now()->toDateString());
            })
            ->count();
    }

    private function lotesPorVencer(int $empresaId): int
    {
        $hasta = CarbonImmutable::today()->addDays(self::DIAS_ALERTA_VENCIMIENTO)->toDateString();

        return StockLoteInventario::query()
            ->where('empresa_id', $empresaId)
            ->where('stock_actual', '>', 0)
            ->whereHas('lote', function (Builder $query) use ($hasta) {
                $query
                    ->where('activo', true)
                    ->whereNotNull('fecha_vencimiento')
                    ->whereDate('fecha_vencimiento', '>=', now()->toDateString())
                    ->whereDate('fecha_vencimiento', '<=', $hasta);
            })
            ->count();
    }

    private function exactitudTomaFisica(int $empresaId): float
    {
        $contados = TomaFisicaDetalleInventario::query()
            ->where('empresa_id', $empresaId)
            ->whereNotNull('stock_contado')
            ->count();

        if ($contados === 0) {
            return 0.0;
        }

        $sinDiferencia = TomaFisicaDetalleInventario::query()
            ->where('empresa_id', $empresaId)
            ->whereNotNull('stock_contado')
            ->where('diferencia', '=', 0)
            ->count();

        return $this->redondear(($sinDiferencia / $contados) * 100, 2);
    }

    private function rotacionSimple(int $empresaId, float $valorTotalInventario): float
    {
        if ($valorTotalInventario <= 0) {
            return 0.0;
        }

        $desde = CarbonImmutable::today()->subDays(30)->toDateString();

        $costoSalidas = (float) MovimientoInventario::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('tipo', [
                MovimientoInventario::TIPO_SALIDA,
                MovimientoInventario::TIPO_AJUSTE_NEGATIVO,
            ])
            ->whereDate('fecha_movimiento', '>=', $desde)
            ->sum('costo_total');

        return $this->redondear($costoSalidas / $valorTotalInventario, 4);
    }

    private function stockPorBodega(int $empresaId)
    {
        return StockProducto::query()
            ->where('inventario_stock.empresa_id', $empresaId)
            ->join('inventario_bodegas', 'inventario_bodegas.id', '=', 'inventario_stock.bodega_id')
            ->select([
                'inventario_stock.bodega_id',
                'inventario_bodegas.codigo as bodega_codigo',
                'inventario_bodegas.nombre as bodega_nombre',
            ])
            ->selectRaw('SUM(inventario_stock.stock_actual) as stock_total')
            ->selectRaw('SUM(inventario_stock.valor_total) as valor_total')
            ->groupBy('inventario_stock.bodega_id', 'inventario_bodegas.codigo', 'inventario_bodegas.nombre')
            ->orderByDesc('valor_total')
            ->limit(10)
            ->get()
            ->map(fn ($item) => [
                'bodega_id' => (int) $item->bodega_id,
                'bodega_codigo' => $item->bodega_codigo,
                'bodega_nombre' => $item->bodega_nombre,
                'stock_total' => $this->redondear((float) $item->stock_total),
                'valor_total' => $this->redondear((float) $item->valor_total),
            ])
            ->values();
    }

    private function stockPorLote(int $empresaId)
    {
        return StockLoteInventario::query()
            ->where('empresa_id', $empresaId)
            ->where('stock_actual', '>', 0)
            ->with([
                'producto:id,empresa_id,sku,nombre,activo',
                'bodega:id,empresa_id,codigo,nombre,estado',
                'lote:id,empresa_id,producto_id,codigo_lote,fecha_vencimiento,activo',
            ])
            ->orderByDesc('stock_actual')
            ->limit(10)
            ->get()
            ->map(fn (StockLoteInventario $stock) => [
                'producto_id' => (int) $stock->producto_id,
                'producto_sku' => $stock->producto?->sku,
                'producto_nombre' => $stock->producto?->nombre,
                'bodega_id' => (int) $stock->bodega_id,
                'bodega_nombre' => $stock->bodega?->nombre,
                'lote_id' => (int) $stock->lote_id,
                'lote_codigo' => $stock->lote?->codigo_lote,
                'fecha_vencimiento' => $stock->lote?->fecha_vencimiento?->toDateString(),
                'stock_actual' => $this->redondear((float) $stock->stock_actual),
            ])
            ->values();
    }

    private function alertasEjecutivas(int $empresaId): array
    {
        $query = InventarioAlertaEstado::query()
            ->where('empresa_id', $empresaId);

        $total = (clone $query)->count();
        $criticas = (clone $query)->where('severidad', 'critica')->count();

        $items = (clone $query)
            ->with([
                'producto:id,empresa_id,sku,nombre,activo',
                'bodega:id,empresa_id,codigo,nombre,estado',
                'lote:id,empresa_id,producto_id,codigo_lote,fecha_vencimiento,activo,estado_operativo',
            ])
            ->orderByRaw("CASE severidad WHEN 'critica' THEN 1 WHEN 'alta' THEN 2 WHEN 'media' THEN 3 WHEN 'baja' THEN 4 ELSE 5 END")
            ->orderBy('fecha_referencia')
            ->limit(8)
            ->get()
            ->map(fn (InventarioAlertaEstado $alerta) => [
                'id' => (int) $alerta->id,
                'tipo' => $alerta->tipo,
                'severidad' => $alerta->severidad,
                'titulo' => $alerta->titulo,
                'descripcion' => $alerta->descripcion,
                'producto_id' => $alerta->producto_id,
                'producto_nombre' => $alerta->producto?->nombre,
                'producto_sku' => $alerta->producto?->sku,
                'bodega_id' => $alerta->bodega_id,
                'bodega_nombre' => $alerta->bodega?->nombre,
                'lote_id' => $alerta->lote_id,
                'lote_codigo' => $alerta->lote?->codigo_lote,
                'cantidad_actual' => $alerta->cantidad_actual !== null ? (float) $alerta->cantidad_actual : null,
                'stock_minimo' => $alerta->stock_minimo !== null ? (float) $alerta->stock_minimo : null,
                'stock_objetivo' => $alerta->stock_objetivo !== null ? (float) $alerta->stock_objetivo : null,
                'cantidad_sugerida' => $alerta->cantidad_sugerida !== null ? (float) $alerta->cantidad_sugerida : null,
                'fecha_referencia' => $alerta->fecha_referencia?->toDateString(),
                'referencia' => $alerta->referencia,
                'metadata' => $alerta->metadata ?? [],
                'calculado_en' => $alerta->calculado_en?->toISOString(),
            ])
            ->values();

        return [
            'total' => $total,
            'criticas' => $criticas,
            'items' => $items,
        ];
    }

    private function sugerenciasReposicion(int $empresaId): array
    {
        $query = InventarioAlertaEstado::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo', 'REPOSICION_SUGERIDA');

        $total = (clone $query)->count();

        $items = (clone $query)
            ->with([
                'producto:id,empresa_id,sku,nombre,activo',
                'bodega:id,empresa_id,codigo,nombre,estado',
            ])
            ->orderByDesc('cantidad_sugerida')
            ->limit(8)
            ->get()
            ->map(fn (InventarioAlertaEstado $alerta) => [
                'producto_id' => $alerta->producto_id,
                'producto_nombre' => $alerta->producto?->nombre,
                'producto_sku' => $alerta->producto?->sku,
                'bodega_id' => $alerta->bodega_id,
                'bodega_nombre' => $alerta->bodega?->nombre,
                'stock_actual' => $alerta->cantidad_actual !== null ? (float) $alerta->cantidad_actual : null,
                'stock_minimo' => $alerta->stock_minimo !== null ? (float) $alerta->stock_minimo : null,
                'stock_objetivo' => $alerta->stock_objetivo !== null ? (float) $alerta->stock_objetivo : null,
                'cantidad_sugerida' => $alerta->cantidad_sugerida !== null ? (float) $alerta->cantidad_sugerida : null,
                'referencia' => $alerta->referencia,
                'metadata' => $alerta->metadata ?? [],
            ])
            ->values();

        return [
            'total' => $total,
            'items' => $items,
        ];
    }

    private function ultimosMovimientos(int $empresaId)
    {
        return MovimientoInventario::query()
            ->where('empresa_id', $empresaId)
            ->with([
                'producto:id,empresa_id,sku,nombre,activo,maneja_lotes,requiere_fecha_vencimiento',
                'bodegaOrigen:id,empresa_id,codigo,nombre,estado',
                'bodegaDestino:id,empresa_id,codigo,nombre,estado',
            ])
            ->orderByDesc('fecha_movimiento')
            ->orderByDesc('id')
            ->limit(8)
            ->get();
    }

    private function ajustesCriticosRecientes(int $empresaId)
    {
        return AjusteCriticoInventario::query()
            ->where('empresa_id', $empresaId)
            ->with([
                'producto:id,empresa_id,sku,nombre,activo',
                'bodega:id,empresa_id,codigo,nombre,estado',
                'lote:id,empresa_id,producto_id,codigo_lote,fecha_vencimiento,activo',
                'tipo:id,codigo,nombre,tipo_movimiento,activo',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get();
    }

    private function tomasRecientes(int $empresaId)
    {
        return TomaFisicaInventario::query()
            ->where('empresa_id', $empresaId)
            ->with([
                'bodega:id,empresa_id,codigo,nombre,estado',
            ])
            ->withCount([
                'detalles',
                'detalles as detalles_contados_count' => function ($query) {
                    $query->whereNotNull('stock_contado');
                },
                'detalles as detalles_con_diferencia_count' => function ($query) {
                    $query->where('diferencia', '!=', 0);
                },
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get();
    }

    private function porcentaje(int $valor, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return $this->redondear(($valor / $total) * 100, 2);
    }

    private function redondear(float $valor, int $decimales = 4): float
    {
        return round($valor, $decimales);
    }
}
