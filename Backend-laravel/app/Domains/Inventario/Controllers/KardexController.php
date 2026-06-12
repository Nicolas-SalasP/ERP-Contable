<?php

namespace App\Domains\Inventario\Controllers;

use App\Domains\Inventario\Exceptions\InventarioException;

use App\Domains\Inventario\Controllers\Concerns\RespondeInventario;
use App\Domains\Inventario\Models\MovimientoInventario;
use App\Domains\Inventario\Services\InventarioMovimientoService;
use App\Domains\Inventario\Services\InventarioPermisoService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Consulta del kardex (lado lectura de los movimientos). Extraído de
 * InventarioController (H7 sprint 4) sin cambiar contratos. Solo reutiliza la capa
 * de respuesta compartida (RespondeInventario); no aporta helpers propios.
 */
class KardexController
{
    use RespondeInventario;

    public function __construct(
        protected InventarioMovimientoService $movimientoService,
        protected InventarioPermisoService $permisos,
    ) {
    }

    public function kardex(Request $request): JsonResponse
    {
        try {
            $usuario = $request->user();

            $this->permisos->exigir($usuario, 'inventario.kardex.ver');

            $filtros = $request->validate([
                'producto_id' => ['nullable', 'integer'],
                'bodega_id' => ['nullable', 'integer'],
                'lote_id' => ['nullable', 'integer'],
                'ubicacion_id' => ['nullable', 'integer'],
                'tipo' => ['nullable', Rule::in(MovimientoInventario::tiposPermitidos())],
                'desde' => ['nullable', 'date'],
                'hasta' => ['nullable', 'date'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            $paginador = $this->movimientoService->kardexGeneral(
                $filtros,
                (int) $usuario->empresa_id
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

    public function kardexProducto(Request $request, $id): JsonResponse
    {
        try {
            $usuario = $request->user();

            $this->permisos->exigir($usuario, 'inventario.kardex.ver');

            $filtros = $request->validate([
                'bodega_id' => ['nullable', 'integer'],
                'lote_id' => ['nullable', 'integer'],
                'tipo' => ['nullable', Rule::in(MovimientoInventario::tiposPermitidos())],
                'desde' => ['nullable', 'date'],
                'hasta' => ['nullable', 'date'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            $paginador = $this->movimientoService->kardexProducto(
                (int) $id,
                $filtros,
                (int) $usuario->empresa_id
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
}
