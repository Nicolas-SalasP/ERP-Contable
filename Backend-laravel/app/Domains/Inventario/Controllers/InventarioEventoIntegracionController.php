<?php

namespace App\Domains\Inventario\Controllers;

use App\Domains\Inventario\Models\InventarioEventoIntegracion;
use App\Domains\Inventario\Services\InventarioEventoIntegracionService;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventarioEventoIntegracionController
{
    public function __construct(private readonly InventarioEventoIntegracionService $service)
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

    public function procesar(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->service->marcarProcesado($request->user(), $id),
            ]);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function ignorar(Request $request, int $id): JsonResponse
    {
        try {
            $payload = $request->validate([
                'motivo' => ['nullable', 'string', 'max:500'],
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->service->marcarIgnorado($request->user(), $id, $payload['motivo'] ?? null),
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function error(Request $request, int $id): JsonResponse
    {
        try {
            $payload = $request->validate([
                'mensaje' => ['required', 'string', 'max:2000'],
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->service->marcarError($request->user(), $id, $payload['mensaje']),
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
            'evento' => ['nullable', Rule::in(InventarioEventoIntegracion::eventosPermitidos())],
            'entidad_tipo' => ['nullable', 'string', 'max:120'],
            'entidad_id' => ['nullable', 'integer'],
            'usuario_id' => ['nullable', 'integer'],
            'estado' => ['nullable', Rule::in(InventarioEventoIntegracion::estadosPermitidos())],
            'prioridad' => ['nullable', Rule::in(InventarioEventoIntegracion::prioridadesPermitidas())],
            'modulo_origen' => ['nullable', 'string', 'max:40'],
            'origen_modulo' => ['nullable', 'string', 'max:80'],
            'origen_id' => ['nullable', 'integer'],
            'correlacion_id' => ['nullable', 'string', 'max:120'],
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
