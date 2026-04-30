<?php

namespace App\Domains\Inventario\Controllers;

use App\Domains\Inventario\Services\InventarioService;
use Exception;
use Illuminate\Http\Request;

class InventarioController
{
    protected InventarioService $service;

    public function __construct(InventarioService $service)
    {
        $this->service = $service;
    }

    public function catalogos(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->catalogos($request->user()->empresa_id),
        ]);
    }

    public function index(Request $request)
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

    public function show(Request $request, $id)
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

    public function store(Request $request)
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

    public function update(Request $request, $id)
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

    public function bodegas(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->listarBodegas($request->user()),
        ]);
    }

    public function storeBodega(Request $request)
    {
        try {
            $datos = $request->validate([
                'codigo' => 'required|string|max:20',
                'nombre' => 'required|string|max:120',
                'direccion' => 'nullable|string|max:255',
                'estado' => 'nullable|in:ACTIVA,INACTIVA',
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->service->crearBodega($request->user(), $datos),
                'message' => 'Bodega creada correctamente.',
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}