<?php

namespace App\Domains\Inventario\Controllers;

use App\Domains\Inventario\Services\InventarioService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD de productos de inventario. Extraído de InventarioController (H7) sin cambiar
 * contratos: mismos paths, mismo middleware, mismo servicio. Solo CRUD de la entidad
 * Producto — disponibilidad, reposición y los anidados (kardex/valorización/lotes)
 * son concerns separados que viven en sus propios controllers.
 */
class ProductoController
{
    public function __construct(
        protected InventarioService $service,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $paginador = $this->service->listarProductos(
            $request->user(),
            $request->only(['search', 'activo', 'limit'])
        );

        return response()->json([
            'success' => true,
            'data' => $paginador->items(),
            'pagination' => [
                'total' => $paginador->total(),
                'totalPages' => $paginador->lastPage(),
                'page' => $paginador->currentPage(),
            ],
        ]);
    }

    public function show(Request $request, $id): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->service->obtenerProducto($request->user(), (int) $id),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $datos = $request->validate([
                'sku' => 'required|string|max:50',
                'nombre' => 'required|string|max:180',
                'descripcion' => 'nullable|string',
                'tipo_producto' => 'nullable|in:BIEN,SERVICIO,INSUMO',
                'unidad_medida_id' => 'required|integer',
                'metodo_valorizacion' => 'nullable|in:PMP,FIFO',
                'costo_promedio' => 'nullable|numeric|min:0',
                'precio_venta_neto' => 'nullable|numeric|min:0',
                'afecto_iva' => 'nullable|boolean',
                'codigo_barra' => 'nullable|string|max:80',
                'stock_minimo' => 'nullable|numeric|min:0',
                'bodega_defecto_id' => 'nullable|integer',
                'permite_merma' => 'nullable|boolean',
                'maneja_lotes' => 'nullable|boolean',
                'requiere_fecha_vencimiento' => 'nullable|boolean',
                'activo' => 'nullable|boolean',
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->service->crearProducto($request->user(), $datos),
                'message' => 'Producto de inventario creado correctamente.',
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $datos = $request->validate([
                'sku' => 'required|string|max:50',
                'nombre' => 'required|string|max:180',
                'descripcion' => 'nullable|string',
                'tipo_producto' => 'nullable|in:BIEN,SERVICIO,INSUMO',
                'unidad_medida_id' => 'required|integer',
                'metodo_valorizacion' => 'nullable|in:PMP,FIFO',
                'precio_venta_neto' => 'nullable|numeric|min:0',
                'afecto_iva' => 'nullable|boolean',
                'codigo_barra' => 'nullable|string|max:80',
                'stock_minimo' => 'nullable|numeric|min:0',
                'bodega_defecto_id' => 'nullable|integer',
                'permite_merma' => 'nullable|boolean',
                'maneja_lotes' => 'nullable|boolean',
                'requiere_fecha_vencimiento' => 'nullable|boolean',
                'activo' => 'nullable|boolean',
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->service->actualizarProducto($request->user(), (int) $id, $datos),
                'message' => 'Producto actualizado correctamente.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
