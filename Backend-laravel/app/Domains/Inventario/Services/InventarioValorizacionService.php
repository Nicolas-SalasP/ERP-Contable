<?php

namespace App\Domains\Inventario\Services;

use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\StockProducto;
use App\Domains\Inventario\Services\Valorizacion\FifoValorizacionStrategy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventarioValorizacionService
{
    private const DECIMALES = 4;

    public function __construct(
        private ?FifoValorizacionStrategy $fifoStrategy = null
    ) {}

    public function calcularEntrada(
        StockProducto $stock,
        Producto $producto,
        float $cantidad,
        ?float $costoUnitario = null,
        ?int $loteId = null,
        ?string $fechaMovimiento = null,
        bool $costoCeroIntencional = false
    ): array {
        if ($this->usaFifo($producto)) {
            return $this->fifoStrategy()->calcularEntrada(
                stock: $stock,
                producto: $producto,
                cantidad: $cantidad,
                costoUnitario: $costoUnitario,
                loteId: $loteId,
                fechaMovimiento: $fechaMovimiento,
                costoCeroIntencional: $costoCeroIntencional
            );
        }

        $resultado = $this->calcularEntradaPmp($stock, $producto, $cantidad, $costoUnitario, costoCeroIntencional: $costoCeroIntencional);
        $resultado['metodo_valorizacion'] = 'PMP';

        return $resultado;
    }

    public function calcularSalida(
        StockProducto $stock,
        Producto $producto,
        float $cantidad,
        ?int $loteId = null,
        ?int $capaObjetivoId = null,
        ?float $costoUnitarioForzado = null
    ): array {
        if ($this->usaFifo($producto)) {
            return $this->fifoStrategy()->calcularSalida(
                stock: $stock,
                producto: $producto,
                cantidad: $cantidad,
                loteId: $loteId,
                capaObjetivoId: $capaObjetivoId
            );
        }

        $resultado = $this->calcularSalidaPmp($stock, $producto, $cantidad, costoUnitarioForzado: $costoUnitarioForzado);
        $resultado['metodo_valorizacion'] = 'PMP';

        return $resultado;
    }

    public function calcularTraspaso(
        StockProducto $stockOrigen,
        StockProducto $stockDestino,
        Producto $producto,
        float $cantidad,
        ?int $loteId = null,
        ?string $fechaMovimiento = null
    ): array {
        if ($this->usaFifo($producto)) {
            return $this->fifoStrategy()->calcularTraspaso(
                stockOrigen: $stockOrigen,
                stockDestino: $stockDestino,
                producto: $producto,
                cantidad: $cantidad,
                loteId: $loteId,
                fechaMovimiento: $fechaMovimiento
            );
        }

        $resultado = $this->calcularTraspasoPmp($stockOrigen, $stockDestino, $producto, $cantidad);
        $resultado['metodo_valorizacion'] = 'PMP';

        return $resultado;
    }

    private function usaFifo(Producto $producto): bool
    {
        return strtoupper((string) $producto->metodo_valorizacion) === 'FIFO';
    }

    private function fifoStrategy(): FifoValorizacionStrategy
    {
        return $this->fifoStrategy ??= app(FifoValorizacionStrategy::class);
    }

    /** Calcula entrada valorizada con PMP: nuevo_stock = stock_actual + cantidad; nuevo_valor_total = valor_total_actual + (cantidad * costo_unitario); nuevo_costo_promedio = nuevo_valor_total / nuevo_stock. */
    public function calcularEntradaPmp(
        StockProducto $stock,
        Producto $producto,
        float $cantidad,
        ?float $costoUnitario = null,
        bool $actualizarProducto = true,
        bool $costoCeroIntencional = false
    ): array {
        $this->validarCantidadPositiva($cantidad);

        if ($costoUnitario !== null && $costoUnitario < 0) {
            throw new RuntimeException('El costo unitario no puede ser negativo.');
        }

        if ($costoUnitario === 0.0 && ! $costoCeroIntencional) {
            throw new RuntimeException(
                'El costo unitario de la entrada es 0. Si es intencional (bonificación, muestra gratis, etc.), '
                .'confirme explícitamente que el costo cero es correcto.'
            );
        }

        $stockAntes = $this->numero($stock->stock_actual);
        $valorAntes = $this->numero($stock->valor_total);
        $costoPromedioAntes = $this->numero($stock->costo_promedio);

        $costoEntrada = $this->obtenerCostoUnitarioEntrada(
            stock: $stock,
            producto: $producto,
            costoUnitario: $costoUnitario
        );

        $valorEntrada = $this->redondear($cantidad * $costoEntrada);
        $stockDespues = $this->redondear($stockAntes + $cantidad);
        $valorDespues = $this->redondear($valorAntes + $valorEntrada);

        $costoPromedioDespues = $stockDespues > 0
            ? $this->redondear($valorDespues / $stockDespues)
            : 0.0000;

        $stock->stock_actual = $stockDespues;
        $stock->costo_promedio = $costoPromedioDespues;
        $stock->valor_total = $valorDespues;
        $stock->save();

        $productoCostoPromedio = null;

        if ($actualizarProducto) {
            $productoCostoPromedio = $this->actualizarCostoPromedioProducto(
                empresaId: (int) $stock->empresa_id,
                productoId: (int) $stock->producto_id
            );
        }

        return [
            'stock_antes' => $this->formatear($stockAntes),
            'stock_despues' => $this->formatear($stockDespues),
            'costo_promedio_antes' => $this->formatear($costoPromedioAntes),
            'costo_promedio_despues' => $this->formatear($costoPromedioDespues),
            'valor_antes' => $this->formatear($valorAntes),
            'valor_despues' => $this->formatear($valorDespues),
            'costo_unitario' => $this->formatear($costoEntrada),
            'costo_total' => $this->formatear($valorEntrada),
            'producto_costo_promedio' => $productoCostoPromedio !== null
                ? $this->formatear($productoCostoPromedio)
                : null,
        ];
    }

    /**
     * Calcula salida valorizada con PMP: usa el PMP actual de la bodega y no lo recalcula
     * hacia arriba o abajo. $costoUnitarioForzado permite anular exactamente la reversa de un
     * ajuste crítico positivo, usando el costo original del ajuste en vez del promedio actual
     * (el promedio ponderado ya absorbió ese costo y no puede "recordar" la porción original
     * si se usara el promedio vigente para revertir).
     */
    public function calcularSalidaPmp(
        StockProducto $stock,
        Producto $producto,
        float $cantidad,
        bool $actualizarProducto = true,
        ?float $costoUnitarioForzado = null
    ): array {
        $this->validarCantidadPositiva($cantidad);

        $stockAntes = $this->numero($stock->stock_actual);
        $valorAntes = $this->numero($stock->valor_total);
        $costoPromedioAntes = $this->numero($stock->costo_promedio);

        if ($stockAntes < $cantidad) {
            throw new RuntimeException('Stock insuficiente para realizar la operación.');
        }

        $costoSalida = $costoUnitarioForzado !== null
            ? $this->redondear($costoUnitarioForzado)
            : $this->obtenerCostoUnitarioSalida(
                stock: $stock,
                producto: $producto
            );

        $valorSalida = $this->redondear($cantidad * $costoSalida);
        $stockDespues = $this->redondear($stockAntes - $cantidad);

        $valorDespues = $stockDespues > 0
            ? $this->redondear($valorAntes - $valorSalida)
            : 0.0000;

        if ($valorDespues < 0) {
            $valorDespues = 0.0000;
        }

        // En una salida normal (costoUnitarioForzado === null), costoSalida es el propio
        // promedio vigente, así que remover unidades a ese costo no lo altera: usarlo
        // directamente equivale a recalcular valorDespues/stockDespues. Pero cuando se fuerza un
        // costo distinto al promedio actual (reversa exacta de un ajuste crítico positivo), el
        // promedio resultante SÍ cambia y debe recalcularse a partir del valor remanente.
        $costoPromedioDespues = $stockDespues > 0 ? $this->redondear($valorDespues / $stockDespues) : 0.0000;

        $stock->stock_actual = $stockDespues;
        $stock->costo_promedio = $costoPromedioDespues;
        $stock->valor_total = $valorDespues;
        $stock->save();

        $productoCostoPromedio = null;

        if ($actualizarProducto) {
            $productoCostoPromedio = $this->actualizarCostoPromedioProducto(
                empresaId: (int) $stock->empresa_id,
                productoId: (int) $stock->producto_id
            );
        }

        return [
            'stock_antes' => $this->formatear($stockAntes),
            'stock_despues' => $this->formatear($stockDespues),
            'costo_promedio_antes' => $this->formatear($costoPromedioAntes),
            'costo_promedio_despues' => $this->formatear((float) $stock->costo_promedio),
            'valor_antes' => $this->formatear($valorAntes),
            'valor_despues' => $this->formatear($valorDespues),
            'costo_unitario' => $this->formatear($costoSalida),
            'costo_total' => $this->formatear($valorSalida),
            'producto_costo_promedio' => $productoCostoPromedio !== null
                ? $this->formatear($productoCostoPromedio)
                : null,
        ];
    }

    /** Calcula traspaso valorizado: la bodega origen descuenta usando su PMP y la bodega destino recibe usando el costo PMP de origen. */
    public function calcularTraspasoPmp(
        StockProducto $stockOrigen,
        StockProducto $stockDestino,
        Producto $producto,
        float $cantidad
    ): array {
        $this->validarCantidadPositiva($cantidad);

        if ((int) $stockOrigen->bodega_id === (int) $stockDestino->bodega_id) {
            throw new RuntimeException('La bodega origen y destino no pueden ser la misma.');
        }

        $salida = $this->calcularSalidaPmp(
            stock: $stockOrigen,
            producto: $producto,
            cantidad: $cantidad,
            actualizarProducto: false
        );

        $entrada = $this->calcularEntradaPmp(
            stock: $stockDestino,
            producto: $producto,
            cantidad: $cantidad,
            costoUnitario: (float) $salida['costo_unitario'],
            actualizarProducto: false,
            // El costo viene heredado de la bodega origen (ya aceptado cuando entro alli), no es
            // una decision nueva del usuario: un traspaso nunca debe exigir reconfirmacion de costo cero.
            costoCeroIntencional: true
        );

        $productoCostoPromedio = $this->actualizarCostoPromedioProducto(
            empresaId: (int) $stockOrigen->empresa_id,
            productoId: (int) $stockOrigen->producto_id
        );

        return [
            'origen' => $salida,
            'destino' => $entrada,
            'costo_unitario' => $salida['costo_unitario'],
            'costo_total' => $salida['costo_total'],
            'producto_costo_promedio' => $this->formatear($productoCostoPromedio),
        ];
    }

    /** Obtiene costo de entrada con prioridad: costo_unitario recibido, costo_promedio del stock de la bodega, costo_promedio consolidado del producto, o 0. */
    public function obtenerCostoUnitarioEntrada(
        StockProducto $stock,
        Producto $producto,
        ?float $costoUnitario = null
    ): float {
        if ($costoUnitario !== null) {
            if ($costoUnitario < 0) {
                throw new RuntimeException('El costo unitario no puede ser negativo.');
            }

            return $this->redondear($costoUnitario);
        }

        $costoStock = $this->numero($stock->costo_promedio);

        if ($costoStock > 0) {
            return $this->redondear($costoStock);
        }

        $costoProducto = $this->numero($producto->costo_promedio);

        if ($costoProducto > 0) {
            return $this->redondear($costoProducto);
        }

        return 0.0000;
    }

    /** Obtiene costo de salida usando PMP con prioridad: costo_promedio del stock de la bodega, costo_promedio consolidado del producto, o 0. */
    public function obtenerCostoUnitarioSalida(
        StockProducto $stock,
        Producto $producto
    ): float {
        $costoStock = $this->numero($stock->costo_promedio);

        if ($costoStock > 0) {
            return $this->redondear($costoStock);
        }

        $costoProducto = $this->numero($producto->costo_promedio);

        if ($costoProducto > 0) {
            return $this->redondear($costoProducto);
        }

        return 0.0000;
    }

    /** Actualiza el PMP consolidado del producto: producto.costo_promedio = SUM(valor_total) / SUM(stock_actual). */
    public function actualizarCostoPromedioProducto(int $empresaId, int $productoId): float
    {
        $resumen = StockProducto::query()
            ->where('empresa_id', $empresaId)
            ->where('producto_id', $productoId)
            ->selectRaw('COALESCE(SUM(stock_actual), 0) as stock_total')
            ->selectRaw('COALESCE(SUM(valor_total), 0) as valor_total')
            ->first();

        $stockTotal = $this->numero($resumen->stock_total ?? 0);
        $valorTotal = $this->numero($resumen->valor_total ?? 0);

        $costoPromedio = $stockTotal > 0
            ? $this->redondear($valorTotal / $stockTotal)
            : 0.0000;

        Producto::query()
            ->where('empresa_id', $empresaId)
            ->where('id', $productoId)
            ->update([
                'costo_promedio' => $costoPromedio,
                'updated_at' => now(),
            ]);

        return $costoPromedio;
    }

    /** Obtiene o crea stock de producto/bodega; debe usarse dentro de una transacción cuando se llame desde movimientos, porque aplica lockForUpdate(). */
    public function obtenerOCrearStock(
        int $empresaId,
        int $productoId,
        int $bodegaId
    ): StockProducto {
        $stock = StockProducto::query()
            ->where('empresa_id', $empresaId)
            ->where('producto_id', $productoId)
            ->where('bodega_id', $bodegaId)
            ->lockForUpdate()
            ->first();

        if ($stock) {
            return $stock;
        }

        StockProducto::query()->create([
            'empresa_id' => $empresaId,
            'producto_id' => $productoId,
            'bodega_id' => $bodegaId,
            'stock_actual' => 0,
            'costo_promedio' => 0,
            'valor_total' => 0,
        ]);

        return StockProducto::query()
            ->where('empresa_id', $empresaId)
            ->where('producto_id', $productoId)
            ->where('bodega_id', $bodegaId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /** Lista stock valorizado por producto/bodega. */
    public function listarValorizacion(int $empresaId, array $filtros = []): LengthAwarePaginator
    {
        $page = max((int) ($filtros['page'] ?? 1), 1);
        $perPage = min(max((int) ($filtros['per_page'] ?? 15), 1), 100);

        $query = DB::table('inventario_stock as s')
            ->join('inventario_productos as p', function ($join) use ($empresaId) {
                $join->on('p.id', '=', 's.producto_id')
                    ->where('p.empresa_id', '=', $empresaId);
            })
            ->join('inventario_bodegas as b', function ($join) use ($empresaId) {
                $join->on('b.id', '=', 's.bodega_id')
                    ->where('b.empresa_id', '=', $empresaId);
            })
            ->where('s.empresa_id', $empresaId)
            ->select([
                's.id',
                's.empresa_id',
                's.producto_id',
                's.bodega_id',
                's.stock_actual',
                's.costo_promedio',
                's.valor_total',
                's.created_at',
                's.updated_at',
                'p.sku as producto_sku',
                'p.nombre as producto_nombre',
                'p.activo as producto_activo',
                'p.costo_promedio as producto_costo_promedio',
                'b.codigo as bodega_codigo',
                'b.nombre as bodega_nombre',
                'b.estado as bodega_estado',
            ])
            ->orderBy('p.nombre')
            ->orderBy('b.nombre');

        $this->aplicarFiltrosValorizacion($query, $filtros);

        $paginado = $query->paginate($perPage, ['*'], 'page', $page);

        $paginado->getCollection()->transform(function ($item) {
            return [
                'id' => $item->id,
                'empresa_id' => $item->empresa_id,
                'producto' => [
                    'id' => $item->producto_id,
                    'sku' => $item->producto_sku,
                    'nombre' => $item->producto_nombre,
                    'activo' => (bool) $item->producto_activo,
                    'costo_promedio' => $this->formatear((float) $item->producto_costo_promedio),
                ],
                'bodega' => [
                    'id' => $item->bodega_id,
                    'codigo' => $item->bodega_codigo,
                    'nombre' => $item->bodega_nombre,
                    'estado' => $item->bodega_estado,
                ],
                'stock_actual' => $this->formatear((float) $item->stock_actual),
                'costo_promedio' => $this->formatear((float) $item->costo_promedio),
                'valor_total' => $this->formatear((float) $item->valor_total),
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ];
        });

        return $paginado;
    }

    /** Resumen valorizado según filtros. */
    public function resumenValorizacion(int $empresaId, array $filtros = []): array
    {
        $query = DB::table('inventario_stock as s')
            ->join('inventario_productos as p', function ($join) use ($empresaId) {
                $join->on('p.id', '=', 's.producto_id')
                    ->where('p.empresa_id', '=', $empresaId);
            })
            ->join('inventario_bodegas as b', function ($join) use ($empresaId) {
                $join->on('b.id', '=', 's.bodega_id')
                    ->where('b.empresa_id', '=', $empresaId);
            })
            ->where('s.empresa_id', $empresaId);

        $this->aplicarFiltrosValorizacion($query, $filtros);

        $resumen = $query
            ->selectRaw('COALESCE(SUM(s.stock_actual), 0) as stock_total')
            ->selectRaw('COALESCE(SUM(s.valor_total), 0) as valor_total')
            ->first();

        $stockTotal = $this->numero($resumen->stock_total ?? 0);
        $valorTotal = $this->numero($resumen->valor_total ?? 0);

        $costoPromedioGlobal = $stockTotal > 0
            ? $this->redondear($valorTotal / $stockTotal)
            : 0.0000;

        return [
            'stock_total' => $this->formatear($stockTotal),
            'valor_total' => $this->formatear($valorTotal),
            'costo_promedio_global' => $this->formatear($costoPromedioGlobal),
        ];
    }

    /** Aplica filtros comunes para listado/resumen. */
    private function aplicarFiltrosValorizacion($query, array $filtros): void
    {
        if (! empty($filtros['producto_id'])) {
            $query->where('s.producto_id', (int) $filtros['producto_id']);
        }

        if (! empty($filtros['bodega_id'])) {
            $query->where('s.bodega_id', (int) $filtros['bodega_id']);
        }

        if (! empty($filtros['search'])) {
            $search = trim((string) $filtros['search']);

            $query->where(function ($subQuery) use ($search) {
                $subQuery
                    ->where('p.nombre', 'like', "%{$search}%")
                    ->orWhere('p.sku', 'like', "%{$search}%")
                    ->orWhere('b.nombre', 'like', "%{$search}%")
                    ->orWhere('b.codigo', 'like', "%{$search}%");
            });
        }
    }

    private function validarCantidadPositiva(float $cantidad): void
    {
        if ($cantidad <= 0) {
            throw new RuntimeException('La cantidad debe ser mayor a cero.');
        }
    }

    private function numero(mixed $valor): float
    {
        return $this->redondear((float) ($valor ?? 0));
    }

    private function redondear(float $valor): float
    {
        return round($valor, self::DECIMALES);
    }

    private function formatear(float $valor): string
    {
        return number_format($this->redondear($valor), self::DECIMALES, '.', '');
    }
}
