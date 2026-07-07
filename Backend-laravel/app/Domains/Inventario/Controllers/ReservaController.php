<?php

namespace App\Domains\Inventario\Controllers;

use App\Domains\Inventario\Exceptions\InventarioException;
use App\Support\MensajeErrorGenerico;

use App\Domains\Inventario\Controllers\Concerns\RespondeInventario;
use App\Domains\Inventario\Models\ReservaInventario;
use App\Domains\Inventario\Models\StockUbicacionInventario;
use App\Domains\Inventario\Services\InventarioReservaService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** La máquina de estados vive en InventarioReservaService; el controller solo valida y delega. */
class ReservaController
{
    use RespondeInventario;

    public function __construct(
        protected InventarioReservaService $reservaService,
    ) {
    }

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
                'ubicacion_id' => ['nullable', 'integer'],
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
        } catch (InventarioException $e) {
            throw $e;
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
                'detalles.*.ubicacion_id' => ['nullable', 'integer'],
                'detalles.*.lote_id' => ['nullable', 'integer'],
                'detalles.*.cantidad' => ['required', 'numeric', 'gt:0'],
                'detalles.*.estado_stock' => ['nullable', Rule::in(StockUbicacionInventario::estadosPermitidos())],
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
        } catch (InventarioException $e) {
            throw $e;
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
        } catch (InventarioException $e) {
            throw $e;
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
        } catch (InventarioException $e) {
            throw $e;
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
        } catch (InventarioException $e) {
            throw $e;
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

                // Si detalles no viene, el service consume todo lo pendiente; si viene, consume parcialmente los indicados.
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
        } catch (InventarioException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }
}
