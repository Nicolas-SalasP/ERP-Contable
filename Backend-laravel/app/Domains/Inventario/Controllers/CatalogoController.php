<?php

namespace App\Domains\Inventario\Controllers;

use App\Domains\Inventario\Controllers\Concerns\RespondeInventario;
use App\Domains\Inventario\Services\InventarioPermisoService;
use App\Domains\Inventario\Services\InventarioService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catálogos de referencia del módulo (unidades, tipos, etc.) que alimentan los
 * formularios. Extraído de InventarioController (H7 sprint 13b). Su gate de permiso es
 * transversal (varios módulos lo consultan), de ahí el permiso compuesto. Solo trait.
 */
class CatalogoController
{
    use RespondeInventario;

    public function __construct(
        protected InventarioService $service,
        protected InventarioPermisoService $permisos,
    ) {
    }

    public function catalogos(Request $request): JsonResponse
    {
        try {
            $this->permisos->exigirAlguno($request->user(), [
                'inventario.productos.ver',
                'inventario.bodegas.ver',
                'inventario.dashboard.ver',
                'inventario.reportes.ver',
                'inventario.ubicaciones.ver',
                'inventario.stock_ubicaciones.ver',
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->service->catalogos($request->user()->empresa_id),
            ]);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }
}
