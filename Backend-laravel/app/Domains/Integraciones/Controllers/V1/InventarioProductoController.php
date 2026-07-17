<?php

namespace App\Domains\Integraciones\Controllers\V1;

use App\Domains\Integraciones\Http\Resources\ProductoIntegracionResource;
use App\Domains\Integraciones\Models\IntegracionApiKey;
use App\Domains\Integraciones\Services\InventarioIntegracionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventarioProductoController
{
    public function __construct(private readonly InventarioIntegracionService $servicio) {}

    public function index(Request $request): JsonResponse
    {
        $empresaId = $this->empresaId($request);

        $paginador = $this->servicio->listar($empresaId, $request->only([
            'search', 'sku', 'activo', 'visible_web', 'updated_since', 'limit',
        ]));

        return response()->json([
            'success' => true,
            'data' => ProductoIntegracionResource::collection($paginador->items()),
            'pagination' => [
                'total' => $paginador->total(),
                'total_pages' => $paginador->lastPage(),
                'page' => $paginador->currentPage(),
            ],
        ]);
    }

    public function show(Request $request, string $sku): JsonResponse
    {
        $producto = $this->servicio->obtenerPorSku($this->empresaId($request), $sku);

        if ($producto === null) {
            return response()->json(['success' => false, 'message' => 'Producto no encontrado.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new ProductoIntegracionResource($producto),
        ]);
    }

    public function actualizarVisibleWeb(Request $request, string $sku): JsonResponse
    {
        $datos = $request->validate(['visible_web' => ['required', 'boolean']]);

        $producto = $this->servicio->actualizarVisibleWeb($this->empresaId($request), $sku, $datos['visible_web']);

        if ($producto === null) {
            return response()->json(['success' => false, 'message' => 'Producto no encontrado.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new ProductoIntegracionResource($producto),
        ]);
    }

    private function empresaId(Request $request): int
    {
        /** @var IntegracionApiKey $key */
        $key = $request->attributes->get('integracion_key');

        return (int) $key->empresa_id;
    }
}
