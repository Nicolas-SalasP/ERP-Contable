<?php

namespace App\Domains\Inventario\Controllers;

use App\Domains\Core\Models\User;
use App\Domains\Inventario\Controllers\Concerns\RespondeInventario;
use App\Domains\Inventario\Models\MovimientoInventario;
use App\Domains\Inventario\Models\StockUbicacionInventario;
use App\Domains\Inventario\Services\InventarioMovimientoService;
use App\Domains\Inventario\Services\InventarioPermisoService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Listado y registro de movimientos de inventario. Extraído de InventarioController
 * (H7 sprint 3) sin cambiar contratos. Los validadores de permiso/bodega por tipo de
 * movimiento son exclusivos de este concern y viajan con él; la capa de respuesta se
 * comparte via el trait RespondeInventario.
 */
class MovimientoController
{
    use RespondeInventario;

    public function __construct(
        protected InventarioMovimientoService $movimientoService,
        protected InventarioPermisoService $permisos,
    ) {
    }

    public function movimientos(Request $request): JsonResponse
    {
        try {
            $usuario = $request->user();

            $this->permisos->exigir($usuario, 'inventario.movimientos.ver');

            $filtros = $request->validate([
                'producto_id' => ['nullable', 'integer'],
                'bodega_id' => ['nullable', 'integer'],
                'lote_id' => ['nullable', 'integer'],
                'ubicacion_id' => ['nullable', 'integer'],
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
                'ubicacion_origen_id' => ['nullable', 'integer'],
                'ubicacion_destino_id' => ['nullable', 'integer'],
                'estado_stock_origen' => ['nullable', Rule::in(StockUbicacionInventario::estadosPermitidos())],
                'estado_stock_destino' => ['nullable', Rule::in(StockUbicacionInventario::estadosPermitidos())],
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
}
