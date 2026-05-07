<?php

namespace App\Domains\Inventario\Services;

use App\Domains\Core\Models\User;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\LoteInventario;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\ReservaDetalleInventario;
use App\Domains\Inventario\Models\ReservaInventario;
use App\Domains\Inventario\Models\StockLoteInventario;
use App\Domains\Inventario\Models\StockProducto;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class InventarioDisponibilidadService
{
    public function __construct(
        private readonly InventarioPermisoService $permisos
    ) {
    }

    public function consultar(User $usuario, array $filtros = []): LengthAwarePaginator
    {
        $this->permisos->exigir($usuario, 'inventario.disponibilidad.ver');

        $query = StockProducto::query()
            ->where('empresa_id', $usuario->empresa_id)
            ->with([
                'producto:id,empresa_id,sku,nombre,activo,maneja_lotes,requiere_fecha_vencimiento',
                'bodega:id,empresa_id,codigo,nombre,estado',
            ])
            ->when(!empty($filtros['producto_id']), function (Builder $query) use ($filtros) {
                $query->where('producto_id', (int) $filtros['producto_id']);
            })
            ->when(!empty($filtros['bodega_id']), function (Builder $query) use ($filtros) {
                $query->where('bodega_id', (int) $filtros['bodega_id']);
            })
            ->orderBy('producto_id')
            ->orderBy('bodega_id');

        $paginador = $query->paginate($this->normalizarPerPage($filtros['per_page'] ?? 15));

        $paginador->getCollection()->transform(function (StockProducto $stock) use ($usuario, $filtros) {
            $disponibilidad = $this->formatearDisponibilidadStock($stock, (int) $usuario->empresa_id);

            if (!empty($filtros['incluir_lotes']) && $stock->producto?->maneja_lotes) {
                $disponibilidad['lotes'] = $this->disponibilidadLotesProductoBodega(
                    empresaId: (int) $usuario->empresa_id,
                    productoId: (int) $stock->producto_id,
                    bodegaId: (int) $stock->bodega_id
                );
            }

            return $disponibilidad;
        });

        return $paginador;
    }

    public function porProducto(User $usuario, int $productoId, array $filtros = []): array
    {
        $this->permisos->exigir($usuario, 'inventario.disponibilidad.ver');

        $producto = Producto::where('empresa_id', $usuario->empresa_id)->find($productoId);

        if (!$producto) {
            throw new Exception('El producto solicitado no existe o no pertenece a la empresa.');
        }

        $stocks = StockProducto::query()
            ->where('empresa_id', $usuario->empresa_id)
            ->where('producto_id', $producto->id)
            ->with([
                'producto:id,empresa_id,sku,nombre,activo,maneja_lotes,requiere_fecha_vencimiento',
                'bodega:id,empresa_id,codigo,nombre,estado',
            ])
            ->when(!empty($filtros['bodega_id']), function (Builder $query) use ($filtros) {
                $query->where('bodega_id', (int) $filtros['bodega_id']);
            })
            ->orderBy('bodega_id')
            ->get()
            ->map(fn (StockProducto $stock) => $this->formatearDisponibilidadStock($stock, (int) $usuario->empresa_id))
            ->values();

        $totales = [
            'stock_fisico' => $this->redondearCantidad((float) $stocks->sum('stock_fisico')),
            'stock_reservado' => $this->redondearCantidad((float) $stocks->sum('stock_reservado')),
            'stock_disponible' => $this->redondearCantidad((float) $stocks->sum('stock_disponible')),
        ];

        $respuesta = [
            'producto' => $producto->only([
                'id',
                'empresa_id',
                'sku',
                'nombre',
                'activo',
                'maneja_lotes',
                'requiere_fecha_vencimiento',
            ]),
            'totales' => $totales,
            'bodegas' => $stocks,
        ];

        if (!empty($filtros['incluir_lotes']) && $producto->maneja_lotes) {
            $respuesta['lotes'] = $this->disponibilidadLotesProducto(
                empresaId: (int) $usuario->empresa_id,
                productoId: (int) $producto->id,
                bodegaId: !empty($filtros['bodega_id']) ? (int) $filtros['bodega_id'] : null
            );
        }

        return $respuesta;
    }

    public function calcularDisponibilidad(int $empresaId, int $productoId, int $bodegaId): array
    {
        $stock = StockProducto::where('empresa_id', $empresaId)
            ->where('producto_id', $productoId)
            ->where('bodega_id', $bodegaId)
            ->first();

        $stockFisico = $stock ? (float) $stock->stock_actual : 0.0;
        $stockReservado = $this->calcularStockReservadoActivo($empresaId, $productoId, $bodegaId);

        return [
            'empresa_id' => $empresaId,
            'producto_id' => $productoId,
            'bodega_id' => $bodegaId,
            'lote_id' => null,
            'stock_fisico' => $this->redondearCantidad($stockFisico),
            'stock_reservado' => $this->redondearCantidad($stockReservado),
            'stock_disponible' => $this->redondearCantidad($stockFisico - $stockReservado),
        ];
    }

    public function calcularDisponibilidadLote(int $empresaId, int $productoId, int $bodegaId, int $loteId): array
    {
        $stock = StockLoteInventario::where('empresa_id', $empresaId)
            ->where('producto_id', $productoId)
            ->where('bodega_id', $bodegaId)
            ->where('lote_id', $loteId)
            ->first();

        $stockFisico = $stock ? (float) $stock->stock_actual : 0.0;
        $stockReservado = $this->calcularStockReservadoActivoLote($empresaId, $productoId, $bodegaId, $loteId);

        return [
            'empresa_id' => $empresaId,
            'producto_id' => $productoId,
            'bodega_id' => $bodegaId,
            'lote_id' => $loteId,
            'stock_fisico' => $this->redondearCantidad($stockFisico),
            'stock_reservado' => $this->redondearCantidad($stockReservado),
            'stock_disponible' => $this->redondearCantidad($stockFisico - $stockReservado),
        ];
    }

    public function validarDisponibleParaReserva(
        Producto $producto,
        Bodega $bodega,
        ?LoteInventario $lote,
        float $cantidad,
        int $empresaId
    ): void {
        $cantidad = $this->redondearCantidad($cantidad);

        if ($cantidad <= 0) {
            throw ValidationException::withMessages([
                'cantidad' => 'La cantidad a reservar debe ser mayor a cero.',
            ]);
        }

        $disponibilidad = $lote
            ? $this->calcularDisponibilidadLote($empresaId, (int) $producto->id, (int) $bodega->id, (int) $lote->id)
            : $this->calcularDisponibilidad($empresaId, (int) $producto->id, (int) $bodega->id);

        if ((float) $disponibilidad['stock_disponible'] < $cantidad) {
            throw ValidationException::withMessages([
                'cantidad' => 'Stock disponible insuficiente para crear la reserva.',
            ]);
        }
    }

    public function calcularStockReservadoActivo(int $empresaId, int $productoId, int $bodegaId): float
    {
        return $this->sumarReservasActivas($empresaId, $productoId, $bodegaId, null, false);
    }

    public function calcularStockReservadoActivoLote(int $empresaId, int $productoId, int $bodegaId, int $loteId): float
    {
        return $this->sumarReservasActivas($empresaId, $productoId, $bodegaId, $loteId, true);
    }

    public function disponibilidadLotesProducto(int $empresaId, int $productoId, ?int $bodegaId = null): array
    {
        return StockLoteInventario::query()
            ->where('empresa_id', $empresaId)
            ->where('producto_id', $productoId)
            ->with([
                'lote:id,empresa_id,producto_id,codigo_lote,fecha_fabricacion,fecha_vencimiento,activo',
                'bodega:id,empresa_id,codigo,nombre,estado',
            ])
            ->when($bodegaId !== null, function (Builder $query) use ($bodegaId) {
                $query->where('bodega_id', $bodegaId);
            })
            ->orderBy('bodega_id')
            ->orderBy('lote_id')
            ->get()
            ->map(fn (StockLoteInventario $stock) => $this->formatearDisponibilidadStockLote($stock))
            ->values()
            ->all();
    }

    public function disponibilidadLotesProductoBodega(int $empresaId, int $productoId, int $bodegaId): array
    {
        return $this->disponibilidadLotesProducto($empresaId, $productoId, $bodegaId);
    }

    private function formatearDisponibilidadStock(StockProducto $stock, int $empresaId): array
    {
        $stockFisico = (float) $stock->stock_actual;
        $stockReservado = $this->calcularStockReservadoActivo(
            empresaId: $empresaId,
            productoId: (int) $stock->producto_id,
            bodegaId: (int) $stock->bodega_id
        );

        return [
            'empresa_id' => (int) $stock->empresa_id,
            'producto_id' => (int) $stock->producto_id,
            'bodega_id' => (int) $stock->bodega_id,
            'producto' => $stock->producto,
            'bodega' => $stock->bodega,
            'stock_fisico' => $this->redondearCantidad($stockFisico),
            'stock_reservado' => $this->redondearCantidad($stockReservado),
            'stock_disponible' => $this->redondearCantidad($stockFisico - $stockReservado),
        ];
    }

    private function formatearDisponibilidadStockLote(StockLoteInventario $stock): array
    {
        $stockFisico = (float) $stock->stock_actual;
        $stockReservado = $this->calcularStockReservadoActivoLote(
            empresaId: (int) $stock->empresa_id,
            productoId: (int) $stock->producto_id,
            bodegaId: (int) $stock->bodega_id,
            loteId: (int) $stock->lote_id
        );

        return [
            'empresa_id' => (int) $stock->empresa_id,
            'producto_id' => (int) $stock->producto_id,
            'bodega_id' => (int) $stock->bodega_id,
            'lote_id' => (int) $stock->lote_id,
            'bodega' => $stock->bodega,
            'lote' => $stock->lote,
            'stock_fisico' => $this->redondearCantidad($stockFisico),
            'stock_reservado' => $this->redondearCantidad($stockReservado),
            'stock_disponible' => $this->redondearCantidad($stockFisico - $stockReservado),
        ];
    }

    private function sumarReservasActivas(
        int $empresaId,
        int $productoId,
        int $bodegaId,
        ?int $loteId,
        bool $filtrarPorLote
    ): float {
        $query = ReservaDetalleInventario::query()
            ->where('empresa_id', $empresaId)
            ->where('producto_id', $productoId)
            ->where('bodega_id', $bodegaId)
            ->whereHas('reserva', function (Builder $query) use ($empresaId) {
                $query
                    ->where('empresa_id', $empresaId)
                    ->whereIn('estado', ReservaInventario::estadosQueComprometenDisponibilidad())
                    ->where(function (Builder $subQuery) {
                        $subQuery
                            ->whereNull('fecha_expiracion')
                            ->orWhereDate('fecha_expiracion', '>=', now()->toDateString());
                    });
            });

        if ($filtrarPorLote) {
            $query->where('lote_id', $loteId);
        }

        $total = $query
            ->selectRaw('COALESCE(SUM(cantidad_reservada - cantidad_consumida - cantidad_liberada), 0) as total')
            ->value('total');

        return $this->redondearCantidad((float) $total);
    }

    private function normalizarPerPage(mixed $perPage): int
    {
        $perPage = (int) $perPage;

        if ($perPage <= 0) {
            return 15;
        }

        return min($perPage, 100);
    }

    private function redondearCantidad(float $value): float
    {
        return round($value, 4);
    }
}