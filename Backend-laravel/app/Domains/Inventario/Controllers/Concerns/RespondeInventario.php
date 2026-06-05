<?php

namespace App\Domains\Inventario\Controllers\Concerns;

use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Capa de respuesta JSON compartida por los controllers de Inventario. Extraída del
 * monolito InventarioController (H7) para ser la única fuente de paginación y manejo
 * de errores que reutilizan los controllers que se van separando.
 */
trait RespondeInventario
{
    protected function respuestaPaginada(LengthAwarePaginator $paginador): array
    {
        return [
            'success' => true,
            'data' => $paginador->items(),
            'pagination' => [
                'total' => $paginador->total(),
                'totalPages' => $paginador->lastPage(),
                'page' => $paginador->currentPage(),
            ],
        ];
    }

    protected function respuestaPaginadaConResumen(LengthAwarePaginator $paginador, array $resumen): array
    {
        $respuesta = $this->respuestaPaginada($paginador);
        $respuesta['resumen'] = $resumen;

        return $respuesta;
    }

    protected function respuestaValidacion(ValidationException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Los datos enviados no son válidos.',
            'errors' => $e->errors(),
        ], 422);
    }

    protected function respuestaError(Exception $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 422);
    }
}
