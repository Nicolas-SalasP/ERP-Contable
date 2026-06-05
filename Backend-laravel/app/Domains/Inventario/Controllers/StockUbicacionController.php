<?php

namespace App\Domains\Inventario\Controllers;

use App\Domains\Inventario\Controllers\Concerns\RespondeInventario;
use App\Domains\Inventario\Models\StockUbicacionInventario;
use App\Domains\Inventario\Services\InventarioStockUbicacionService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Distribución de stock entre ubicaciones: listado, movimiento entre ubicaciones y
 * confirmación de putaway. Extraído de InventarioController (H7 sprint 11b). Concern
 * separado de Ubicación (CRUD de ubicaciones). confirmarPutaway delega en
 * moverStockUbicacion (mismo flujo). Un único servicio, solo trait.
 */
class StockUbicacionController
{
    use RespondeInventario;

    public function __construct(
        protected InventarioStockUbicacionService $stockUbicacionService,
    ) {
    }

    public function stockUbicaciones(Request $request): JsonResponse
    {
        try {
            $filtros = $request->validate([
                'producto_id' => ['nullable', 'integer'],
                'bodega_id' => ['nullable', 'integer'],
                'ubicacion_id' => ['nullable', 'integer'],
                'lote_id' => ['nullable', 'integer'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            return response()->json($this->respuestaPaginada(
                $this->stockUbicacionService->listar($request->user(), $filtros)
            ));
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function moverStockUbicacion(Request $request): JsonResponse
    {
        try {
            $datos = $request->validate([
                'producto_id' => ['required', 'integer'],
                'bodega_origen_id' => ['required', 'integer'],
                'bodega_destino_id' => ['required', 'integer'],
                'ubicacion_origen_id' => ['required', 'integer'],
                'ubicacion_destino_id' => ['required', 'integer'],
                'lote_id' => ['nullable', 'integer'],
                'cantidad' => ['required', 'numeric', 'gt:0'],
                'estado_stock_origen' => ['nullable', Rule::in(StockUbicacionInventario::estadosPermitidos())],
                'estado_stock_destino' => ['nullable', Rule::in(StockUbicacionInventario::estadosPermitidos())],
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->stockUbicacionService->moverStock($request->user(), $datos),
                'message' => 'Stock movido entre ubicaciones correctamente.',
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function confirmarPutaway(Request $request): JsonResponse
    {
        return $this->moverStockUbicacion($request);
    }
}
