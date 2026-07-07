<?php

namespace App\Domains\Inventario\Controllers\Concerns;

use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/** Única fuente de paginación y manejo de errores reutilizada por los controllers de Inventario que se van separando del monolito. */
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
