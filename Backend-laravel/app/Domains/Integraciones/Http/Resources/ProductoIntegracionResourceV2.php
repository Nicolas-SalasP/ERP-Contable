<?php

namespace App\Domains\Integraciones\Http\Resources;

use App\Domains\Inventario\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Contrato v2: agrega `precio_venta_bruto` (con IVA ya aplicado por el ERP, usando la misma
 * `config('fiscal.tasa_iva')` que Comercial/Sii — el consumidor NUNCA debe recalcular esto) y
 * `stock_disponible` (fisico menos reservas activas, ver InventarioIntegracionService). Mantiene
 * todos los campos de v1 (incluido `stock_actual_total`, para compatibilidad/diagnostico).
 *
 * `categoria`, `marca` y `peso_gramos` quedan fuera a proposito: `Producto` no tiene esos
 * conceptos hoy (sin relacion de categoria ni columnas de marca/peso) -> se documentan como
 * pendientes de una fase futura en vez de inventar estructura nueva.
 *
 * @property Producto $resource
 */
class ProductoIntegracionResourceV2 extends JsonResource
{
    public function toArray(Request $request): array
    {
        $producto = $this->resource;

        $precioNeto = (float) $producto->precio_venta_neto;
        $precioBruto = $producto->afecto_iva
            ? round($precioNeto + round($precioNeto * (float) config('fiscal.tasa_iva'), 2), 2)
            : $precioNeto;

        return [
            'sku' => $producto->sku,
            'nombre' => $producto->nombre,
            'descripcion' => $producto->descripcion,
            'precio_venta_neto' => $precioNeto,
            'precio_venta_bruto' => $precioBruto,
            'afecto_iva' => $producto->afecto_iva,
            'codigo_barra' => $producto->codigo_barra,
            'stock_actual_total' => (float) ($producto->getAttribute('stock_actual_total') ?? 0),
            'stock_disponible' => (float) ($producto->getAttribute('stock_disponible') ?? 0),
            'activo' => $producto->activo,
            'visible_web' => $producto->visible_web,
            'actualizado_at' => $producto->updated_at?->toIso8601String(),
        ];
    }
}
