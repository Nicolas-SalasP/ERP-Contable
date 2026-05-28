<?php

namespace App\Domains\Inventario\Controllers;

use App\Domains\Inventario\Models\InventarioPackingOrden;
use App\Domains\Inventario\Services\InventarioPackingService;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventarioPackingController
{
    public function __construct(private readonly InventarioPackingService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $filtros = $request->validate([
                'estado' => ['nullable', Rule::in([
                    InventarioPackingOrden::ESTADO_PENDIENTE,
                    InventarioPackingOrden::ESTADO_EN_EMPAQUE,
                    InventarioPackingOrden::ESTADO_EMPACADO,
                    InventarioPackingOrden::ESTADO_CON_DIFERENCIAS,
                    InventarioPackingOrden::ESTADO_CANCELADO,
                ])],
                'bodega_id' => ['nullable', 'integer'],
                'picking_orden_id' => ['nullable', 'integer'],
                'search' => ['nullable', 'string', 'max:120'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            ]);

            return response()->json($this->respuestaPaginada($this->service->listar($request->user(), $filtros)));
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $datos = $request->validate([
                'picking_orden_id' => ['required', 'integer'],
                'codigo' => ['nullable', 'string', 'max:60'],
                'observacion' => ['nullable', 'string', 'max:2000'],
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->service->crear($request->user(), $datos),
                'message' => 'Orden de packing creada correctamente.',
            ], 201);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->service->obtener($request->user(), $id),
            ]);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function iniciar(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->service->iniciar($request->user(), $id),
                'message' => 'Packing iniciado correctamente.',
            ]);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function confirmar(Request $request, int $id): JsonResponse
    {
        try {
            $datos = $request->validate([
                'observacion' => ['nullable', 'string', 'max:2000'],
                'detalles' => ['nullable', 'array'],
                'detalles.*.id' => ['nullable', 'integer'],
                'detalles.*.detalle_id' => ['nullable', 'integer'],
                'detalles.*.cantidad_empacada' => ['required_with:detalles', 'numeric', 'min:0'],
                'detalles.*.observacion' => ['nullable', 'string', 'max:2000'],
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->service->confirmar($request->user(), $id, $datos),
                'message' => 'Packing confirmado correctamente.',
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function cancelar(Request $request, int $id): JsonResponse
    {
        try {
            $datos = $request->validate([
                'observacion' => ['nullable', 'string', 'max:2000'],
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->service->cancelar($request->user(), $id, $datos),
                'message' => 'Packing cancelado correctamente.',
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function reporte(Request $request): JsonResponse
    {
        try {
            $filtros = $request->validate([
                'bodega_id' => ['nullable', 'integer'],
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->service->reporte($request->user(), $filtros),
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    private function respuestaPaginada(LengthAwarePaginator $paginador): array
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

    private function respuestaValidacion(ValidationException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Los datos enviados no son válidos.',
            'errors' => $e->errors(),
        ], 422);
    }

    private function respuestaError(Exception $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 422);
    }
}
