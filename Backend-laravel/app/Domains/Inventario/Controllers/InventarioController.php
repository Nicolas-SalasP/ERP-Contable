<?php

namespace App\Domains\Inventario\Controllers;

use App\Domains\Core\Models\User;
use App\Domains\Inventario\Controllers\Concerns\RespondeInventario;
use App\Domains\Inventario\Models\MovimientoInventario;
use App\Domains\Inventario\Models\ReservaInventario;
use App\Domains\Inventario\Services\InventarioAjusteCriticoService;
use App\Domains\Inventario\Services\InventarioDisponibilidadService;
use App\Domains\Inventario\Services\InventarioLoteService;
use App\Domains\Inventario\Services\InventarioMovimientoService;
use App\Domains\Inventario\Services\InventarioPermisoService;
use App\Domains\Inventario\Services\InventarioReposicionService;
use App\Domains\Inventario\Services\InventarioAlertaService;
use App\Domains\Inventario\Services\InventarioDashboardService;
use App\Domains\Inventario\Services\InventarioReporteService;
use App\Domains\Inventario\Services\InventarioReservaService;
use App\Domains\Inventario\Services\InventarioUbicacionService;
use App\Domains\Inventario\Services\InventarioStockUbicacionService;
use App\Domains\Inventario\Services\InventarioService;
use App\Domains\Inventario\Services\InventarioValorizacionService;
use App\Domains\Inventario\Models\TomaFisicaInventario;
use App\Domains\Inventario\Models\InventarioUbicacion;
use App\Domains\Inventario\Models\StockUbicacionInventario;
use App\Domains\Inventario\Services\InventarioTomaFisicaService;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;
class InventarioController
{
    use RespondeInventario;

    protected InventarioService $service;
    protected InventarioMovimientoService $movimientoService;
    protected InventarioPermisoService $permisos;
    protected InventarioValorizacionService $valorizacionService;
    protected InventarioLoteService $loteService;
    protected InventarioReservaService $reservaService;
    protected InventarioDisponibilidadService $disponibilidadService;
    protected InventarioTomaFisicaService $tomaFisicaService;
    protected InventarioReposicionService $reposicionService;
    protected InventarioAlertaService $alertaService;
    protected InventarioDashboardService $dashboardService;
    protected InventarioReporteService $reporteService;
    protected InventarioUbicacionService $ubicacionService;
    protected InventarioStockUbicacionService $stockUbicacionService;

public function __construct(
    InventarioService $service,
    InventarioMovimientoService $movimientoService,
    InventarioPermisoService $permisos,
    InventarioValorizacionService $valorizacionService,
    InventarioLoteService $loteService,
    InventarioReservaService $reservaService,
    InventarioDisponibilidadService $disponibilidadService,
    InventarioTomaFisicaService $tomaFisicaService,
    InventarioReposicionService $reposicionService,
    InventarioAlertaService $alertaService,
    InventarioDashboardService $dashboardService,
    InventarioReporteService $reporteService,
    InventarioUbicacionService $ubicacionService,
    InventarioStockUbicacionService $stockUbicacionService
) {
    $this->service = $service;
    $this->movimientoService = $movimientoService;
    $this->permisos = $permisos;
    $this->valorizacionService = $valorizacionService;
    $this->loteService = $loteService;
    $this->reservaService = $reservaService;
    $this->disponibilidadService = $disponibilidadService;
    $this->tomaFisicaService = $tomaFisicaService;
    $this->reposicionService = $reposicionService;
    $this->alertaService = $alertaService;
    $this->dashboardService = $dashboardService;
    $this->reporteService = $reporteService;
    $this->ubicacionService = $ubicacionService;
    $this->stockUbicacionService = $stockUbicacionService;
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


    public function ubicaciones(Request $request): JsonResponse
    {
        try {
            $filtros = $request->validate([
                'bodega_id' => ['nullable', 'integer'],
                'tipo' => ['nullable', Rule::in(InventarioUbicacion::tiposPermitidos())],
                'activo' => ['nullable'],
                'search' => ['nullable', 'string', 'max:120'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            ]);

            return response()->json($this->respuestaPaginada(
                $this->ubicacionService->listar($request->user(), $filtros)
            ));
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function storeUbicacion(Request $request): JsonResponse
    {
        try {
            $datos = $request->validate([
                'bodega_id' => ['required', 'integer'],
                'ubicacion_padre_id' => ['nullable', 'integer'],
                'codigo' => ['required', 'string', 'max:80'],
                'nombre' => ['required', 'string', 'max:160'],
                'tipo' => ['nullable', Rule::in(InventarioUbicacion::tiposPermitidos())],
                'pasillo' => ['nullable', 'string', 'max:40'],
                'estante' => ['nullable', 'string', 'max:40'],
                'nivel' => ['nullable', 'string', 'max:40'],
                'posicion' => ['nullable', 'string', 'max:40'],
                'capacidad_maxima' => ['nullable', 'numeric', 'min:0'],
                'activo' => ['nullable', 'boolean'],
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->ubicacionService->crear($request->user(), $datos),
                'message' => 'Ubicación de inventario creada correctamente.',
            ], 201);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function showUbicacion(Request $request, $id): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->ubicacionService->obtener($request->user(), (int) $id),
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function updateUbicacion(Request $request, $id): JsonResponse
    {
        try {
            $datos = $request->validate([
                'bodega_id' => ['nullable', 'integer'],
                'ubicacion_padre_id' => ['nullable', 'integer'],
                'codigo' => ['nullable', 'string', 'max:80'],
                'nombre' => ['nullable', 'string', 'max:160'],
                'tipo' => ['nullable', Rule::in(InventarioUbicacion::tiposPermitidos())],
                'pasillo' => ['nullable', 'string', 'max:40'],
                'estante' => ['nullable', 'string', 'max:40'],
                'nivel' => ['nullable', 'string', 'max:40'],
                'posicion' => ['nullable', 'string', 'max:40'],
                'capacidad_maxima' => ['nullable', 'numeric', 'min:0'],
                'activo' => ['nullable', 'boolean'],
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->ubicacionService->actualizar($request->user(), (int) $id, $datos),
                'message' => 'Ubicación de inventario actualizada correctamente.',
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function stockUbicacion(Request $request, $id): JsonResponse
    {
        try {
            $filtros = $request->validate([
                'producto_id' => ['nullable', 'integer'],
                'lote_id' => ['nullable', 'integer'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            return response()->json($this->respuestaPaginada(
                $this->ubicacionService->stock($request->user(), (int) $id, $filtros)
            ));
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function stockUbicaciones(Request $request): JsonResponse
    {
        try {
            $filtros = $request->validate([
                'producto_id' => ['nullable', 'integer'],
                'bodega_id' => ['nullable', 'integer'],
                'ubicacion_id' => ['nullable', 'integer'],
                'lote_id' => ['nullable', 'integer'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            return response()->json($this->respuestaPaginada(
                $this->stockUbicacionService->listar($request->user(), $filtros)
            ));
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function moverStockUbicacion(Request $request): JsonResponse
    {
        try {
            $datos = $request->validate([
                'producto_id' => ['required', 'integer'],
                'bodega_origen_id' => ['required', 'integer'],
                'bodega_destino_id' => ['required', 'integer'],
                'ubicacion_origen_id' => ['required', 'integer'],
                'ubicacion_destino_id' => ['required', 'integer'],
                'lote_id' => ['nullable', 'integer'],
                'cantidad' => ['required', 'numeric', 'gt:0'],
                'estado_stock_origen' => ['nullable', Rule::in(StockUbicacionInventario::estadosPermitidos())],
                'estado_stock_destino' => ['nullable', Rule::in(StockUbicacionInventario::estadosPermitidos())],
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->stockUbicacionService->moverStock($request->user(), $datos),
                'message' => 'Stock movido entre ubicaciones correctamente.',
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function confirmarPutaway(Request $request): JsonResponse
    {
        return $this->moverStockUbicacion($request);
    }


    public function dashboard(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->dashboardService->obtener($request->user()),
            ]);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

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

public function disponibilidad(Request $request): JsonResponse
{
    try {
        $filtros = $request->validate([
            'producto_id' => ['nullable', 'integer'],
            'bodega_id' => ['nullable', 'integer'],
            'ubicacion_id' => ['nullable', 'integer'],
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
            'ubicacion_id' => ['nullable', 'integer'],
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

    public function reglasReposicion(Request $request): JsonResponse
    {
        try {
            $filtros = $request->validate([
                'producto_id' => ['nullable', 'integer'],
                'bodega_id' => ['nullable', 'integer'],
                'activo' => ['nullable'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            return response()->json($this->respuestaPaginada(
                $this->reposicionService->listar($request->user(), $filtros)
            ));
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function storeReglaReposicion(Request $request): JsonResponse
    {
        try {
            $datos = $request->validate([
                'producto_id' => ['required', 'integer'],
                'bodega_id' => ['nullable', 'integer'],
                'stock_minimo' => ['required', 'numeric', 'min:0'],
                'stock_objetivo' => ['required', 'numeric', 'min:0'],
                'punto_reorden' => ['nullable', 'numeric', 'min:0'],
                'dias_alerta_vencimiento' => ['nullable', 'integer', 'min:0', 'max:3650'],
                'activo' => ['nullable', 'boolean'],
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->reposicionService->crear($request->user(), $datos),
                'message' => 'Regla de reposición creada correctamente.',
            ], 201);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function showReglaReposicion(Request $request, $id): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->reposicionService->obtener($request->user(), (int) $id),
            ]);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function updateReglaReposicion(Request $request, $id): JsonResponse
    {
        try {
            $datos = $request->validate([
                'producto_id' => ['required', 'integer'],
                'bodega_id' => ['nullable', 'integer'],
                'stock_minimo' => ['required', 'numeric', 'min:0'],
                'stock_objetivo' => ['required', 'numeric', 'min:0'],
                'punto_reorden' => ['nullable', 'numeric', 'min:0'],
                'dias_alerta_vencimiento' => ['nullable', 'integer', 'min:0', 'max:3650'],
                'activo' => ['nullable', 'boolean'],
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->reposicionService->actualizar($request->user(), (int) $id, $datos),
                'message' => 'Regla de reposición actualizada correctamente.',
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function destroyReglaReposicion(Request $request, $id): JsonResponse
    {
        try {
            $this->reposicionService->eliminar($request->user(), (int) $id);

            return response()->json([
                'success' => true,
                'message' => 'Regla de reposición eliminada correctamente.',
            ]);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function alertas(Request $request): JsonResponse
    {
        try {
            $filtros = $request->validate([
                'tipo' => ['nullable', 'string', 'max:80'],
                'severidad' => ['nullable', 'in:baja,media,alta,critica'],
                'producto_id' => ['nullable', 'integer'],
                'bodega_id' => ['nullable', 'integer'],
                'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            ]);

            $resultado = $this->alertaService->listar($request->user(), $filtros);

            return response()->json([
                'success' => true,
                'data' => $resultado['data'],
                'resumen' => $resultado['resumen'],
                'metadata' => $resultado['metadata'],
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function sugerenciasReposicion(Request $request): JsonResponse
    {
        try {
            $filtros = $request->validate([
                'producto_id' => ['nullable', 'integer'],
                'bodega_id' => ['nullable', 'integer'],
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->reposicionService->sugerencias($request->user(), $filtros),
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (Exception $e) {
            return $this->respuestaError($e);
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
