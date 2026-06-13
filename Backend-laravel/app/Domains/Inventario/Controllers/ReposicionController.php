<?php

namespace App\Domains\Inventario\Controllers;

use App\Domains\Inventario\Exceptions\InventarioException;

use App\Domains\Inventario\Controllers\Concerns\RespondeInventario;
use App\Domains\Inventario\Services\InventarioReposicionService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de reposición (CRUD) y sugerencias de reposición. Extraído de
 * InventarioController (H7 sprint 10) sin cambiar contratos. Concern separado de
 * Alertas (servicio y responsabilidad distintos). Un único servicio, solo trait.
 */
class ReposicionController
{
    use RespondeInventario;

    public function __construct(
        protected InventarioReposicionService $reposicionService,
    ) {
    }

    public function reglasReposicion(Request $request): JsonResponse
    {
        try {
            $filtros = $request->validate([
                'producto_id' => ['nullable', 'integer'],
                'bodega_id' => ['nullable', 'integer'],
                'activo' => ['nullable'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            return response()->json($this->respuestaPaginada(
                $this->reposicionService->listar($request->user(), $filtros)
            ));
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (InventarioException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function storeReglaReposicion(Request $request): JsonResponse
    {
        try {
            $datos = $request->validate([
                'producto_id' => ['required', 'integer'],
                'bodega_id' => ['nullable', 'integer'],
                'stock_minimo' => ['required', 'numeric', 'min:0'],
                'stock_objetivo' => ['required', 'numeric', 'min:0'],
                'punto_reorden' => ['nullable', 'numeric', 'min:0'],
                'dias_alerta_vencimiento' => ['nullable', 'integer', 'min:0', 'max:3650'],
                'activo' => ['nullable', 'boolean'],
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->reposicionService->crear($request->user(), $datos),
                'message' => 'Regla de reposición creada correctamente.',
            ], 201);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (InventarioException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function showReglaReposicion(Request $request, $id): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->reposicionService->obtener($request->user(), (int) $id),
            ]);
        } catch (InventarioException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function updateReglaReposicion(Request $request, $id): JsonResponse
    {
        try {
            $datos = $request->validate([
                'producto_id' => ['required', 'integer'],
                'bodega_id' => ['nullable', 'integer'],
                'stock_minimo' => ['required', 'numeric', 'min:0'],
                'stock_objetivo' => ['required', 'numeric', 'min:0'],
                'punto_reorden' => ['nullable', 'numeric', 'min:0'],
                'dias_alerta_vencimiento' => ['nullable', 'integer', 'min:0', 'max:3650'],
                'activo' => ['nullable', 'boolean'],
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->reposicionService->actualizar($request->user(), (int) $id, $datos),
                'message' => 'Regla de reposición actualizada correctamente.',
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (InventarioException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function destroyReglaReposicion(Request $request, $id): JsonResponse
    {
        try {
            $this->reposicionService->eliminar($request->user(), (int) $id);

            return response()->json([
                'success' => true,
                'message' => 'Regla de reposición eliminada correctamente.',
            ]);
        } catch (InventarioException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function sugerenciasReposicion(Request $request): JsonResponse
    {
        try {
            $filtros = $request->validate([
                'producto_id' => ['nullable', 'integer'],
                'bodega_id' => ['nullable', 'integer'],
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->reposicionService->sugerencias($request->user(), $filtros),
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (InventarioException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }
}
