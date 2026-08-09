<?php

namespace App\Domains\Comercial\Controllers;

use App\Domains\Comercial\Exceptions\ComercialException;
use App\Domains\Comercial\Services\PricingService;
use App\Domains\Inventario\Models\Producto;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    public function __construct(
        private readonly PricingService $service,
    ) {}

    public function sugerir(Request $request, int $producto): JsonResponse
    {
        $producto = $this->obtenerProductoEmpresa($producto, (int) $request->user()->empresa_activa_id);

        return response()->json(['success' => true, 'data' => $this->service->sugerirPrecio($producto)]);
    }

    public function aplicar(Request $request, int $producto): JsonResponse
    {
        $producto = $this->obtenerProductoEmpresa($producto, (int) $request->user()->empresa_activa_id);

        $productoActualizado = $this->service->aplicarSugerencia($producto);

        return response()->json([
            'success' => true,
            'data' => $productoActualizado,
            'message' => 'Precio de venta actualizado a partir de la sugerencia.',
        ]);
    }

    private function obtenerProductoEmpresa(int $productoId, int $empresaId): Producto
    {
        $producto = Producto::where('id', $productoId)->where('empresa_id', $empresaId)->first();

        if ($producto === null) {
            throw ComercialException::noEncontrado('El producto no existe o no pertenece a la empresa.');
        }

        return $producto;
    }
}
