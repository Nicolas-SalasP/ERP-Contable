<?php

namespace App\Domains\Inventario\Controllers;

use App\Domains\Inventario\Exceptions\InventarioException;
use App\Support\MensajeErrorGenerico;

use App\Domains\Inventario\Services\InventarioService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Extraído de InventarioController sin cambiar contratos: mismos paths, mismo middleware, mismo servicio. */
class BodegaController
{
    public function __construct(
        protected InventarioService $service,
    ) {
    }

    public function bodegas(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->listarBodegas($request->user()),
        ]);
    }

    public function storeBodega(Request $request): JsonResponse
    {
        try {
            $datos = $request->validate([
                'codigo' => 'required|string|max:20',
                'nombre' => 'required|string|max:120',
                'direccion' => 'nullable|string|max:255',
                'estado' => 'nullable|in:ACTIVA,INACTIVA',
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->service->crearBodega($request->user(), $datos),
                'message' => 'Bodega creada correctamente.',
            ], 201);
        } catch (InventarioException $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => MensajeErrorGenerico::desde($e),
            ], 422);
        }
    }
}
