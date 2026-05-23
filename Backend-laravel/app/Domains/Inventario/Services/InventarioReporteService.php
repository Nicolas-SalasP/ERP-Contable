<?php

namespace App\Domains\Inventario\Services;

use App\Domains\Core\Models\User;
use App\Domains\Inventario\Models\AjusteCriticoInventario;
use App\Domains\Inventario\Models\Bodega;
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
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventarioReporteService
{
    private const DEFAULT_LIMIT = 200;
    private const MAX_LIMIT = 1000;
    private const DEFAULT_DIAS_VENCIMIENTO = 30;

    public function __construct(
        private readonly InventarioPermisoService $permisos,
        private readonly InventarioAlertaService $alertaService,
        private readonly InventarioReposicionService $reposicionService
    ) {
    }

    public function stock(User $usuario, array $filtros = []): array
    {
        $this->exigirPermisoReportes($usuario, ['inventario.disponibilidad.ver', 'inventario.valorizacion.ver']);

        $empresaId = (int) $usuario->empresa_id;
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
                $stockMinimo = (float) ($stock->producto?->stock_minimo ?? 0);
                $key = $this->claveProductoBodega((int) $stock->producto_id, (int) $stock->bodega_id);
                $cantidadComprometida = (float) ($comprometido[$key] ?? 0);

                return [
                    'producto_id' => (int) $stock->producto_id,
                    'producto_sku' => $stock->producto?->sku,
                    'producto_nombre' => $stock->producto?->nombre,
                    'producto_activo' => (bool) ($stock->producto?->activo ?? false),
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
            ->when(!empty($filtros['estado_stock']), function (Collection $items) use ($filtros) {
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

    public function movimientos(User $usuario, array $filtros = []): array
    {
        $this->exigirPermisoReportes($usuario, ['inventario.movimientos.ver', 'inventario.kardex.ver']);

        $empresaId = (int) $usuario->empresa_id;
        $limit = $this->normalizarLimit($filtros['limit'] ?? self::DEFAULT_LIMIT);

        $query = MovimientoInventario::query()
            ->where('empresa_id', $empresaId)
            ->with([
                'producto:id,empresa_id,sku,nombre,activo',
                'bodegaOrigen:id,empresa_id,codigo,nombre,estado',
                'bodegaDestino:id,empresa_id,codigo,nombre,estado',
            ]);

        $this->aplicarFiltrosMovimientos($query, $filtros);

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

    public function valorizacion(User $usuario, array $filtros = []): array
    {
        $this->exigirPermisoReportes($usuario, ['inventario.valorizacion.ver']);

        $empresaId = (int) $usuario->empresa_id;
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

    public function lotes(User $usuario, array $filtros = []): array
    {
        $this->exigirPermisoReportes($usuario, ['inventario.lotes.ver']);

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
                $estado = $this->estadoLote($fechaVencimiento, $hoy, $hasta, (bool) ($stock->lote?->activo ?? false));

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
                    'activo' => (bool) ($stock->lote?->activo ?? false),
                ];
            })
            ->when(!empty($filtros['estado_lote']), function (Collection $items) use ($filtros) {
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

    public function reservas(User $usuario, array $filtros = []): array
    {
        $this->exigirPermisoReportes($usuario, ['inventario.reservas.ver', 'inventario.disponibilidad.ver']);

        $empresaId = (int) $usuario->empresa_id;
        $limit = $this->normalizarLimit($filtros['limit'] ?? self::DEFAULT_LIMIT);

        $query = ReservaInventario::query()
            ->where('empresa_id', $empresaId)
            ->with([
                'detalles.producto:id,empresa_id,sku,nombre,activo',
                'detalles.bodega:id,empresa_id,codigo,nombre,estado',
                'detalles.lote:id,empresa_id,producto_id,codigo_lote,fecha_vencimiento,activo',
                'reservadoPor:id,name,email',
            ])
            ->withCount('detalles')
            ->when(!empty($filtros['estado']), fn (Builder $query) => $query->where('estado', $filtros['estado']))
            ->when(!empty($filtros['producto_id']), function (Builder $query) use ($filtros) {
                $query->whereHas('detalles', fn (Builder $detalle) => $detalle->where('producto_id', (int) $filtros['producto_id']));
            })
            ->when(!empty($filtros['bodega_id']), function (Builder $query) use ($filtros) {
                $query->whereHas('detalles', fn (Builder $detalle) => $detalle->where('bodega_id', (int) $filtros['bodega_id']));
            })
            ->when(!empty($filtros['desde']), fn (Builder $query) => $query->whereDate('fecha_reserva', '>=', $filtros['desde']))
            ->when(!empty($filtros['hasta']), fn (Builder $query) => $query->whereDate('fecha_reserva', '<=', $filtros['hasta']));

        $items = (clone $query)
            ->orderByDesc('fecha_reserva')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (ReservaInventario $reserva) {
                $cantidadReservada = (float) $reserva->detalles->sum('cantidad_reservada');
                $cantidadConsumida = (float) $reserva->detalles->sum('cantidad_consumida');
                $cantidadLiberada = (float) $reserva->detalles->sum('cantidad_liberada');
                $cantidadPendiente = max($cantidadReservada - $cantidadConsumida - $cantidadLiberada, 0);

                return [
                    'id' => (int) $reserva->id,
                    'codigo_reserva' => $reserva->codigo_reserva,
                    'estado' => $reserva->estado,
                    'referencia' => $reserva->referencia,
                    'motivo' => $reserva->motivo,
                    'fecha_reserva' => $reserva->fecha_reserva?->toDateTimeString(),
                    'fecha_expiracion' => $reserva->fecha_expiracion?->toDateTimeString(),
                    'detalles_count' => (int) $reserva->detalles_count,
                    'cantidad_reservada' => $this->redondear($cantidadReservada),
                    'cantidad_consumida' => $this->redondear($cantidadConsumida),
                    'cantidad_liberada' => $this->redondear($cantidadLiberada),
                    'cantidad_pendiente' => $this->redondear($cantidadPendiente),
                    'reservado_por' => $reserva->reservadoPor?->name ?? $reserva->reservadoPor?->email,
                ];
            })
            ->values();

        $porProducto = ReservaDetalleInventario::query()
            ->where('inventario_reserva_detalles.empresa_id', $empresaId)
            ->join('inventario_reservas', 'inventario_reservas.id', '=', 'inventario_reserva_detalles.reserva_id')
            ->join('inventario_productos', 'inventario_productos.id', '=', 'inventario_reserva_detalles.producto_id')
            ->whereIn('inventario_reservas.estado', ReservaInventario::estadosQueComprometenDisponibilidad())
            ->select([
                'inventario_reserva_detalles.producto_id',
                'inventario_productos.sku as producto_sku',
                'inventario_productos.nombre as producto_nombre',
            ])
            ->selectRaw('SUM(cantidad_reservada - cantidad_consumida - cantidad_liberada) as cantidad_comprometida')
            ->groupBy('inventario_reserva_detalles.producto_id', 'inventario_productos.sku', 'inventario_productos.nombre')
            ->orderByDesc('cantidad_comprometida')
            ->limit(10)
            ->get()
            ->map(fn ($item) => [
                'producto_id' => (int) $item->producto_id,
                'producto_sku' => $item->producto_sku,
                'producto_nombre' => $item->producto_nombre,
                'cantidad_comprometida' => $this->redondear((float) $item->cantidad_comprometida),
            ])
            ->values();

        return [
            'data' => $items,
            'resumen' => [
                'filas' => $items->count(),
                'reservas_activas' => (clone $query)->whereIn('estado', ReservaInventario::estadosQueComprometenDisponibilidad())->count(),
                'reservas_consumidas' => (clone $query)->where('estado', ReservaInventario::ESTADO_CONSUMIDA)->count(),
                'reservas_liberadas' => (clone $query)->where('estado', ReservaInventario::ESTADO_PARCIALMENTE_LIBERADA)->count(),
                'cantidad_pendiente' => $this->redondear((float) $items->sum('cantidad_pendiente')),
                'productos_mayor_reserva' => $porProducto,
            ],
            'metadata' => $this->metadata($filtros, $limit),
        ];
    }

    public function tomasFisicas(User $usuario, array $filtros = []): array
    {
        $this->exigirPermisoReportes($usuario, ['inventario.tomas_fisicas.ver']);

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

    public function ajustes(User $usuario, array $filtros = []): array
    {
        $this->exigirPermisoReportes($usuario, ['inventario.ajustes_criticos.ver', 'inventario.movimientos.ver']);

        $empresaId = (int) $usuario->empresa_id;
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
                'fecha' => $ajuste->created_at?->toDateTimeString(),
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
                'registrado_por' => $ajuste->registradoPor?->name ?? $ajuste->registradoPor?->email,
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

    public function reposicionAlertas(User $usuario, array $filtros = []): array
    {
        $this->exigirPermisoReportes($usuario, ['inventario.alertas.ver', 'inventario.reglas_reposicion.ver']);

        $alertas = $this->alertaService->listar($usuario, $filtros + ['limit' => $filtros['limit'] ?? 200]);
        $sugerencias = $this->reposicionService->sugerencias($usuario, $filtros);

        return [
            'data' => [
                'alertas' => $alertas['data'] ?? [],
                'sugerencias_reposicion' => $sugerencias,
            ],
            'resumen' => [
                'alertas' => $alertas['resumen'] ?? [],
                'total_sugerencias_reposicion' => count($sugerencias),
                'cantidad_sugerida_total' => $this->redondear((float) collect($sugerencias)->sum('cantidad_sugerida')),
            ],
            'metadata' => [
                'generado_en' => now()->toISOString(),
                'filtros' => $filtros,
            ],
        ];
    }

    public function exportarCsv(User $usuario, string $tipo, array $filtros = []): array
    {
        $resultado = match ($tipo) {
            'stock' => $this->stock($usuario, $filtros + ['limit' => self::MAX_LIMIT]),
            'movimientos' => $this->movimientos($usuario, $filtros + ['limit' => self::MAX_LIMIT]),
            'valorizacion' => $this->valorizacion($usuario, $filtros + ['limit' => self::MAX_LIMIT]),
            'lotes' => $this->lotes($usuario, $filtros + ['limit' => self::MAX_LIMIT]),
            'reservas' => $this->reservas($usuario, $filtros + ['limit' => self::MAX_LIMIT]),
            'tomas-fisicas' => $this->tomasFisicas($usuario, $filtros + ['limit' => self::MAX_LIMIT]),
            'ajustes' => $this->ajustes($usuario, $filtros + ['limit' => self::MAX_LIMIT]),
            default => throw new Exception('El tipo de reporte no es válido para exportación CSV.'),
        };

        $filas = $this->filasExportables($tipo, $resultado['data'] ?? []);
        $encabezados = $filas->isNotEmpty() ? array_keys($filas->first()) : ['sin_datos'];

        return [
            'filename' => 'inventario_reporte_' . str_replace('-', '_', $tipo) . '_' . now()->format('Ymd_His') . '.csv',
            'headers' => $encabezados,
            'rows' => $filas->values()->all(),
        ];
    }

    private function exigirPermisoReportes(User $usuario, array $permisosAlternativos = []): void
    {
        $this->permisos->exigirAlguno($usuario, array_values(array_unique(array_merge([
            'inventario.reportes.ver',
            'inventario.dashboard.ver',
        ], $permisosAlternativos))));
    }

    private function aplicarFiltrosMovimientos(Builder $query, array $filtros): void
    {
        $query
            ->when(!empty($filtros['producto_id']), fn (Builder $query) => $query->where('producto_id', (int) $filtros['producto_id']))
            ->when(!empty($filtros['bodega_id']), fn (Builder $query) => $query->where(function (Builder $subQuery) use ($filtros) {
                $bodegaId = (int) $filtros['bodega_id'];
                $subQuery->where('bodega_origen_id', $bodegaId)->orWhere('bodega_destino_id', $bodegaId);
            }))
            ->when(!empty($filtros['tipo']), fn (Builder $query) => $query->where('tipo', $filtros['tipo']))
            ->when(!empty($filtros['desde']), fn (Builder $query) => $query->whereDate('fecha_movimiento', '>=', $filtros['desde']))
            ->when(!empty($filtros['hasta']), fn (Builder $query) => $query->whereDate('fecha_movimiento', '<=', $filtros['hasta']));
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

    private function filasExportables(string $tipo, mixed $data): Collection
    {
        if ($tipo === 'valorizacion') {
            return collect($data['por_producto'] ?? [])->map(fn ($row) => $this->aplanarFila($row));
        }

        return collect($data)->map(fn ($row) => $this->aplanarFila($row));
    }

    private function aplanarFila(array $fila): array
    {
        $resultado = [];

        foreach ($fila as $key => $value) {
            if (is_array($value) || $value instanceof Collection) {
                continue;
            }

            if (is_bool($value)) {
                $resultado[$key] = $value ? '1' : '0';
                continue;
            }

            $resultado[$key] = $value;
        }

        return $resultado;
    }

    private function claveProductoBodega(int $productoId, int $bodegaId): string
    {
        return $productoId . ':' . $bodegaId;
    }

    private function normalizarLimit(mixed $limit): int
    {
        $limit = (int) $limit;

        if ($limit <= 0) {
            return self::DEFAULT_LIMIT;
        }

        return min($limit, self::MAX_LIMIT);
    }

    private function metadata(array $filtros, int $limit): array
    {
        return [
            'generado_en' => now()->toISOString(),
            'limit' => $limit,
            'filtros' => $filtros,
        ];
    }

    private function redondear(float $valor, int $decimales = 4): float
    {
        return round($valor, $decimales);
    }
}
