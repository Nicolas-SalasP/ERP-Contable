<?php

namespace App\Domains\Inventario\Controllers;

use App\Domains\Inventario\Exceptions\InventarioException;

use App\Domains\Inventario\Controllers\Concerns\RespondeInventario;
use App\Domains\Inventario\Services\InventarioPermisoService;
use App\Domains\Inventario\Services\InventarioValorizacionService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Valorización de inventario (general y por producto). Extraído de InventarioController
 * (H7 sprint 5) sin cambiar contratos. Reutiliza la capa de respuesta compartida
 * (RespondeInventario); no aporta helpers propios.
 */
class ValorizacionController
{
    use RespondeInventario;

    public function __construct(
        protected InventarioValorizacionService $valorizacionService,
        protected InventarioPermisoService $permisos,
    ) {
    }

    public function valorizacion(Request $request): JsonResponse
    {
        try {
            $usuario = $request->user();

            $this->permisos->exigir($usuario, 'inventario.valorizacion.ver');

            $filtros = $request->validate([
                'producto_id' => ['nullable', 'integer'],
                'bodega_id' => ['nullable', 'integer'],
                'search' => ['nullable', 'string', 'max:120'],
                'page' => ['nullable', 'integer', 'min:1'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            $paginador = $this->valorizacionService->listarValorizacion(
                (int) $usuario->empresa_id,
                $filtros
            );

            $resumen = $this->valorizacionService->resumenValorizacion(
                (int) $usuario->empresa_id,
                $filtros
            );

            return response()->json(
                $this->respuestaPaginadaConResumen($paginador, $resumen)
            );
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (InventarioException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function valorizacionProducto(Request $request, $id): JsonResponse
    {
        try {
            $usuario = $request->user();

            $this->permisos->exigir($usuario, 'inventario.valorizacion.ver');

            $filtros = $request->validate([
                'bodega_id' => ['nullable', 'integer'],
                'search' => ['nullable', 'string', 'max:120'],
                'page' => ['nullable', 'integer', 'min:1'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            $filtros['producto_id'] = (int) $id;

            $paginador = $this->valorizacionService->listarValorizacion(
                (int) $usuario->empresa_id,
                $filtros
            );

            $resumen = $this->valorizacionService->resumenValorizacion(
                (int) $usuario->empresa_id,
                $filtros
            );

            return response()->json(
                $this->respuestaPaginadaConResumen($paginador, $resumen)
            );
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (InventarioException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }
}
