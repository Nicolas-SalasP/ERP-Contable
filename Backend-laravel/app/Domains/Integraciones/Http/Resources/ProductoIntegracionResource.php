<?php

namespace App\Domains\Integraciones\Http\Resources;

use App\Domains\Inventario\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Contrato v1 estable para consumidores externos: allowlist explicita de campos, nunca un
 * wrap automatico del modelo. Agregar columnas a Producto (ej. costo_promedio, metodo_valorizacion)
 * NO filtra aca por accidente; un cambio de contrato incompatible se resuelve en v2, no editando esto.
 *
 * @property Producto $resource
 */
class ProductoIntegracionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $producto = $this->resource;

        return [
            'sku' => $producto->sku,
            'nombre' => $producto->nombre,
            'descripcion' => $producto->descripcion,
            'precio_venta_neto' => (float) $producto->precio_venta_neto,
            'afecto_iva' => $producto->afecto_iva,
            'codigo_barra' => $producto->codigo_barra,
            'stock_actual_total' => (float) ($producto->getAttribute('stock_actual_total') ?? 0),
            'activo' => $producto->activo,
            'visible_web' => $producto->visible_web,
            'actualizado_at' => $producto->updated_at?->toIso8601String(),
        ];
    }
}
