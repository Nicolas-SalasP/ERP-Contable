<?php

namespace App\Domains\Integraciones\Services;

use App\Domains\Inventario\Models\Producto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Lectura/actualizacion de inventario para la API publica de Integraciones. TODA query filtra
 * `empresa_id` explicito recibido como argumento (nunca confia en EmpresaScope: se apaga sin
 * sesion Sanctum, ver AutenticarApiKey). Espejo de InventarioService::listarProductos pero solo
 * con los campos del contrato v1 (ver ProductoIntegracionResource).
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
}
