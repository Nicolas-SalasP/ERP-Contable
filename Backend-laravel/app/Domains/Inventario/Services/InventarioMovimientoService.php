<?php

namespace App\Domains\Inventario\Services;

use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\MovimientoInventario;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\StockProducto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventarioMovimientoService
{
    public function __construct(
        private readonly InventarioValorizacionService $valorizacionService,
        private readonly InventarioLoteService $loteService
    ) {
    }

    public function registrarMovimiento(array $data, int $empresaId, ?int $userId = null): MovimientoInventario
    {
        return DB::transaction(function () use ($data, $empresaId, $userId) {
            $tipo = $data['tipo'] ?? null;

            return match ($tipo) {
                MovimientoInventario::TIPO_ENTRADA => $this->registrarEntrada($data, $empresaId, $userId),
                MovimientoInventario::TIPO_SALIDA => $this->registrarSalida($data, $empresaId, $userId),
                MovimientoInventario::TIPO_TRASPASO => $this->registrarTraspaso($data, $empresaId, $userId),
                MovimientoInventario::TIPO_AJUSTE_POSITIVO => $this->registrarAjustePositivo($data, $empresaId, $userId),
                MovimientoInventario::TIPO_AJUSTE_NEGATIVO => $this->registrarAjusteNegativo($data, $empresaId, $userId),
                default => throw ValidationException::withMessages([
                    'tipo' => 'El tipo de movimiento no es válido.',
                ]),
            };
        });
    }

    private function registrarEntrada(array $data, int $empresaId, ?int $userId): MovimientoInventario
    {
        $producto = $this->obtenerProductoActivoEmpresa((int) $data['producto_id'], $empresaId);
        $bodegaDestino = $this->obtenerBodegaActivaEmpresa((int) $data['bodega_destino_id'], $empresaId);
        $cantidad = $this->validarCantidadPositiva($data['cantidad']);
        $costoUnitarioEntrada = $this->normalizarCostoUnitarioEntrada($data);

        $lote = $this->loteService->resolverLoteParaEntrada($producto, $data, $empresaId);

        $stockDestino = $this->obtenerOCrearStockBloqueado($producto->id, $bodegaDestino->id, $empresaId);

        $valorizacion = $this->valorizacionService->calcularEntradaPmp(
            stock: $stockDestino,
            producto: $producto,
            cantidad: $cantidad,
            costoUnitario: $costoUnitarioEntrada
        );

        $movimiento = MovimientoInventario::create(array_merge([
            'empresa_id' => $empresaId,
            'producto_id' => $producto->id,
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'bodega_origen_id' => null,
            'bodega_destino_id' => $bodegaDestino->id,
            'cantidad' => $cantidad,
            'stock_origen_antes' => null,
            'stock_origen_despues' => null,
            'stock_destino_antes' => $valorizacion['stock_antes'],
            'stock_destino_despues' => $valorizacion['stock_despues'],
        ], $this->datosComplementariosMovimiento(
            $data,
            (float) $valorizacion['costo_unitario'],
            (float) $valorizacion['costo_total'],
            $userId
        )));

        $this->loteService->aplicarEntradaLote(
            movimiento: $movimiento,
            producto: $producto,
            bodegaDestinoId: (int) $bodegaDestino->id,
            cantidad: $cantidad,
            lote: $lote,
            empresaId: $empresaId
        );

        return $this->cargarRelacionesMovimiento($movimiento);
    }

    private function registrarSalida(array $data, int $empresaId, ?int $userId): MovimientoInventario
    {
        $producto = $this->obtenerProductoActivoEmpresa((int) $data['producto_id'], $empresaId);
        $bodegaOrigen = $this->obtenerBodegaActivaEmpresa((int) $data['bodega_origen_id'], $empresaId);
        $cantidad = $this->validarCantidadPositiva($data['cantidad']);

        $lote = $this->loteService->resolverLoteParaSalida($producto, $data, $empresaId);

        $stockOrigen = $this->obtenerOCrearStockBloqueado($producto->id, $bodegaOrigen->id, $empresaId);
        $stockAntes = $this->toFloat($stockOrigen->stock_actual);

        if ($stockAntes < $cantidad) {
            throw ValidationException::withMessages([
                'cantidad' => 'Stock insuficiente para realizar la salida.',
            ]);
        }

        $valorizacion = $this->valorizacionService->calcularSalidaPmp(
            stock: $stockOrigen,
            producto: $producto,
            cantidad: $cantidad
        );

        $movimiento = MovimientoInventario::create(array_merge([
            'empresa_id' => $empresaId,
            'producto_id' => $producto->id,
            'tipo' => MovimientoInventario::TIPO_SALIDA,
            'bodega_origen_id' => $bodegaOrigen->id,
            'bodega_destino_id' => null,
            'cantidad' => $cantidad,
            'stock_origen_antes' => $valorizacion['stock_antes'],
            'stock_origen_despues' => $valorizacion['stock_despues'],
            'stock_destino_antes' => null,
            'stock_destino_despues' => null,
        ], $this->datosComplementariosMovimiento(
            $data,
            (float) $valorizacion['costo_unitario'],
            (float) $valorizacion['costo_total'],
            $userId
        )));

        $this->loteService->aplicarSalidaLote(
            movimiento: $movimiento,
            producto: $producto,
            bodegaOrigenId: (int) $bodegaOrigen->id,
            cantidad: $cantidad,
            lote: $lote,
            empresaId: $empresaId
        );

        return $this->cargarRelacionesMovimiento($movimiento);
    }

    private function registrarTraspaso(array $data, int $empresaId, ?int $userId): MovimientoInventario
    {
        $producto = $this->obtenerProductoActivoEmpresa((int) $data['producto_id'], $empresaId);
        $bodegaOrigen = $this->obtenerBodegaActivaEmpresa((int) $data['bodega_origen_id'], $empresaId);
        $bodegaDestino = $this->obtenerBodegaActivaEmpresa((int) $data['bodega_destino_id'], $empresaId);

        if ($bodegaOrigen->id === $bodegaDestino->id) {
            throw ValidationException::withMessages([
                'bodega_destino_id' => 'La bodega destino debe ser distinta a la bodega origen.',
            ]);
        }

        $cantidad = $this->validarCantidadPositiva($data['cantidad']);

        $lote = $this->loteService->resolverLoteParaTraspaso($producto, $data, $empresaId);

        $stocks = $this->obtenerStocksTraspasoBloqueados(
            $producto->id,
            $bodegaOrigen->id,
            $bodegaDestino->id,
            $empresaId
        );

        $stockOrigen = $stocks['origen'];
        $stockDestino = $stocks['destino'];
        $stockOrigenAntes = $this->toFloat($stockOrigen->stock_actual);

        if ($stockOrigenAntes < $cantidad) {
            throw ValidationException::withMessages([
                'cantidad' => 'Stock insuficiente para realizar el traspaso.',
            ]);
        }

        $valorizacion = $this->valorizacionService->calcularTraspasoPmp(
            stockOrigen: $stockOrigen,
            stockDestino: $stockDestino,
            producto: $producto,
            cantidad: $cantidad
        );

        $movimiento = MovimientoInventario::create(array_merge([
            'empresa_id' => $empresaId,
            'producto_id' => $producto->id,
            'tipo' => MovimientoInventario::TIPO_TRASPASO,
            'bodega_origen_id' => $bodegaOrigen->id,
            'bodega_destino_id' => $bodegaDestino->id,
            'cantidad' => $cantidad,
            'stock_origen_antes' => $valorizacion['origen']['stock_antes'],
            'stock_origen_despues' => $valorizacion['origen']['stock_despues'],
            'stock_destino_antes' => $valorizacion['destino']['stock_antes'],
            'stock_destino_despues' => $valorizacion['destino']['stock_despues'],
        ], $this->datosComplementariosMovimiento(
            $data,
            (float) $valorizacion['costo_unitario'],
            (float) $valorizacion['costo_total'],
            $userId
        )));

        $this->loteService->aplicarTraspasoLote(
            movimiento: $movimiento,
            producto: $producto,
            bodegaOrigenId: (int) $bodegaOrigen->id,
            bodegaDestinoId: (int) $bodegaDestino->id,
            cantidad: $cantidad,
            lote: $lote,
            empresaId: $empresaId
        );

        return $this->cargarRelacionesMovimiento($movimiento);
    }

    private function registrarAjustePositivo(array $data, int $empresaId, ?int $userId): MovimientoInventario
    {
        $producto = $this->obtenerProductoActivoEmpresa((int) $data['producto_id'], $empresaId);
        $bodegaDestino = $this->obtenerBodegaActivaEmpresa((int) $data['bodega_destino_id'], $empresaId);
        $cantidad = $this->validarCantidadPositiva($data['cantidad']);
        $costoUnitarioEntrada = $this->normalizarCostoUnitarioEntrada($data);

        $lote = $this->loteService->resolverLoteParaEntrada($producto, $data, $empresaId);

        $stockDestino = $this->obtenerOCrearStockBloqueado($producto->id, $bodegaDestino->id, $empresaId);

        $valorizacion = $this->valorizacionService->calcularEntradaPmp(
            stock: $stockDestino,
            producto: $producto,
            cantidad: $cantidad,
            costoUnitario: $costoUnitarioEntrada
        );

        $movimiento = MovimientoInventario::create(array_merge([
            'empresa_id' => $empresaId,
            'producto_id' => $producto->id,
            'tipo' => MovimientoInventario::TIPO_AJUSTE_POSITIVO,
            'bodega_origen_id' => null,
            'bodega_destino_id' => $bodegaDestino->id,
            'cantidad' => $cantidad,
            'stock_origen_antes' => null,
            'stock_origen_despues' => null,
            'stock_destino_antes' => $valorizacion['stock_antes'],
            'stock_destino_despues' => $valorizacion['stock_despues'],
        ], $this->datosComplementariosMovimiento(
            $data,
            (float) $valorizacion['costo_unitario'],
            (float) $valorizacion['costo_total'],
            $userId
        )));

        $this->loteService->aplicarEntradaLote(
            movimiento: $movimiento,
            producto: $producto,
            bodegaDestinoId: (int) $bodegaDestino->id,
            cantidad: $cantidad,
            lote: $lote,
            empresaId: $empresaId
        );

        return $this->cargarRelacionesMovimiento($movimiento);
    }

    private function registrarAjusteNegativo(array $data, int $empresaId, ?int $userId): MovimientoInventario
    {
        $producto = $this->obtenerProductoActivoEmpresa((int) $data['producto_id'], $empresaId);
        $bodegaOrigen = $this->obtenerBodegaActivaEmpresa((int) $data['bodega_origen_id'], $empresaId);
        $cantidad = $this->validarCantidadPositiva($data['cantidad']);

        $lote = $this->loteService->resolverLoteParaSalida($producto, $data, $empresaId);

        $stockOrigen = $this->obtenerOCrearStockBloqueado($producto->id, $bodegaOrigen->id, $empresaId);
        $stockAntes = $this->toFloat($stockOrigen->stock_actual);

        if ($stockAntes < $cantidad) {
            throw ValidationException::withMessages([
                'cantidad' => 'Stock insuficiente para realizar el ajuste negativo.',
            ]);
        }

        $valorizacion = $this->valorizacionService->calcularSalidaPmp(
            stock: $stockOrigen,
            producto: $producto,
            cantidad: $cantidad
        );

        $movimiento = MovimientoInventario::create(array_merge([
            'empresa_id' => $empresaId,
            'producto_id' => $producto->id,
            'tipo' => MovimientoInventario::TIPO_AJUSTE_NEGATIVO,
            'bodega_origen_id' => $bodegaOrigen->id,
            'bodega_destino_id' => null,
            'cantidad' => $cantidad,
            'stock_origen_antes' => $valorizacion['stock_antes'],
            'stock_origen_despues' => $valorizacion['stock_despues'],
            'stock_destino_antes' => null,
            'stock_destino_despues' => null,
        ], $this->datosComplementariosMovimiento(
            $data,
            (float) $valorizacion['costo_unitario'],
            (float) $valorizacion['costo_total'],
            $userId
        )));

        $this->loteService->aplicarSalidaLote(
            movimiento: $movimiento,
            producto: $producto,
            bodegaOrigenId: (int) $bodegaOrigen->id,
            cantidad: $cantidad,
            lote: $lote,
            empresaId: $empresaId
        );

        return $this->cargarRelacionesMovimiento($movimiento);
    }

    public function listarMovimientos(array $filtros, int $empresaId): LengthAwarePaginator
    {
        $perPage = $this->normalizarPerPage($filtros['per_page'] ?? 15);

        return MovimientoInventario::query()
            ->with([
                'producto:id,empresa_id,sku,nombre,activo,maneja_lotes,requiere_fecha_vencimiento',
                'bodegaOrigen:id,empresa_id,codigo,nombre,estado',
                'bodegaDestino:id,empresa_id,codigo,nombre,estado',
                'lotes.lote:id,empresa_id,producto_id,codigo_lote,fecha_fabricacion,fecha_vencimiento,activo',
                'lotes.bodegaOrigen:id,empresa_id,codigo,nombre,estado',
                'lotes.bodegaDestino:id,empresa_id,codigo,nombre,estado',
            ])
            ->empresa($empresaId)
            ->when(!empty($filtros['producto_id']), function ($query) use ($filtros) {
                $query->producto((int) $filtros['producto_id']);
            })
            ->when(!empty($filtros['tipo']), function ($query) use ($filtros) {
                $query->tipo($filtros['tipo']);
            })
            ->when(!empty($filtros['bodega_id']), function ($query) use ($filtros) {
                $query->bodega((int) $filtros['bodega_id']);
            })
            ->when(!empty($filtros['lote_id']), function ($query) use ($filtros) {
                $query->whereHas('lotes', function ($loteQuery) use ($filtros) {
                    $loteQuery->where('lote_id', (int) $filtros['lote_id']);
                });
            })
            ->when(!empty($filtros['desde']), function ($query) use ($filtros) {
                $query->desde($filtros['desde']);
            })
            ->when(!empty($filtros['hasta']), function ($query) use ($filtros) {
                $query->hasta($filtros['hasta']);
            })
            ->masRecientes()
            ->paginate($perPage);
    }

    public function kardexProducto(int $productoId, array $filtros, int $empresaId): LengthAwarePaginator
    {
        $this->obtenerProductoEmpresa($productoId, $empresaId);

        $perPage = $this->normalizarPerPage($filtros['per_page'] ?? 15);

        return MovimientoInventario::query()
            ->with([
                'producto:id,empresa_id,sku,nombre,activo,maneja_lotes,requiere_fecha_vencimiento',
                'bodegaOrigen:id,empresa_id,codigo,nombre,estado',
                'bodegaDestino:id,empresa_id,codigo,nombre,estado',
                'lotes.lote:id,empresa_id,producto_id,codigo_lote,fecha_fabricacion,fecha_vencimiento,activo',
                'lotes.bodegaOrigen:id,empresa_id,codigo,nombre,estado',
                'lotes.bodegaDestino:id,empresa_id,codigo,nombre,estado',
            ])
            ->empresa($empresaId)
            ->producto($productoId)
            ->when(!empty($filtros['bodega_id']), function ($query) use ($filtros) {
                $query->bodega((int) $filtros['bodega_id']);
            })
            ->when(!empty($filtros['lote_id']), function ($query) use ($filtros) {
                $query->whereHas('lotes', function ($loteQuery) use ($filtros) {
                    $loteQuery->where('lote_id', (int) $filtros['lote_id']);
                });
            })
            ->when(!empty($filtros['tipo']), function ($query) use ($filtros) {
                $query->tipo($filtros['tipo']);
            })
            ->when(!empty($filtros['desde']), function ($query) use ($filtros) {
                $query->desde($filtros['desde']);
            })
            ->when(!empty($filtros['hasta']), function ($query) use ($filtros) {
                $query->hasta($filtros['hasta']);
            })
            ->ordenKardex()
            ->paginate($perPage);
    }

    public function kardexGeneral(array $filtros, int $empresaId): LengthAwarePaginator
    {
        $perPage = $this->normalizarPerPage($filtros['per_page'] ?? 15);

        return MovimientoInventario::query()
            ->with([
                'producto:id,empresa_id,sku,nombre,activo,maneja_lotes,requiere_fecha_vencimiento',
                'bodegaOrigen:id,empresa_id,codigo,nombre,estado',
                'bodegaDestino:id,empresa_id,codigo,nombre,estado',
                'lotes.lote:id,empresa_id,producto_id,codigo_lote,fecha_fabricacion,fecha_vencimiento,activo',
                'lotes.bodegaOrigen:id,empresa_id,codigo,nombre,estado',
                'lotes.bodegaDestino:id,empresa_id,codigo,nombre,estado',
            ])
            ->empresa($empresaId)
            ->when(!empty($filtros['producto_id']), function ($query) use ($filtros) {
                $query->producto((int) $filtros['producto_id']);
            })
            ->when(!empty($filtros['bodega_id']), function ($query) use ($filtros) {
                $query->bodega((int) $filtros['bodega_id']);
            })
            ->when(!empty($filtros['lote_id']), function ($query) use ($filtros) {
                $query->whereHas('lotes', function ($loteQuery) use ($filtros) {
                    $loteQuery->where('lote_id', (int) $filtros['lote_id']);
                });
            })
            ->when(!empty($filtros['tipo']), function ($query) use ($filtros) {
                $query->tipo($filtros['tipo']);
            })
            ->when(!empty($filtros['desde']), function ($query) use ($filtros) {
                $query->desde($filtros['desde']);
            })
            ->when(!empty($filtros['hasta']), function ($query) use ($filtros) {
                $query->hasta($filtros['hasta']);
            })
            ->ordenKardex()
            ->paginate($perPage);
    }

    private function obtenerProductoEmpresa(int $productoId, int $empresaId): Producto
    {
        $producto = Producto::query()
            ->where('id', $productoId)
            ->where('empresa_id', $empresaId)
            ->first();

        if (!$producto) {
            throw ValidationException::withMessages([
                'producto_id' => 'El producto no existe o no pertenece a la empresa.',
            ]);
        }

        return $producto;
    }

    private function obtenerProductoActivoEmpresa(int $productoId, int $empresaId): Producto
    {
        $producto = $this->obtenerProductoEmpresa($productoId, $empresaId);

        if (!$producto->activo) {
            throw ValidationException::withMessages([
                'producto_id' => 'El producto está inactivo.',
            ]);
        }

        return $producto;
    }

    private function obtenerBodegaEmpresa(int $bodegaId, int $empresaId): Bodega
    {
        $bodega = Bodega::query()
            ->where('id', $bodegaId)
            ->where('empresa_id', $empresaId)
            ->first();

        if (!$bodega) {
            throw ValidationException::withMessages([
                'bodega_id' => 'La bodega no existe o no pertenece a la empresa.',
            ]);
        }

        return $bodega;
    }

    private function obtenerBodegaActivaEmpresa(int $bodegaId, int $empresaId): Bodega
    {
        $bodega = $this->obtenerBodegaEmpresa($bodegaId, $empresaId);

        if ($bodega->estado !== 'ACTIVA') {
            throw ValidationException::withMessages([
                'bodega_id' => 'La bodega está inactiva.',
            ]);
        }

        return $bodega;
    }

    private function obtenerOCrearStockBloqueado(int $productoId, int $bodegaId, int $empresaId): StockProducto
    {
        $stock = StockProducto::query()
            ->where('empresa_id', $empresaId)
            ->where('producto_id', $productoId)
            ->where('bodega_id', $bodegaId)
            ->lockForUpdate()
            ->first();

        if ($stock) {
            return $stock;
        }

        try {
            StockProducto::create([
                'empresa_id' => $empresaId,
                'producto_id' => $productoId,
                'bodega_id' => $bodegaId,
                'stock_actual' => 0,
                'costo_promedio' => 0,
                'valor_total' => 0,
            ]);
        } catch (QueryException) {
            // Si otro proceso creó el stock entre el SELECT y el INSERT,
            // volvemos a consultarlo bloqueado dentro de la misma transacción.
        }

        return StockProducto::query()
            ->where('empresa_id', $empresaId)
            ->where('producto_id', $productoId)
            ->where('bodega_id', $bodegaId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function obtenerStocksTraspasoBloqueados(
        int $productoId,
        int $bodegaOrigenId,
        int $bodegaDestinoId,
        int $empresaId
    ): array {
        collect([$bodegaOrigenId, $bodegaDestinoId])
            ->sort()
            ->values()
            ->each(function ($bodegaId) use ($productoId, $empresaId) {
                $this->obtenerOCrearStockBloqueado($productoId, (int) $bodegaId, $empresaId);
            });

        $stockOrigen = StockProducto::query()
            ->where('empresa_id', $empresaId)
            ->where('producto_id', $productoId)
            ->where('bodega_id', $bodegaOrigenId)
            ->lockForUpdate()
            ->firstOrFail();

        $stockDestino = StockProducto::query()
            ->where('empresa_id', $empresaId)
            ->where('producto_id', $productoId)
            ->where('bodega_id', $bodegaDestinoId)
            ->lockForUpdate()
            ->firstOrFail();

        return [
            'origen' => $stockOrigen,
            'destino' => $stockDestino,
        ];
    }

    private function validarCantidadPositiva(mixed $cantidad): float
    {
        if (!is_numeric($cantidad)) {
            throw ValidationException::withMessages([
                'cantidad' => 'La cantidad debe ser numérica.',
            ]);
        }

        $cantidad = $this->redondearCantidad((float) $cantidad);

        if ($cantidad <= 0) {
            throw ValidationException::withMessages([
                'cantidad' => 'La cantidad debe ser mayor a cero.',
            ]);
        }

        return $cantidad;
    }

    private function normalizarCostoUnitarioEntrada(array $data): ?float
    {
        return $this->normalizarDecimalNullable(
            $data['costo_unitario'] ?? null,
            'costo_unitario',
            'El costo unitario debe ser numérico.',
            'El costo unitario no puede ser negativo.'
        );
    }

    private function datosComplementariosMovimiento(
        array $data,
        float $costoUnitario,
        float $costoTotal,
        ?int $userId
    ): array {
        return [
            'costo_unitario' => $this->redondearCantidad($costoUnitario),
            'costo_total' => $this->redondearCantidad($costoTotal),
            'referencia' => $data['referencia'] ?? null,
            'motivo' => $data['motivo'] ?? null,
            'observacion' => $data['observacion'] ?? null,
            'created_by' => $userId,
            'fecha_movimiento' => $data['fecha_movimiento'] ?? now(),
        ];
    }

    private function normalizarDecimalNullable(
        mixed $valor,
        string $campo,
        string $mensajeNoNumerico,
        string $mensajeNegativo
    ): ?float {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (!is_numeric($valor)) {
            throw ValidationException::withMessages([
                $campo => $mensajeNoNumerico,
            ]);
        }

        $valor = $this->redondearCantidad((float) $valor);

        if ($valor < 0) {
            throw ValidationException::withMessages([
                $campo => $mensajeNegativo,
            ]);
        }

        return $valor;
    }

    private function cargarRelacionesMovimiento(MovimientoInventario $movimiento): MovimientoInventario
    {
        return $movimiento->load([
            'producto:id,empresa_id,sku,nombre,activo,maneja_lotes,requiere_fecha_vencimiento',
            'bodegaOrigen:id,empresa_id,codigo,nombre,estado',
            'bodegaDestino:id,empresa_id,codigo,nombre,estado',
            'lotes.lote:id,empresa_id,producto_id,codigo_lote,fecha_fabricacion,fecha_vencimiento,activo',
            'lotes.bodegaOrigen:id,empresa_id,codigo,nombre,estado',
            'lotes.bodegaDestino:id,empresa_id,codigo,nombre,estado',
        ]);
    }

    private function normalizarPerPage(mixed $perPage): int
    {
        $perPage = (int) $perPage;

        if ($perPage <= 0) {
            return 15;
        }

        return min($perPage, 100);
    }

    private function toFloat(mixed $value): float
    {
        return $this->redondearCantidad((float) $value);
    }

    private function redondearCantidad(float $value): float
    {
        return round($value, 4);
    }
}