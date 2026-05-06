<?php

namespace App\Domains\Inventario\Controllers;

use App\Domains\Core\Models\User;
use App\Domains\Inventario\Models\MovimientoInventario;
use App\Domains\Inventario\Models\ReservaInventario;
use App\Domains\Inventario\Services\InventarioAjusteCriticoService;
use App\Domains\Inventario\Services\InventarioDisponibilidadService;
use App\Domains\Inventario\Services\InventarioLoteService;
use App\Domains\Inventario\Services\InventarioMovimientoService;
use App\Domains\Inventario\Services\InventarioPermisoService;
use App\Domains\Inventario\Services\InventarioReservaService;
use App\Domains\Inventario\Services\InventarioService;
use App\Domains\Inventario\Services\InventarioValorizacionService;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;
class InventarioController
{
    protected InventarioService $service;
    protected InventarioMovimientoService $movimientoService;
    protected InventarioPermisoService $permisos;
    protected InventarioValorizacionService $valorizacionService;
    protected InventarioLoteService $loteService;
    protected InventarioReservaService $reservaService;
    protected InventarioDisponibilidadService $disponibilidadService;

public function __construct(
    InventarioService $service,
    InventarioMovimientoService $movimientoService,
    InventarioPermisoService $permisos,
    InventarioValorizacionService $valorizacionService,
    InventarioLoteService $loteService,
    InventarioReservaService $reservaService,
    InventarioDisponibilidadService $disponibilidadService
) {
    $this->service = $service;
    $this->movimientoService = $movimientoService;
    $this->permisos = $permisos;
    $this->valorizacionService = $valorizacionService;
    $this->loteService = $loteService;
    $this->reservaService = $reservaService;
    $this->disponibilidadService = $disponibilidadService;
}

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
                'lote_id' => ['nullable', 'integer'],
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

                'lote_id' => ['nullable', 'integer'],
                'lote' => ['nullable', 'array'],
                'lote.codigo_lote' => ['required_with:lote', 'string', 'max:80'],
                'lote.fecha_fabricacion' => ['nullable', 'date'],
                'lote.fecha_vencimiento' => ['nullable', 'date'],
                'lote.observacion' => ['nullable', 'string', 'max:2000'],

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
                    'producto:id,empresa_id,sku,nombre,activo,maneja_lotes,requiere_fecha_vencimiento',
                    'bodegaOrigen:id,empresa_id,codigo,nombre,estado',
                    'bodegaDestino:id,empresa_id,codigo,nombre,estado',
                    'lotes.lote:id,empresa_id,producto_id,codigo_lote,fecha_fabricacion,fecha_vencimiento,activo',
                    'lotes.bodegaOrigen:id,empresa_id,codigo,nombre,estado',
                    'lotes.bodegaDestino:id,empresa_id,codigo,nombre,estado',
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
                'lote_id' => ['nullable', 'integer'],
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
    | Fase 5 - Lotes, vencimientos y trazabilidad avanzada
    |--------------------------------------------------------------------------
    |
    | Inventario NO emite, gestiona ni prepara DTE.
    | Los lotes entregan trazabilidad granular por producto/bodega/lote.
    |
    */

    public function lotes(Request $request): JsonResponse
    {
        try {
            $filtros = $request->validate([
                'producto_id' => ['nullable', 'integer'],
                'activo' => ['nullable'],
                'search' => ['nullable', 'string', 'max:120'],
                'vencidos' => ['nullable'],
                'por_vencer_hasta' => ['nullable', 'date'],
                'page' => ['nullable', 'integer', 'min:1'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            $paginador = $this->loteService->listarLotes(
                $request->user(),
                $filtros
            );

            return response()->json($this->respuestaPaginada($paginador));
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function storeLote(Request $request): JsonResponse
    {
        try {
            $datos = $request->validate([
                'producto_id' => ['required', 'integer'],
                'codigo_lote' => ['required', 'string', 'max:80'],
                'fecha_fabricacion' => ['nullable', 'date'],
                'fecha_vencimiento' => ['nullable', 'date'],
                'observacion' => ['nullable', 'string', 'max:2000'],
                'activo' => ['nullable', 'boolean'],
            ]);

            $lote = $this->loteService->crearLote($request->user(), $datos);

            return response()->json([
                'success' => true,
                'data' => $lote,
                'message' => 'Lote de inventario creado correctamente.',
            ], 201);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function showLote(Request $request, $id): JsonResponse
    {
        try {
            $lote = $this->loteService->obtenerLote($request->user(), (int) $id);

            return response()->json([
                'success' => true,
                'data' => $lote,
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function updateLote(Request $request, $id): JsonResponse
    {
        try {
            $datos = $request->validate([
                'codigo_lote' => ['nullable', 'string', 'max:80'],
                'fecha_fabricacion' => ['nullable', 'date'],
                'fecha_vencimiento' => ['nullable', 'date'],
                'observacion' => ['nullable', 'string', 'max:2000'],
                'activo' => ['nullable', 'boolean'],
            ]);

            $lote = $this->loteService->actualizarLote(
                $request->user(),
                (int) $id,
                $datos
            );

            return response()->json([
                'success' => true,
                'data' => $lote,
                'message' => 'Lote de inventario actualizado correctamente.',
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function lotesProducto(Request $request, $id): JsonResponse
    {
        try {
            $filtros = $request->validate([
                'activo' => ['nullable'],
                'con_stock' => ['nullable'],
            ]);

            $lotes = $this->loteService->listarLotesProducto(
                $request->user(),
                (int) $id,
                $filtros
            );

            return response()->json([
                'success' => true,
                'data' => $lotes,
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function stockLote(Request $request, $id): JsonResponse
    {
        try {
            $stock = $this->loteService->consultarStockPorLote(
                $request->user(),
                (int) $id
            );

            return response()->json([
                'success' => true,
                'data' => $stock,
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Fase 4 - Mermas y ajustes críticos
    |--------------------------------------------------------------------------
    |
    | Inventario NO emite, gestiona ni prepara DTE.
    | No se usan codigo_dte, codigo_sii, folio_dte, xml_dte ni lógica SII.
    |
    */

    public function tiposAjusteCritico(
        Request $request,
        InventarioAjusteCriticoService $ajusteCriticoService
    ): JsonResponse {
        try {
            $tipos = $ajusteCriticoService->listarTiposAjusteCritico($request->user());

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

    public function ajustesCriticos(
        Request $request,
        InventarioAjusteCriticoService $ajusteCriticoService
    ): JsonResponse {
        try {
            $ajustes = $ajusteCriticoService->listarAjustesCriticos(
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

    public function registrarAjusteCritico(
        Request $request,
        InventarioAjusteCriticoService $ajusteCriticoService
    ): JsonResponse {
        try {
            $ajuste = $ajusteCriticoService->registrarAjusteCritico(
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

    public function verAjusteCritico(
        Request $request,
        int $id,
        InventarioAjusteCriticoService $ajusteCriticoService
    ): JsonResponse {
        try {
            $ajuste = $ajusteCriticoService->obtenerAjusteCritico(
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

        /*
    |--------------------------------------------------------------------------
    | Fase 6 - Reservas y disponibilidad comprometida
    |--------------------------------------------------------------------------
    |
    | Inventario NO emite, gestiona ni prepara DTE.
    | Las reservas comprometen disponibilidad, pero NO descuentan stock físico.
    | El stock físico solo se descuenta al consumir una reserva mediante una
    | salida real delegada a InventarioMovimientoService.
    |
    */

public function reservas(Request $request): JsonResponse
{
    try {
        $filtros = $request->validate([
            'estado' => ['nullable', Rule::in(ReservaInventario::estadosPermitidos())],
            'referencia' => ['nullable', 'string', 'max:120'],
            'origen_modulo' => ['nullable', 'string', 'max:80'],
            'origen_id' => ['nullable', 'integer'],
            'producto_id' => ['nullable', 'integer'],
            'bodega_id' => ['nullable', 'integer'],
            'lote_id' => ['nullable', 'integer'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $paginador = $this->reservaService->listarReservas(
            $request->user(),
            $filtros
        );

        return response()->json($this->respuestaPaginada($paginador));
    } catch (ValidationException $e) {
        return $this->respuestaValidacion($e);
    } catch (Exception $e) {
        return $this->respuestaError($e);
    }
}

public function storeReserva(Request $request): JsonResponse
{
    try {
        $datos = $request->validate([
            'codigo_reserva' => ['nullable', 'string', 'max:60'],
            'referencia' => ['nullable', 'string', 'max:120'],
            'motivo' => ['nullable', 'string', 'max:120'],
            'observacion' => ['nullable', 'string', 'max:2000'],
            'origen_modulo' => ['nullable', 'string', 'max:80'],
            'origen_id' => ['nullable', 'integer'],
            'fecha_reserva' => ['nullable', 'date'],
            'fecha_expiracion' => ['nullable', 'date'],

            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['required', 'integer'],
            'detalles.*.bodega_id' => ['required', 'integer'],
            'detalles.*.lote_id' => ['nullable', 'integer'],
            'detalles.*.cantidad' => ['required', 'numeric', 'gt:0'],
        ]);

        $reserva = $this->reservaService->crearReserva(
            $request->user(),
            $datos
        );

        return response()->json([
            'success' => true,
            'data' => $reserva,
            'message' => 'Reserva de inventario creada correctamente.',
        ], 201);
    } catch (ValidationException $e) {
        return $this->respuestaValidacion($e);
    } catch (Exception $e) {
        return $this->respuestaError($e);
    }
}

public function showReserva(Request $request, $id): JsonResponse
{
    try {
        $reserva = $this->reservaService->obtenerReserva(
            $request->user(),
            (int) $id
        );

        return response()->json([
            'success' => true,
            'data' => $reserva,
        ]);
    } catch (ValidationException $e) {
        return $this->respuestaValidacion($e);
    } catch (Exception $e) {
        return $this->respuestaError($e);
    }
}

public function cancelarReserva(Request $request, $id): JsonResponse
{
    try {
        $datos = $request->validate([
            'observacion' => ['nullable', 'string', 'max:2000'],
        ]);

        $reserva = $this->reservaService->cancelarReserva(
            $request->user(),
            (int) $id,
            $datos
        );

        return response()->json([
            'success' => true,
            'data' => $reserva,
            'message' => 'Reserva cancelada correctamente.',
        ]);
    } catch (ValidationException $e) {
        return $this->respuestaValidacion($e);
    } catch (Exception $e) {
        return $this->respuestaError($e);
    }
}

public function liberarReserva(Request $request, $id): JsonResponse
{
    try {
        $datos = $request->validate([
            'observacion' => ['nullable', 'string', 'max:2000'],

            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.detalle_id' => ['required', 'integer'],
            'detalles.*.cantidad' => ['required', 'numeric', 'gt:0'],
        ]);

        $reserva = $this->reservaService->liberarReserva(
            $request->user(),
            (int) $id,
            $datos
        );

        return response()->json([
            'success' => true,
            'data' => $reserva,
            'message' => 'Reserva liberada correctamente.',
        ]);
    } catch (ValidationException $e) {
        return $this->respuestaValidacion($e);
    } catch (Exception $e) {
        return $this->respuestaError($e);
    }
}

public function consumirReserva(Request $request, $id): JsonResponse
{
    try {
        $datos = $request->validate([
            'referencia' => ['nullable', 'string', 'max:120'],
            'motivo' => ['nullable', 'string', 'max:80'],
            'observacion' => ['nullable', 'string', 'max:2000'],
            'fecha_movimiento' => ['nullable', 'date'],

            /*
            | Si detalles no viene, el service consumirá todo lo pendiente.
            | Si viene, consumirá parcialmente los detalles indicados.
            */
            'detalles' => ['nullable', 'array', 'min:1'],
            'detalles.*.detalle_id' => ['required_with:detalles', 'integer'],
            'detalles.*.cantidad' => ['required_with:detalles', 'numeric', 'gt:0'],
        ]);

        $reserva = $this->reservaService->consumirReserva(
            $request->user(),
            (int) $id,
            $datos
        );

        return response()->json([
            'success' => true,
            'data' => $reserva,
            'message' => 'Reserva consumida correctamente. Se generó la salida real de inventario.',
        ]);
    } catch (ValidationException $e) {
        return $this->respuestaValidacion($e);
    } catch (Exception $e) {
        return $this->respuestaError($e);
    }
}

public function disponibilidad(Request $request): JsonResponse
{
    try {
        $filtros = $request->validate([
            'producto_id' => ['nullable', 'integer'],
            'bodega_id' => ['nullable', 'integer'],
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
    } catch (Exception $e) {
        return $this->respuestaError($e);
    }
}

public function disponibilidadProducto(Request $request, $id): JsonResponse
{
    try {
        $filtros = $request->validate([
            'bodega_id' => ['nullable', 'integer'],
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
