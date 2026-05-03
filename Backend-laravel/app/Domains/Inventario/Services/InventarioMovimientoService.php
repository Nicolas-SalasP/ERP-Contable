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

        $stockDestino = $this->obtenerOCrearStockBloqueado($producto->id, $bodegaDestino->id, $empresaId);

        $stockAntes = $this->toFloat($stockDestino->stock_actual);
        $valorAntes = $this->toFloat($stockDestino->valor_total);

        $costoUnitario = $this->obtenerCostoUnitarioEntrada($data, $stockDestino, $producto);
        $costoTotal = $this->redondearCantidad($cantidad * $costoUnitario);

        $stockDespues = $this->redondearCantidad($stockAntes + $cantidad);
        $valorDespues = $this->redondearCantidad($valorAntes + $costoTotal);
        $costoPromedioDespues = $this->calcularCostoPromedio($stockDespues, $valorDespues);

        $stockDestino->update([
            'stock_actual' => $stockDespues,
            'costo_promedio' => $costoPromedioDespues,
            'valor_total' => $valorDespues,
        ]);

        return MovimientoInventario::create(array_merge([
            'empresa_id' => $empresaId,
            'producto_id' => $producto->id,
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'bodega_origen_id' => null,
            'bodega_destino_id' => $bodegaDestino->id,
            'cantidad' => $cantidad,
            'stock_origen_antes' => null,
            'stock_origen_despues' => null,
            'stock_destino_antes' => $stockAntes,
            'stock_destino_despues' => $stockDespues,
        ], $this->datosComplementariosMovimiento($data, $costoUnitario, $costoTotal, $userId)));
    }

    private function registrarSalida(array $data, int $empresaId, ?int $userId): MovimientoInventario
    {
        $producto = $this->obtenerProductoActivoEmpresa((int) $data['producto_id'], $empresaId);
        $bodegaOrigen = $this->obtenerBodegaActivaEmpresa((int) $data['bodega_origen_id'], $empresaId);
        $cantidad = $this->validarCantidadPositiva($data['cantidad']);

        $stockOrigen = $this->obtenerOCrearStockBloqueado($producto->id, $bodegaOrigen->id, $empresaId);

        $stockAntes = $this->toFloat($stockOrigen->stock_actual);

        if ($stockAntes < $cantidad) {
            throw ValidationException::withMessages([
                'cantidad' => 'Stock insuficiente para realizar la salida.',
            ]);
        }

        $valorAntes = $this->toFloat($stockOrigen->valor_total);
        $costoUnitario = $this->obtenerCostoUnitarioSalida($stockOrigen, $producto);
        $costoTotal = $this->redondearCantidad($cantidad * $costoUnitario);

        $stockDespues = $this->redondearCantidad($stockAntes - $cantidad);
        $valorDespues = $this->calcularValorDespuesSalida($stockDespues, $valorAntes, $costoTotal);
        $costoPromedioDespues = $this->calcularCostoPromedio($stockDespues, $valorDespues);

        $stockOrigen->update([
            'stock_actual' => $stockDespues,
            'costo_promedio' => $costoPromedioDespues,
            'valor_total' => $valorDespues,
        ]);

        return MovimientoInventario::create(array_merge([
            'empresa_id' => $empresaId,
            'producto_id' => $producto->id,
            'tipo' => MovimientoInventario::TIPO_SALIDA,
            'bodega_origen_id' => $bodegaOrigen->id,
            'bodega_destino_id' => null,
            'cantidad' => $cantidad,
            'stock_origen_antes' => $stockAntes,
            'stock_origen_despues' => $stockDespues,
            'stock_destino_antes' => null,
            'stock_destino_despues' => null,
        ], $this->datosComplementariosMovimiento($data, $costoUnitario, $costoTotal, $userId)));
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

        $stockDestinoAntes = $this->toFloat($stockDestino->stock_actual);

        $valorOrigenAntes = $this->toFloat($stockOrigen->valor_total);
        $valorDestinoAntes = $this->toFloat($stockDestino->valor_total);

        $costoUnitario = $this->obtenerCostoUnitarioSalida($stockOrigen, $producto);
        $costoTotal = $this->redondearCantidad($cantidad * $costoUnitario);

        $stockOrigenDespues = $this->redondearCantidad($stockOrigenAntes - $cantidad);
        $valorOrigenDespues = $this->calcularValorDespuesSalida($stockOrigenDespues, $valorOrigenAntes, $costoTotal);
        $costoPromedioOrigenDespues = $this->calcularCostoPromedio($stockOrigenDespues, $valorOrigenDespues);

        $stockDestinoDespues = $this->redondearCantidad($stockDestinoAntes + $cantidad);
        $valorDestinoDespues = $this->redondearCantidad($valorDestinoAntes + $costoTotal);
        $costoPromedioDestinoDespues = $this->calcularCostoPromedio($stockDestinoDespues, $valorDestinoDespues);

        $stockOrigen->update([
            'stock_actual' => $stockOrigenDespues,
            'costo_promedio' => $costoPromedioOrigenDespues,
            'valor_total' => $valorOrigenDespues,
        ]);

        $stockDestino->update([
            'stock_actual' => $stockDestinoDespues,
            'costo_promedio' => $costoPromedioDestinoDespues,
            'valor_total' => $valorDestinoDespues,
        ]);

        return MovimientoInventario::create(array_merge([
            'empresa_id' => $empresaId,
            'producto_id' => $producto->id,
            'tipo' => MovimientoInventario::TIPO_TRASPASO,
            'bodega_origen_id' => $bodegaOrigen->id,
            'bodega_destino_id' => $bodegaDestino->id,
            'cantidad' => $cantidad,
            'stock_origen_antes' => $stockOrigenAntes,
            'stock_origen_despues' => $stockOrigenDespues,
            'stock_destino_antes' => $stockDestinoAntes,
            'stock_destino_despues' => $stockDestinoDespues,
        ], $this->datosComplementariosMovimiento($data, $costoUnitario, $costoTotal, $userId)));
    }

    private function registrarAjustePositivo(array $data, int $empresaId, ?int $userId): MovimientoInventario
    {
        $producto = $this->obtenerProductoActivoEmpresa((int) $data['producto_id'], $empresaId);
        $bodegaDestino = $this->obtenerBodegaActivaEmpresa((int) $data['bodega_destino_id'], $empresaId);
        $cantidad = $this->validarCantidadPositiva($data['cantidad']);

        $stockDestino = $this->obtenerOCrearStockBloqueado($producto->id, $bodegaDestino->id, $empresaId);

        $stockAntes = $this->toFloat($stockDestino->stock_actual);
        $valorAntes = $this->toFloat($stockDestino->valor_total);

        $costoUnitario = $this->obtenerCostoUnitarioEntrada($data, $stockDestino, $producto);
        $costoTotal = $this->redondearCantidad($cantidad * $costoUnitario);

        $stockDespues = $this->redondearCantidad($stockAntes + $cantidad);
        $valorDespues = $this->redondearCantidad($valorAntes + $costoTotal);
        $costoPromedioDespues = $this->calcularCostoPromedio($stockDespues, $valorDespues);

        $stockDestino->update([
            'stock_actual' => $stockDespues,
            'costo_promedio' => $costoPromedioDespues,
            'valor_total' => $valorDespues,
        ]);

        return MovimientoInventario::create(array_merge([
            'empresa_id' => $empresaId,
            'producto_id' => $producto->id,
            'tipo' => MovimientoInventario::TIPO_AJUSTE_POSITIVO,
            'bodega_origen_id' => null,
            'bodega_destino_id' => $bodegaDestino->id,
            'cantidad' => $cantidad,
            'stock_origen_antes' => null,
            'stock_origen_despues' => null,
            'stock_destino_antes' => $stockAntes,
            'stock_destino_despues' => $stockDespues,
        ], $this->datosComplementariosMovimiento($data, $costoUnitario, $costoTotal, $userId)));
    }

    private function registrarAjusteNegativo(array $data, int $empresaId, ?int $userId): MovimientoInventario
    {
        $producto = $this->obtenerProductoActivoEmpresa((int) $data['producto_id'], $empresaId);
        $bodegaOrigen = $this->obtenerBodegaActivaEmpresa((int) $data['bodega_origen_id'], $empresaId);
        $cantidad = $this->validarCantidadPositiva($data['cantidad']);

        $stockOrigen = $this->obtenerOCrearStockBloqueado($producto->id, $bodegaOrigen->id, $empresaId);

        $stockAntes = $this->toFloat($stockOrigen->stock_actual);

        if ($stockAntes < $cantidad) {
            throw ValidationException::withMessages([
                'cantidad' => 'Stock insuficiente para realizar el ajuste negativo.',
            ]);
        }

        $valorAntes = $this->toFloat($stockOrigen->valor_total);
        $costoUnitario = $this->obtenerCostoUnitarioSalida($stockOrigen, $producto);
        $costoTotal = $this->redondearCantidad($cantidad * $costoUnitario);

        $stockDespues = $this->redondearCantidad($stockAntes - $cantidad);
        $valorDespues = $this->calcularValorDespuesSalida($stockDespues, $valorAntes, $costoTotal);
        $costoPromedioDespues = $this->calcularCostoPromedio($stockDespues, $valorDespues);

        $stockOrigen->update([
            'stock_actual' => $stockDespues,
            'costo_promedio' => $costoPromedioDespues,
            'valor_total' => $valorDespues,
        ]);

        return MovimientoInventario::create(array_merge([
            'empresa_id' => $empresaId,
            'producto_id' => $producto->id,
            'tipo' => MovimientoInventario::TIPO_AJUSTE_NEGATIVO,
            'bodega_origen_id' => $bodegaOrigen->id,
            'bodega_destino_id' => null,
            'cantidad' => $cantidad,
            'stock_origen_antes' => $stockAntes,
            'stock_origen_despues' => $stockDespues,
            'stock_destino_antes' => null,
            'stock_destino_despues' => null,
        ], $this->datosComplementariosMovimiento($data, $costoUnitario, $costoTotal, $userId)));
    }

    public function listarMovimientos(array $filtros, int $empresaId): LengthAwarePaginator
    {
        $perPage = $this->normalizarPerPage($filtros['per_page'] ?? 15);

        return MovimientoInventario::query()
            ->with([
                'producto:id,empresa_id,sku,nombre,activo',
                'bodegaOrigen:id,empresa_id,codigo,nombre,estado',
                'bodegaDestino:id,empresa_id,codigo,nombre,estado',
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
                'producto:id,empresa_id,sku,nombre,activo',
                'bodegaOrigen:id,empresa_id,codigo,nombre,estado',
                'bodegaDestino:id,empresa_id,codigo,nombre,estado',
            ])
            ->empresa($empresaId)
            ->producto($productoId)
            ->when(!empty($filtros['bodega_id']), function ($query) use ($filtros) {
                $query->bodega((int) $filtros['bodega_id']);
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
                'producto:id,empresa_id,sku,nombre,activo',
                'bodegaOrigen:id,empresa_id,codigo,nombre,estado',
                'bodegaDestino:id,empresa_id,codigo,nombre,estado',
            ])
            ->empresa($empresaId)
            ->when(!empty($filtros['producto_id']), function ($query) use ($filtros) {
                $query->producto((int) $filtros['producto_id']);
            })
            ->when(!empty($filtros['bodega_id']), function ($query) use ($filtros) {
                $query->bodega((int) $filtros['bodega_id']);
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

    private function obtenerCostoUnitarioEntrada(array $data, StockProducto $stock, Producto $producto): float
    {
        $costoUnitario = $this->normalizarDecimalNullable(
            $data['costo_unitario'] ?? null,
            'costo_unitario',
            'El costo unitario debe ser numérico.',
            'El costo unitario no puede ser negativo.'
        );

        if ($costoUnitario !== null) {
            return $costoUnitario;
        }

        $costoProducto = $this->toFloat($producto->costo_promedio);

        if ($costoProducto > 0) {
            return $costoProducto;
        }

        return $this->toFloat($stock->costo_promedio);
    }

    private function obtenerCostoUnitarioSalida(StockProducto $stock, Producto $producto): float
    {
        $costoStock = $this->toFloat($stock->costo_promedio);

        if ($costoStock > 0) {
            return $costoStock;
        }

        return $this->toFloat($producto->costo_promedio);
    }

    private function datosComplementariosMovimiento(
        array $data,
        float $costoUnitario,
        float $costoTotal,
        ?int $userId
    ): array {
        return [
            'costo_unitario' => $costoUnitario,
            'costo_total' => $costoTotal,
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

    private function calcularValorDespuesSalida(float $stockDespues, float $valorAntes, float $costoTotal): float
    {
        if ($stockDespues <= 0) {
            return 0;
        }

        return max(0, $this->redondearCantidad($valorAntes - $costoTotal));
    }

    private function calcularCostoPromedio(float $stock, float $valorTotal): float
    {
        if ($stock <= 0) {
            return 0;
        }

        return $this->redondearCantidad($valorTotal / $stock);
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