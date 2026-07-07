<?php

namespace App\Domains\Inventario\Controllers;

use App\Domains\Inventario\Exceptions\InventarioException;
use App\Support\MensajeErrorGenerico;

use App\Domains\Inventario\Controllers\Concerns\RespondeInventario;
use App\Domains\Inventario\Models\TomaFisicaInventario;
use App\Domains\Inventario\Services\InventarioTomaFisicaService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Todas las transiciones viven en InventarioTomaFisicaService; el controller solo valida y delega. */
class TomaFisicaController
{
    use RespondeInventario;

    public function __construct(
        protected InventarioTomaFisicaService $tomaFisicaService,
    ) {
    }

    public function tomasFisicas(Request $request): JsonResponse
    {
        try {
            $filtros = $request->validate([
                'estado' => ['nullable', Rule::in(TomaFisicaInventario::estadosPermitidos())],
                'tipo' => ['nullable', Rule::in(TomaFisicaInventario::tiposPermitidos())],
                'bodega_id' => ['nullable', 'integer'],
                'referencia' => ['nullable', 'string', 'max:120'],
                'desde' => ['nullable', 'date'],
                'hasta' => ['nullable', 'date'],
                'page' => ['nullable', 'integer', 'min:1'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            $paginador = $this->tomaFisicaService->listar(
                $request->user(),
                $filtros
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

    public function storeTomaFisica(Request $request): JsonResponse
    {
        try {
            $datos = $request->validate([
                'tipo' => ['required', Rule::in(TomaFisicaInventario::tiposPermitidos())],
                'bodega_id' => ['nullable', 'integer'],
                'referencia' => ['nullable', 'string', 'max:120'],
                'motivo' => ['nullable', 'string', 'max:120'],
                'observacion' => ['nullable', 'string', 'max:2000'],
                'origen_modulo' => ['nullable', 'string', 'max:80'],
                'origen_id' => ['nullable', 'integer'],
            ]);

            $toma = $this->tomaFisicaService->crear(
                $request->user(),
                $datos
            );

            return response()->json([
                'success' => true,
                'data' => $toma,
                'message' => 'Toma física creada correctamente. El stock físico no fue modificado.',
            ], 201);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (InventarioException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function showTomaFisica(Request $request, $id): JsonResponse
    {
        try {
            $toma = $this->tomaFisicaService->obtener(
                $request->user(),
                (int) $id
            );

            return response()->json([
                'success' => true,
                'data' => $toma,
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (InventarioException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function iniciarTomaFisica(Request $request, $id): JsonResponse
    {
        try {
            $toma = $this->tomaFisicaService->iniciar(
                $request->user(),
                (int) $id
            );

            return response()->json([
                'success' => true,
                'data' => $toma,
                'message' => 'Toma física iniciada correctamente.',
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (InventarioException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function registrarConteosTomaFisica(Request $request, $id): JsonResponse
    {
        try {
            $datos = $request->validate([
                'detalles' => ['required', 'array', 'min:1'],
                'detalles.*.detalle_id' => ['required', 'integer'],
                'detalles.*.stock_contado' => ['required', 'numeric', 'min:0'],
                'detalles.*.observacion' => ['nullable', 'string', 'max:2000'],
            ]);

            $toma = $this->tomaFisicaService->registrarConteos(
                $request->user(),
                (int) $id,
                $datos
            );

            return response()->json([
                'success' => true,
                'data' => $toma,
                'message' => 'Conteos registrados correctamente. El stock físico no fue modificado.',
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (InventarioException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function cerrarTomaFisica(Request $request, $id): JsonResponse
    {
        try {
            $datos = $request->validate([
                'observacion' => ['nullable', 'string', 'max:2000'],
            ]);

            $toma = $this->tomaFisicaService->cerrar(
                $request->user(),
                (int) $id,
                $datos
            );

            return response()->json([
                'success' => true,
                'data' => $toma,
                'message' => 'Toma física cerrada correctamente. Las diferencias quedaron listas para revisión.',
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (InventarioException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function ajustarTomaFisica(Request $request, $id): JsonResponse
    {
        try {
            $datos = $request->validate([
                'referencia' => ['nullable', 'string', 'max:120'],
                'motivo' => ['nullable', 'string', 'max:120'],
                'observacion' => ['nullable', 'string', 'max:2000'],
                'costo_unitario' => ['nullable', 'numeric', 'gt:0'],
                'costos_unitarios' => ['nullable', 'array'],
                'costos_unitarios.*' => ['nullable', 'numeric', 'gt:0'],
            ]);

            $toma = $this->tomaFisicaService->ajustar(
                $request->user(),
                (int) $id,
                $datos
            );

            return response()->json([
                'success' => true,
                'data' => $toma,
                'message' => 'Toma física ajustada correctamente. Se generaron los movimientos reales correspondientes.',
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (InventarioException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }

    public function cancelarTomaFisica(Request $request, $id): JsonResponse
    {
        try {
            $datos = $request->validate([
                'observacion' => ['nullable', 'string', 'max:2000'],
            ]);

            $toma = $this->tomaFisicaService->cancelar(
                $request->user(),
                (int) $id,
                $datos
            );

            return response()->json([
                'success' => true,
                'data' => $toma,
                'message' => 'Toma física cancelada correctamente. No se modificó el stock físico.',
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (InventarioException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }
}
