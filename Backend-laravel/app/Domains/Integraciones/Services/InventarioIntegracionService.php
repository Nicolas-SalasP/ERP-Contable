<?php

namespace App\Domains\Integraciones\Services;

use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\ReservaInventario;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Lectura/actualizacion de inventario para la API publica de Integraciones. TODA query filtra
 * `empresa_id` explicito recibido como argumento (nunca confia en EmpresaScope: se apaga sin
 * sesion Sanctum, ver AutenticarApiKey). Espejo de InventarioService::listarProductos pero solo
 * con los campos del contrato v1 (ver ProductoIntegracionResource).
 *
 * Los metodos `*ConDisponibilidad` son exclusivos del contrato v2 (ver ProductoIntegracionResourceV2):
 * agregan el atributo dinamico `stock_disponible` (stock fisico total menos reservas activas no
 * vencidas, mismo criterio que InventarioDisponibilidadService::calcularDisponibilidad pero
 * agregado a nivel de producto en todas las bodegas). Se implementan aparte de `listar`/
 * `obtenerPorSku` para no alterar en nada la query que usa v1.
 */
class InventarioIntegracionService
{
    public function listar(int $empresaId, array $filtros = []): LengthAwarePaginator
    {
        $query = Producto::where('empresa_id', $empresaId)
            ->withSum('stocks as stock_actual_total', 'stock_actual');

        if (! empty($filtros['search'])) {
            $search = trim((string) $filtros['search']);

            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('nombre', 'like', "%{$search}%");
            });
        }

        if (! empty($filtros['sku'])) {
            $query->where('sku', $filtros['sku']);
        }

        if (array_key_exists('activo', $filtros) && $filtros['activo'] !== null && $filtros['activo'] !== '') {
            $query->where('activo', filter_var($filtros['activo'], FILTER_VALIDATE_BOOLEAN));
        }

        if (array_key_exists('visible_web', $filtros) && $filtros['visible_web'] !== null && $filtros['visible_web'] !== '') {
            $query->where('visible_web', filter_var($filtros['visible_web'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filtros['updated_since'])) {
            $query->where('updated_at', '>=', $filtros['updated_since']);
        }

        return $query->orderBy('sku')->paginate((int) ($filtros['limit'] ?? 50));
    }

    public function obtenerPorSku(int $empresaId, string $sku): ?Producto
    {
        return Producto::where('empresa_id', $empresaId)
            ->where('sku', $sku)
            ->withSum('stocks as stock_actual_total', 'stock_actual')
            ->first();
    }

    public function actualizarVisibleWeb(int $empresaId, string $sku, bool $visibleWeb): ?Producto
    {
        $producto = Producto::where('empresa_id', $empresaId)
            ->where('sku', $sku)
            ->first();

        if ($producto === null) {
            return null;
        }

        $producto->update(['visible_web' => $visibleWeb]);

        return $producto->refresh();
    }

    public function listarConDisponibilidad(int $empresaId, array $filtros = []): LengthAwarePaginator
    {
        $paginador = $this->listar($empresaId, $filtros);

        $this->adjuntarStockDisponible($empresaId, $paginador->getCollection());

        return $paginador;
    }

    public function obtenerPorSkuConDisponibilidad(int $empresaId, string $sku): ?Producto
    {
        $producto = $this->obtenerPorSku($empresaId, $sku);

        if ($producto === null) {
            return null;
        }

        $this->adjuntarStockDisponible($empresaId, new Collection([$producto]));

        return $producto;
    }

    /**
     * Setea el atributo dinamico `stock_disponible` en cada producto de la coleccion, con UNA
     * sola query agregada de reservas (evita N+1 en el listado paginado).
     *
     * @param  Collection<int, Producto>  $productos
     */
    private function adjuntarStockDisponible(int $empresaId, Collection $productos): void
    {
        if ($productos->isEmpty()) {
            return;
        }

        $ids = $productos->pluck('id')->all();

        $reservadoPorProducto = DB::table('inventario_reserva_detalles as rd')
            ->join('inventario_reservas as r', 'r.id', '=', 'rd.reserva_id')
            ->where('rd.empresa_id', $empresaId)
            ->whereIn('rd.producto_id', $ids)
            ->whereIn('r.estado', ReservaInventario::estadosQueComprometenDisponibilidad())
            ->where(function ($query) {
                $query->whereNull('r.fecha_expiracion')
                    ->orWhereDate('r.fecha_expiracion', '>=', now()->toDateString());
            })
            ->groupBy('rd.producto_id')
            ->selectRaw('rd.producto_id as producto_id, SUM(rd.cantidad_reservada - rd.cantidad_consumida - rd.cantidad_liberada) as total_reservado')
            ->pluck('total_reservado', 'producto_id');

        $productos->each(function (Producto $producto) use ($reservadoPorProducto) {
            $stockFisico = (float) ($producto->getAttribute('stock_actual_total') ?? 0);
            $reservado = (float) ($reservadoPorProducto[$producto->id] ?? 0);

            $producto->setAttribute('stock_disponible', round($stockFisico - $reservado, 4));
        });
    }
}
