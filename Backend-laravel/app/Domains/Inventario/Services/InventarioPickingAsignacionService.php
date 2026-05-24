<?php

namespace App\Domains\Inventario\Services;

use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\StockUbicacionInventario;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class InventarioPickingAsignacionService
{
    /**
     * Devuelve candidatos bloqueados y ordenados para sugerir picking desde stock disponible.
     * La operación de persistencia debe ejecutarse dentro de una transacción externa.
     */
    public function candidatosDisponibles(
        int $empresaId,
        int $productoId,
        int $bodegaId,
        ?int $loteId = null
    ): Collection {
        $query = StockUbicacionInventario::query()
            ->where('empresa_id', $empresaId)
            ->where('producto_id', $productoId)
            ->where('bodega_id', $bodegaId)
            ->where('stock_actual', '>', 0)
            ->with([
                'ubicacion:id,empresa_id,bodega_id,codigo,nombre,tipo,activo',
                'lote:id,empresa_id,producto_id,codigo_lote,fecha_fabricacion,fecha_vencimiento,estado_operativo,activo',
            ])
            ->lockForUpdate();

        if ($loteId !== null) {
            $query->where('lote_id', $loteId);
        }

        return $query->get()
            ->filter(function (StockUbicacionInventario $stock) {
                if ($stock->ubicacion && !$stock->ubicacion->activo) {
                    return false;
                }

                if ($stock->lote) {
                    if (!$stock->lote->activo || $stock->lote->estaVencido() || $stock->lote->estaBloqueadoOperativamente()) {
                        return false;
                    }
                }

                return $stock->stockDisponible() > 0;
            })
            ->sortBy(function (StockUbicacionInventario $stock) {
                $fechaVencimiento = $stock->lote?->fecha_vencimiento?->format('Y-m-d') ?? '9999-12-31';
                $fechaFabricacion = $stock->lote?->fecha_fabricacion?->format('Y-m-d') ?? '9999-12-31';
                $codigoUbicacion = $stock->ubicacion?->codigo ?? 'ZZZ';

                return $fechaVencimiento . '|' . $fechaFabricacion . '|' . $codigoUbicacion . '|' . str_pad((string) $stock->id, 12, '0', STR_PAD_LEFT);
            })
            ->values();
    }

    /**
     * Construye una asignación multiubicación/multilote respetando FEFO/FIFO/código de ubicación.
     * Retorna filas con el stock sugerido y la cantidad parcial a reservar por cada ubicación/lote.
     */
    public function construirAsignaciones(
        int $empresaId,
        Producto $producto,
        int $bodegaId,
        float $cantidad,
        ?int $loteId = null,
        string $campo = 'cantidad'
    ): array {
        $cantidad = $this->redondearCantidad($cantidad);
        $pendiente = $cantidad;
        $asignaciones = [];
        $candidatos = $this->candidatosDisponibles($empresaId, (int) $producto->id, $bodegaId, $loteId);

        foreach ($candidatos as $stock) {
            if ($pendiente <= 0) {
                break;
            }

            $disponible = $this->redondearCantidad($stock->stockDisponible());

            if ($disponible <= 0) {
                continue;
            }

            $cantidadParcial = $this->redondearCantidad(min($pendiente, $disponible));

            if ($cantidadParcial <= 0) {
                continue;
            }

            $asignaciones[] = [
                'stock' => $stock,
                'cantidad' => $cantidadParcial,
            ];

            $pendiente = $this->redondearCantidad($pendiente - $cantidadParcial);
        }

        if (empty($asignaciones)) {
            throw ValidationException::withMessages([
                $campo => 'No existe stock disponible para sugerir picking del producto en la bodega indicada.',
            ]);
        }

        return $asignaciones;
    }

    /**
     * Compatibilidad con Fase 14 base. Fase 14.1 usa construirAsignaciones().
     */
    public function sugerirPrimeraUbicacion(
        int $empresaId,
        Producto $producto,
        int $bodegaId,
        float $cantidad,
        ?int $loteId = null,
        string $campo = 'cantidad'
    ): StockUbicacionInventario {
        $asignaciones = $this->construirAsignaciones($empresaId, $producto, $bodegaId, $cantidad, $loteId, $campo);

        return $asignaciones[0]['stock'];
    }

    private function redondearCantidad(float $value): float
    {
        return round($value, 4);
    }
}
