<?php

namespace App\Domains\Inventario\Controllers;

use App\Domains\Inventario\Controllers\Concerns\RespondeInventario;
use App\Domains\Inventario\Models\InventarioUbicacion;
use App\Domains\Inventario\Services\InventarioUbicacionService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Ubicaciones físicas de inventario: CRUD + stock de una ubicación concreta. Extraído
 * de InventarioController (H7 sprint 11a). Concern separado de StockUbicación (que mueve
 * stock entre ubicaciones). stockUbicacion va aquí porque usa InventarioUbicacionService
 * y es un sub-recurso de una ubicación, pese a tener "stock" en el nombre. Solo trait.
 */
class UbicacionController
{
    use RespondeInventario;

    public function __construct(
        protected InventarioUbicacionService $ubicacionService,
    ) {
    }

    public function ubicaciones(Request $request): JsonResponse
    {
        try {
            $filtros = $request->validate([
                'bodega_id' => ['nullable', 'integer'],
                'tipo' => ['nullable', Rule::in(InventarioUbicacion::tiposPermitidos())],
                'activo' => ['nullable'],
                'search' => ['nullable', 'string', 'max:120'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            ]);

            return response()->json($this->respuestaPaginada(
                $this->ubicacionService->listar($request->user(), $filtros)
            ));
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function storeUbicacion(Request $request): JsonResponse
    {
        try {
            $datos = $request->validate([
                'bodega_id' => ['required', 'integer'],
                'ubicacion_padre_id' => ['nullable', 'integer'],
                'codigo' => ['required', 'string', 'max:80'],
                'nombre' => ['required', 'string', 'max:160'],
                'tipo' => ['nullable', Rule::in(InventarioUbicacion::tiposPermitidos())],
                'pasillo' => ['nullable', 'string', 'max:40'],
                'estante' => ['nullable', 'string', 'max:40'],
                'nivel' => ['nullable', 'string', 'max:40'],
                'posicion' => ['nullable', 'string', 'max:40'],
                'capacidad_maxima' => ['nullable', 'numeric', 'min:0'],
                'activo' => ['nullable', 'boolean'],
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->ubicacionService->crear($request->user(), $datos),
                'message' => 'Ubicación de inventario creada correctamente.',
            ], 201);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function showUbicacion(Request $request, $id): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->ubicacionService->obtener($request->user(), (int) $id),
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function updateUbicacion(Request $request, $id): JsonResponse
    {
        try {
            $datos = $request->validate([
                'bodega_id' => ['nullable', 'integer'],
                'ubicacion_padre_id' => ['nullable', 'integer'],
                'codigo' => ['nullable', 'string', 'max:80'],
                'nombre' => ['nullable', 'string', 'max:160'],
                'tipo' => ['nullable', Rule::in(InventarioUbicacion::tiposPermitidos())],
                'pasillo' => ['nullable', 'string', 'max:40'],
                'estante' => ['nullable', 'string', 'max:40'],
                'nivel' => ['nullable', 'string', 'max:40'],
                'posicion' => ['nullable', 'string', 'max:40'],
                'capacidad_maxima' => ['nullable', 'numeric', 'min:0'],
                'activo' => ['nullable', 'boolean'],
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->ubicacionService->actualizar($request->user(), (int) $id, $datos),
                'message' => 'Ubicación de inventario actualizada correctamente.',
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function stockUbicacion(Request $request, $id): JsonResponse
    {
        try {
            $filtros = $request->validate([
                'producto_id' => ['nullable', 'integer'],
                'lote_id' => ['nullable', 'integer'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            return response()->json($this->respuestaPaginada(
                $this->ubicacionService->stock($request->user(), (int) $id, $filtros)
            ));
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }
}
