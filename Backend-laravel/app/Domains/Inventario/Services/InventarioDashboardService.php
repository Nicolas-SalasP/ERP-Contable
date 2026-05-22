<?php

namespace App\Domains\Inventario\Services;

use App\Domains\Core\Models\User;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\MovimientoInventario;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\ReservaInventario;
use App\Domains\Inventario\Models\StockProducto;
use App\Domains\Inventario\Models\TomaFisicaInventario;

class InventarioDashboardService
{
    public function __construct(
        private readonly InventarioPermisoService $permisos
    ) {
    }

    public function obtener(User $usuario): array
    {
        $this->permisos->exigirAlguno($usuario, [
            'inventario.productos.ver',
            'inventario.bodegas.ver',
            'inventario.movimientos.ver',
            'inventario.kardex.ver',
            'inventario.valorizacion.ver',
            'inventario.lotes.ver',
            'inventario.reservas.ver',
            'inventario.disponibilidad.ver',
            'inventario.tomas_fisicas.ver',
        ]);

        $empresaId = (int) $usuario->empresa_id;

        return [
            'resumen' => [
                'productos' => $this->totalProductos($empresaId),
                'bodegas' => $this->totalBodegas($empresaId),
                'reservas_activas' => $this->totalReservasActivas($empresaId),
                'tomas_abiertas' => $this->totalTomasAbiertas($empresaId),
                'stock_valorizado' => $this->stockValorizado($empresaId),
            ],
            'ultimos_movimientos' => $this->ultimosMovimientos($empresaId),
            'tomas_recientes' => $this->tomasRecientes($empresaId),
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

    private function stockValorizado(int $empresaId): float
    {
        return (float) StockProducto::query()
            ->where('empresa_id', $empresaId)
            ->sum('valor_total');
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
            ->limit(6)
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
            ->limit(6)
            ->get();
    }
}