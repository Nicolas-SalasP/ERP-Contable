<?php

namespace App\Domains\Inventario\Controllers;

use App\Domains\Inventario\Services\InventarioAjusteCriticoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

/** No usa el trait RespondeInventario: su contrato de respuesta difiere (mensaje = primer error de validación, paginación con shape propio, captura Throwable). */
class AjusteCriticoController
{
    public function __construct(
        protected InventarioAjusteCriticoService $ajusteCriticoService,
    ) {
    }

    public function tiposAjusteCritico(Request $request): JsonResponse
    {
        try {
            $tipos = $this->ajusteCriticoService->listarTiposAjusteCritico($request->user());

            return response()->json([
                'success' => true,
                'data' => $tipos,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $this->mensajeValidacionAjusteCritico($e),
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function ajustesCriticos(Request $request): JsonResponse
    {
        try {
            $ajustes = $this->ajusteCriticoService->listarAjustesCriticos(
                $request->user(),
                $request->all()
            );

            return response()->json([
                'success' => true,
                'data' => $ajustes->items(),
                'meta' => [
                    'current_page' => $ajustes->currentPage(),
                    'from' => $ajustes->firstItem(),
                    'last_page' => $ajustes->lastPage(),
                    'per_page' => $ajustes->perPage(),
                    'to' => $ajustes->lastItem(),
                    'total' => $ajustes->total(),
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $this->mensajeValidacionAjusteCritico($e),
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function registrarAjusteCritico(Request $request): JsonResponse
    {
        try {
            $ajuste = $this->ajusteCriticoService->registrarAjusteCritico(
                $request->user(),
                $request->all()
            );

            return response()->json([
                'success' => true,
                'message' => 'Ajuste crítico registrado correctamente.',
                'data' => $ajuste,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $this->mensajeValidacionAjusteCritico($e),
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function verAjusteCritico(Request $request, int $id): JsonResponse
    {
        try {
            $ajuste = $this->ajusteCriticoService->obtenerAjusteCritico(
                $request->user(),
                $id
            );

            return response()->json([
                'success' => true,
                'data' => $ajuste,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $this->mensajeValidacionAjusteCritico($e),
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function anularAjusteCritico(Request $request, int $id): JsonResponse
    {
        try {
            $motivo = (string) $request->input('motivo_anulacion', '');

            $ajuste = $this->ajusteCriticoService->anularAjusteCritico(
                $request->user(),
                $id,
                $motivo
            );

            return response()->json([
                'success' => true,
                'message' => 'Ajuste crítico anulado: se registró un movimiento compensatorio.',
                'data' => $ajuste,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $this->mensajeValidacionAjusteCritico($e),
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    private function mensajeValidacionAjusteCritico(ValidationException $e): string
    {
        $errores = $e->errors();

        foreach ($errores as $mensajes) {
            if (is_array($mensajes) && isset($mensajes[0])) {
                return (string) $mensajes[0];
            }
        }

        return 'Los datos enviados no son válidos.';
    }
}
