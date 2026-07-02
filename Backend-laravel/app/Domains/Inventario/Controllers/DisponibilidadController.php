<?php

namespace App\Domains\Inventario\Controllers;

use App\Domains\Inventario\Exceptions\InventarioException;
use App\Support\MensajeErrorGenerico;

use App\Domains\Inventario\Controllers\Concerns\RespondeInventario;
use App\Domains\Inventario\Services\InventarioDisponibilidadService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Consulta de disponibilidad de inventario (general y por producto). Extraído de
 * InventarioController (H7 sprint 9) sin cambiar contratos. Un único servicio y solo
 * la capa de respuesta compartida; sin helpers propios.
 */
class DisponibilidadController
{
    use RespondeInventario;

    public function __construct(
        protected InventarioDisponibilidadService $disponibilidadService,
    ) {
    }

    public function disponibilidad(Request $request): JsonResponse
    {
        try {
            $filtros = $request->validate([
                'producto_id' => ['nullable', 'integer'],
                'bodega_id' => ['nullable', 'integer'],
                'ubicacion_id' => ['nullable', 'integer'],
                'incluir_lotes' => ['nullable', 'boolean'],
                'page' => ['nullable', 'integer', 'min:1'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            $paginador = $this->disponibilidadService->consultar(
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

    public function disponibilidadProducto(Request $request, $id): JsonResponse
    {
        try {
            $filtros = $request->validate([
                'bodega_id' => ['nullable', 'integer'],
                'ubicacion_id' => ['nullable', 'integer'],
                'incluir_lotes' => ['nullable', 'boolean'],
            ]);

            $disponibilidad = $this->disponibilidadService->porProducto(
                $request->user(),
                (int) $id,
                $filtros
            );

            return response()->json([
                'success' => true,
                'data' => $disponibilidad,
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
