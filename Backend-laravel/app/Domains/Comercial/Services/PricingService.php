<?php

namespace App\Domains\Comercial\Services;

use App\Domains\Comercial\Exceptions\ComercialException;
use App\Domains\Comercial\Models\ProveedorProducto;
use App\Domains\Core\Models\Empresa;
use App\Domains\Inventario\Models\Producto;

/**
 * Sugiere precio de venta a partir del costo de reposicion mas barato entre los
 * proveedores vigentes del producto (fallback: costo_promedio del producto si no
 * tiene proveedores cargados) y un margen fijo configurable por empresa, con un
 * piso dinamico que nunca deja el precio por debajo de costo + margen minimo.
 *
 * Solo calcula y sugiere: aplicar la sugerencia a un producto es una accion
 * explicita separada (ver aplicarSugerencia()).
 */
class PricingService
{
    private const MARGEN_DEFECTO_PCT = 30.0;

    private const MARGEN_MINIMO_DEFECTO_PCT = 5.0;

    /**
     * @return array{costo_usado: float, proveedor_usado: int|null, margen_aplicado: float, precio_sugerido: float, piso_aplicado: bool}
     */
    public function sugerirPrecio(Producto $producto): array
    {
        $mejorOferta = ProveedorProducto::query()
            ->where('empresa_id', $producto->empresa_id)
            ->where('producto_id', $producto->id)
            ->where('activo', true)
            ->orderBy('costo_neto')
            ->first();

        $costoUsado = $mejorOferta !== null
            ? (float) $mejorOferta->costo_neto
            : (float) $producto->costo_promedio;

        if ($costoUsado <= 0) {
            throw ComercialException::regla(
                'No hay costo de reposicion disponible para sugerir un precio: el producto no tiene proveedores vigentes en proveedor_productos ni costo_promedio cargado.'
            );
        }

        $empresa = Empresa::find($producto->empresa_id);
        $margenPct = $this->porcentajeAFraccion($empresa?->margen_venta_pct, self::MARGEN_DEFECTO_PCT);
        $margenMinimoPct = $this->porcentajeAFraccion($empresa?->margen_minimo_pct, self::MARGEN_MINIMO_DEFECTO_PCT);

        $precioSugerido = round($costoUsado * (1 + $margenPct), 2);
        $piso = round($costoUsado * (1 + $margenMinimoPct), 2);

        $pisoAplicado = $precioSugerido < $piso;
        if ($pisoAplicado) {
            $precioSugerido = $piso;
        }

        return [
            'costo_usado' => round($costoUsado, 4),
            'proveedor_usado' => $mejorOferta?->proveedor_id,
            'margen_aplicado' => $margenPct,
            'precio_sugerido' => $precioSugerido,
            'piso_aplicado' => $pisoAplicado,
        ];
    }

    /** Aplica el precio sugerido al producto. Accion explicita: nunca se llama automaticamente. */
    public function aplicarSugerencia(Producto $producto): Producto
    {
        $sugerencia = $this->sugerirPrecio($producto);

        $producto->update(['precio_venta_neto' => $sugerencia['precio_sugerido']]);

        return $producto->fresh();
    }

    private function porcentajeAFraccion(mixed $valor, float $defecto): float
    {
        $pct = $valor !== null ? (float) $valor : $defecto;

        return $pct / 100;
    }
}
