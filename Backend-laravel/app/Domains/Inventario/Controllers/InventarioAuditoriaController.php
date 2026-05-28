<?php

namespace App\Domains\Inventario\Controllers;

use App\Domains\Inventario\Models\InventarioAuditoriaEvento;
use App\Domains\Inventario\Services\InventarioAuditoriaService;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventarioAuditoriaController
{
    public function __construct(private readonly InventarioAuditoriaService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $filtros = $this->validarFiltros($request);

            return response()->json($this->respuestaPaginada($this->service->listar($request->user(), $filtros)));
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

    public function resumen(Request $request): JsonResponse
    {
        try {
            $filtros = $this->validarFiltros($request, false);

            return response()->json([
                'success' => true,
                'data' => $this->service->resumen($request->user(), $filtros),
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    private function validarFiltros(Request $request, bool $incluyePaginacion = true): array
    {
        $reglas = [
            'accion' => ['nullable', Rule::in(InventarioAuditoriaEvento::accionesPermitidas())],
            'entidad_tipo' => ['nullable', 'string', 'max:120'],
            'entidad_id' => ['nullable', 'integer'],
            'usuario_id' => ['nullable', 'integer'],
            'severidad' => ['nullable', Rule::in(InventarioAuditoriaEvento::severidadesPermitidas())],
            'modulo' => ['nullable', 'string', 'max:40'],
            'origen_modulo' => ['nullable', 'string', 'max:80'],
            'origen_id' => ['nullable', 'integer'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
        ];

        if ($incluyePaginacion) {
            $reglas['per_page'] = ['nullable', 'integer', 'min:1', 'max:200'];
        }

        return $request->validate($reglas);
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
