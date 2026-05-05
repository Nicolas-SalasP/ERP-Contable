<?php

namespace App\Domains\Inventario\Controllers;

use App\Domains\Core\Models\User;
use App\Domains\Inventario\Models\MovimientoInventario;
use App\Domains\Inventario\Services\InventarioMovimientoService;
use App\Domains\Inventario\Services\InventarioPermisoService;
use App\Domains\Inventario\Services\InventarioService;
use App\Domains\Inventario\Services\InventarioValorizacionService;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventarioController
{
    protected InventarioService $service;

    protected InventarioMovimientoService $movimientoService;

    protected InventarioPermisoService $permisos;

    protected InventarioValorizacionService $valorizacionService;

    public function __construct(
        InventarioService $service,
        InventarioMovimientoService $movimientoService,
        InventarioPermisoService $permisos,
        InventarioValorizacionService $valorizacionService
    ) {
        $this->service = $service;
        $this->movimientoService = $movimientoService;
        $this->permisos = $permisos;
        $this->valorizacionService = $valorizacionService;
    }

    /*
    |--------------------------------------------------------------------------
    | Fase 1 - Catálogos, productos y bodegas
    |--------------------------------------------------------------------------
    */

    public function catalogos(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->catalogos($request->user()->empresa_id),
        ]);
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

    public function bodegas(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->listarBodegas($request->user()),
        ]);
    }

    public function storeBodega(Request $request): JsonResponse
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

    /*
    |--------------------------------------------------------------------------
    | Fase 2 - Movimientos de Inventario y Kardex
    |--------------------------------------------------------------------------
    */

    public function movimientos(Request $request): JsonResponse
    {
        try {
            $usuario = $request->user();

            $this->permisos->exigir($usuario, 'inventario.movimientos.ver');

            $filtros = $request->validate([
                'producto_id' => ['nullable', 'integer'],
                'bodega_id' => ['nullable', 'integer'],
                'tipo' => ['nullable', Rule::in(MovimientoInventario::tiposPermitidos())],
                'desde' => ['nullable', 'date'],
                'hasta' => ['nullable', 'date'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            $paginador = $this->movimientoService->listarMovimientos(
                $filtros,
                (int) $usuario->empresa_id
            );

            return response()->json($this->respuestaPaginada($paginador));
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function registrarMovimiento(Request $request): JsonResponse
    {
        try {
            $usuario = $request->user();

            $datos = $request->validate([
                'tipo' => ['required', Rule::in(MovimientoInventario::tiposPermitidos())],
                'producto_id' => ['required', 'integer'],
                'bodega_origen_id' => ['nullable', 'integer'],
                'bodega_destino_id' => ['nullable', 'integer'],
                'cantidad' => ['required', 'numeric', 'gt:0'],
                'costo_unitario' => ['nullable', 'numeric', 'min:0'],
                'referencia' => ['nullable', 'string', 'max:120'],
                'motivo' => ['nullable', 'string', 'max:80'],
                'observacion' => ['nullable', 'string', 'max:2000'],
                'fecha_movimiento' => ['nullable', 'date'],
            ]);

            $this->validarPermisoMovimiento($usuario, $datos['tipo']);
            $this->validarBodegasMovimiento($datos);

            $movimiento = $this->movimientoService->registrarMovimiento(
                $datos,
                (int) $usuario->empresa_id,
                (int) $usuario->id
            );

            return response()->json([
                'success' => true,
                'data' => $movimiento->load([
                    'producto:id,empresa_id,sku,nombre,activo',
                    'bodegaOrigen:id,empresa_id,codigo,nombre,estado',
                    'bodegaDestino:id,empresa_id,codigo,nombre,estado',
                ]),
                'message' => 'Movimiento de inventario registrado correctamente.',
            ], 201);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function kardex(Request $request): JsonResponse
    {
        try {
            $usuario = $request->user();

            $this->permisos->exigir($usuario, 'inventario.kardex.ver');

            $filtros = $request->validate([
                'producto_id' => ['nullable', 'integer'],
                'bodega_id' => ['nullable', 'integer'],
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
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Fase 3 - Precio Medio Ponderado / Valorización
    |--------------------------------------------------------------------------
    |
    | Inventario NO emite, gestiona ni prepara DTE.
    | Estos endpoints consultan stock valorizado y resumen PMP.
    |
    */

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
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Validaciones privadas
    |--------------------------------------------------------------------------
    */

    private function validarPermisoMovimiento(User $usuario, string $tipo): void
    {
        $permiso = match ($tipo) {
            MovimientoInventario::TIPO_ENTRADA => 'inventario.movimientos.entrada',
            MovimientoInventario::TIPO_SALIDA => 'inventario.movimientos.salida',
            MovimientoInventario::TIPO_TRASPASO => 'inventario.movimientos.traspaso',
            MovimientoInventario::TIPO_AJUSTE_POSITIVO,
            MovimientoInventario::TIPO_AJUSTE_NEGATIVO => 'inventario.movimientos.ajuste',
            default => 'inventario.movimientos.ver',
        };

        $this->permisos->exigir($usuario, $permiso);
    }

    private function validarBodegasMovimiento(array $datos): void
    {
        $tipo = $datos['tipo'];

        if (
            in_array($tipo, [
                MovimientoInventario::TIPO_SALIDA,
                MovimientoInventario::TIPO_TRASPASO,
                MovimientoInventario::TIPO_AJUSTE_NEGATIVO,
            ], true)
            && empty($datos['bodega_origen_id'])
        ) {
            throw ValidationException::withMessages([
                'bodega_origen_id' => 'La bodega origen es obligatoria para este tipo de movimiento.',
            ]);
        }

        if (
            in_array($tipo, [
                MovimientoInventario::TIPO_ENTRADA,
                MovimientoInventario::TIPO_TRASPASO,
                MovimientoInventario::TIPO_AJUSTE_POSITIVO,
            ], true)
            && empty($datos['bodega_destino_id'])
        ) {
            throw ValidationException::withMessages([
                'bodega_destino_id' => 'La bodega destino es obligatoria para este tipo de movimiento.',
            ]);
        }

        if (
            $tipo === MovimientoInventario::TIPO_TRASPASO
            && !empty($datos['bodega_origen_id'])
            && !empty($datos['bodega_destino_id'])
            && (int) $datos['bodega_origen_id'] === (int) $datos['bodega_destino_id']
        ) {
            throw ValidationException::withMessages([
                'bodega_destino_id' => 'La bodega destino debe ser distinta a la bodega origen.',
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers de respuesta
    |--------------------------------------------------------------------------
    */

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

    private function respuestaPaginadaConResumen(LengthAwarePaginator $paginador, array $resumen): array
    {
        $respuesta = $this->respuestaPaginada($paginador);
        $respuesta['resumen'] = $resumen;

        return $respuesta;
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