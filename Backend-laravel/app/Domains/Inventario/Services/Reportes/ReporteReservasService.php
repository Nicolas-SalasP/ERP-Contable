<?php

namespace App\Domains\Inventario\Services\Reportes;

use App\Domains\Core\Models\User;
use App\Domains\Inventario\Models\ReservaDetalleInventario;
use App\Domains\Inventario\Models\ReservaInventario;
use App\Domains\Inventario\Services\InventarioPermisoService;
use App\Domains\Inventario\Services\Reportes\Concerns\ManejaCalculosReporte;
use Illuminate\Database\Eloquent\Builder;

class ReporteReservasService
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
                    'reservado_por' => $reserva->reservadoPor->nombre ?? $reserva->reservadoPor?->email,
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
            ->toBase()
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
}
