<?php

namespace App\Domains\Inventario\Controllers;

use App\Domains\Inventario\Exceptions\InventarioException;
use App\Support\MensajeErrorGenerico;

use App\Domains\Inventario\Controllers\Concerns\RespondeInventario;
use App\Domains\Inventario\Services\InventarioLoteService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Depende de un único servicio y reutiliza la capa de respuesta compartida; sin helpers propios. */
class LoteController
{
    use RespondeInventario;

    public function __construct(
        protected InventarioLoteService $loteService,
    ) {
    }

    public function lotes(Request $request): JsonResponse
    {
        try {
            $filtros = $request->validate([
                'producto_id' => ['nullable', 'integer'],
                'activo' => ['nullable'],
                'search' => ['nullable', 'string', 'max:120'],
                'vencidos' => ['nullable'],
                'por_vencer_hasta' => ['nullable', 'date'],
                'page' => ['nullable', 'integer', 'min:1'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            $paginador = $this->loteService->listarLotes(
                $request->user(),
                $filtros
            );

            return response()->json($this->respuestaPaginada($paginador));
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (InventarioException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function storeLote(Request $request): JsonResponse
    {
        try {
            $datos = $request->validate([
                'producto_id' => ['required', 'integer'],
                'codigo_lote' => ['required', 'string', 'max:80'],
                'fecha_fabricacion' => ['nullable', 'date'],
                'fecha_vencimiento' => ['nullable', 'date'],
                'observacion' => ['nullable', 'string', 'max:2000'],
                'activo' => ['nullable', 'boolean'],
            ]);

            $lote = $this->loteService->crearLote($request->user(), $datos);

            return response()->json([
                'success' => true,
                'data' => $lote,
                'message' => 'Lote de inventario creado correctamente.',
            ], 201);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (InventarioException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function showLote(Request $request, $id): JsonResponse
    {
        try {
            $lote = $this->loteService->obtenerLote($request->user(), (int) $id);

            return response()->json([
                'success' => true,
                'data' => $lote,
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (InventarioException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function updateLote(Request $request, $id): JsonResponse
    {
        try {
            $datos = $request->validate([
                'codigo_lote' => ['nullable', 'string', 'max:80'],
                'fecha_fabricacion' => ['nullable', 'date'],
                'fecha_vencimiento' => ['nullable', 'date'],
                'observacion' => ['nullable', 'string', 'max:2000'],
                'activo' => ['nullable', 'boolean'],
            ]);

            $lote = $this->loteService->actualizarLote(
                $request->user(),
                (int) $id,
                $datos
            );

            return response()->json([
                'success' => true,
                'data' => $lote,
                'message' => 'Lote de inventario actualizado correctamente.',
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (InventarioException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function lotesProducto(Request $request, $id): JsonResponse
    {
        try {
            $filtros = $request->validate([
                'activo' => ['nullable'],
                'con_stock' => ['nullable'],
            ]);

            $lotes = $this->loteService->listarLotesProducto(
                $request->user(),
                (int) $id,
                $filtros
            );

            return response()->json([
                'success' => true,
                'data' => $lotes,
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (InventarioException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function stockLote(Request $request, $id): JsonResponse
    {
        try {
            $stock = $this->loteService->consultarStockPorLote(
                $request->user(),
                (int) $id
            );

            return response()->json([
                'success' => true,
                'data' => $stock,
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
