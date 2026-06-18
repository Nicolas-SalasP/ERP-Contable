<?php

namespace App\Domains\Inventario\Controllers;

use App\Domains\Inventario\Exceptions\InventarioException;

use App\Domains\Inventario\Models\MovimientoInventario;
use App\Domains\Inventario\Models\ReservaInventario;
use App\Domains\Inventario\Models\TomaFisicaInventario;
use App\Domains\Inventario\Services\InventarioReporteService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @tags Inventario - Reportes
 */
class ReporteInventarioController
{
    protected InventarioReporteService $reporteService;

    public function __construct(InventarioReporteService $reporteService)
    {
        $this->reporteService = $reporteService;
    }

    public function reporteStock(Request $request): JsonResponse
    {
        try {
            $filtros = $request->validate([
                'producto_id' => ['nullable', 'integer'],
                'bodega_id' => ['nullable', 'integer'],
                'estado_stock' => ['nullable', Rule::in(['ok', 'sin_stock', 'bajo_minimo'])],
                'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            ]);

            $resultado = $this->reporteService->stock($request->user(), $filtros);

            return response()->json([
                'success' => true,
                'data' => $resultado['data'],
                'resumen' => $resultado['resumen'],
                'metadata' => $resultado['metadata'],
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (InventarioException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function reporteMovimientos(Request $request): JsonResponse
    {
        try {
            $filtros = $request->validate([
                'producto_id' => ['nullable', 'integer'],
                'bodega_id' => ['nullable', 'integer'],
                'ubicacion_id' => ['nullable', 'integer'],
                'tipo' => ['nullable', Rule::in(MovimientoInventario::tiposPermitidos())],
                'desde' => ['nullable', 'date'],
                'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
                'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            ]);

            $resultado = $this->reporteService->movimientos($request->user(), $filtros);

            return response()->json([
                'success' => true,
                'data' => $resultado['data'],
                'resumen' => $resultado['resumen'],
                'metadata' => $resultado['metadata'],
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (InventarioException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function reporteValorizacion(Request $request): JsonResponse
    {
        try {
            $filtros = $request->validate([
                'producto_id' => ['nullable', 'integer'],
                'bodega_id' => ['nullable', 'integer'],
                'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            ]);

            $resultado = $this->reporteService->valorizacion($request->user(), $filtros);

            return response()->json([
                'success' => true,
                'data' => $resultado['data'],
                'resumen' => $resultado['resumen'],
                'metadata' => $resultado['metadata'],
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (InventarioException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function reporteLotes(Request $request): JsonResponse
    {
        try {
            $filtros = $request->validate([
                'producto_id' => ['nullable', 'integer'],
                'bodega_id' => ['nullable', 'integer'],
                'lote_id' => ['nullable', 'integer'],
                'estado_lote' => ['nullable', Rule::in(['vigente', 'por_vencer', 'vencido', 'sin_vencimiento', 'inactivo'])],
                'dias_vencimiento' => ['nullable', 'integer', 'min:0', 'max:365'],
                'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            ]);

            $resultado = $this->reporteService->lotes($request->user(), $filtros);

            return response()->json([
                'success' => true,
                'data' => $resultado['data'],
                'resumen' => $resultado['resumen'],
                'metadata' => $resultado['metadata'],
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (InventarioException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function reporteReservas(Request $request): JsonResponse
    {
        try {
            $filtros = $request->validate([
                'estado' => ['nullable', Rule::in(ReservaInventario::estadosPermitidos())],
                'producto_id' => ['nullable', 'integer'],
                'bodega_id' => ['nullable', 'integer'],
                'desde' => ['nullable', 'date'],
                'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
                'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            ]);

            $resultado = $this->reporteService->reservas($request->user(), $filtros);

            return response()->json([
                'success' => true,
                'data' => $resultado['data'],
                'resumen' => $resultado['resumen'],
                'metadata' => $resultado['metadata'],
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (InventarioException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function reporteTomasFisicas(Request $request): JsonResponse
    {
        try {
            $filtros = $request->validate([
                'estado' => ['nullable', Rule::in(TomaFisicaInventario::estadosPermitidos())],
                'bodega_id' => ['nullable', 'integer'],
                'desde' => ['nullable', 'date'],
                'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
                'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            ]);

            $resultado = $this->reporteService->tomasFisicas($request->user(), $filtros);

            return response()->json([
                'success' => true,
                'data' => $resultado['data'],
                'resumen' => $resultado['resumen'],
                'metadata' => $resultado['metadata'],
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (InventarioException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function reporteAjustes(Request $request): JsonResponse
    {
        try {
            $filtros = $request->validate([
                'producto_id' => ['nullable', 'integer'],
                'bodega_id' => ['nullable', 'integer'],
                'lote_id' => ['nullable', 'integer'],
                'desde' => ['nullable', 'date'],
                'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
                'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            ]);

            $resultado = $this->reporteService->ajustes($request->user(), $filtros);

            return response()->json([
                'success' => true,
                'data' => $resultado['data'],
                'resumen' => $resultado['resumen'],
                'metadata' => $resultado['metadata'],
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (InventarioException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function reporteReposicionAlertas(Request $request): JsonResponse
    {
        try {
            $filtros = $request->validate([
                'tipo' => ['nullable', 'string', 'max:80'],
                'severidad' => ['nullable', Rule::in(['baja', 'media', 'alta', 'critica'])],
                'producto_id' => ['nullable', 'integer'],
                'bodega_id' => ['nullable', 'integer'],
                'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            ]);

            $resultado = $this->reporteService->reposicionAlertas($request->user(), $filtros);

            return response()->json([
                'success' => true,
                'data' => $resultado['data'],
                'resumen' => $resultado['resumen'],
                'metadata' => $resultado['metadata'],
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (InventarioException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function exportarReporteCsv(Request $request, string $tipo): StreamedResponse|JsonResponse
    {
        try {
            $filtros = $request->validate([
                'producto_id' => ['nullable', 'integer'],
                'bodega_id' => ['nullable', 'integer'],
                'lote_id' => ['nullable', 'integer'],
                'ubicacion_id' => ['nullable', 'integer'],
                'tipo' => ['nullable', 'string', 'max:80'],
                'estado' => ['nullable', 'string', 'max:80'],
                'severidad' => ['nullable', Rule::in(['baja', 'media', 'alta', 'critica'])],
                'estado_stock' => ['nullable', Rule::in(['ok', 'sin_stock', 'bajo_minimo'])],
                'estado_lote' => ['nullable', Rule::in(['vigente', 'por_vencer', 'vencido', 'sin_vencimiento', 'inactivo'])],
                'dias_vencimiento' => ['nullable', 'integer', 'min:0', 'max:365'],
                'desde' => ['nullable', 'date'],
                'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            ]);

            $csv = $this->reporteService->exportarCsv($request->user(), $tipo, $filtros);

            return response()->streamDownload(function () use ($csv) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, $csv['headers'], ';');

                foreach ($csv['rows'] as $row) {
                    fputcsv($handle, array_map(static fn ($value) => $value ?? '', $row), ';');
                }

                fclose($handle);
            }, $csv['filename'], [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (InventarioException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
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
