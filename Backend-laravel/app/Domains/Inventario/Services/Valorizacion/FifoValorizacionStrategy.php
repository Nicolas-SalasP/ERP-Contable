<?php

namespace App\Domains\Inventario\Services\Valorizacion;

use App\Domains\Inventario\Models\InventarioValorizacionCapa;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\StockProducto;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FifoValorizacionStrategy implements ValorizacionStrategyInterface
{
    private const DECIMALES = 4;

    public function metodo(): string
    {
        return 'FIFO';
    }

    public function calcularEntrada(
        StockProducto $stock,
        Producto $producto,
        float $cantidad,
        ?float $costoUnitario = null,
        ?int $loteId = null,
        ?string $fechaMovimiento = null
    ): array {
        $this->validarCantidadPositiva($cantidad);

        if ($costoUnitario !== null && $costoUnitario < 0) {
            throw new RuntimeException('El costo unitario no puede ser negativo.');
        }

        $stockAntes = $this->numero($stock->stock_actual);
        $valorAntes = $this->numero($stock->valor_total);
        $costoAntes = $this->numero($stock->costo_promedio);
        $costoEntrada = $this->resolverCostoEntrada($stock, $producto, $costoUnitario);
        $valorEntrada = $this->redondear($cantidad * $costoEntrada);
        $stockDespues = $this->redondear($stockAntes + $cantidad);
        $valorDespues = $this->redondear($valorAntes + $valorEntrada);
        $costoDespues = $stockDespues > 0 ? $this->redondear($valorDespues / $stockDespues) : 0.0000;

        $stock->stock_actual = $stockDespues;
        $stock->costo_promedio = $costoDespues;
        $stock->valor_total = $valorDespues;
        $stock->save();

        InventarioValorizacionCapa::create([
            'empresa_id' => (int) $stock->empresa_id,
            'producto_id' => (int) $stock->producto_id,
            'bodega_id' => (int) $stock->bodega_id,
            'lote_id' => $loteId,
            'movimiento_origen_id' => null,
            'cantidad_inicial' => $this->formatear($cantidad),
            'cantidad_disponible' => $this->formatear($cantidad),
            'costo_unitario' => $this->formatear($costoEntrada),
            'valor_disponible' => $this->formatear($valorEntrada),
            'fecha_entrada' => $fechaMovimiento ?: now(),
            'estado' => InventarioValorizacionCapa::ESTADO_ABIERTA,
        ]);

        $productoCostoPromedio = $this->actualizarCostoPromedioProducto(
            empresaId: (int) $stock->empresa_id,
            productoId: (int) $stock->producto_id
        );

        return [
            'stock_antes' => $this->formatear($stockAntes),
            'stock_despues' => $this->formatear($stockDespues),
            'costo_promedio_antes' => $this->formatear($costoAntes),
            'costo_promedio_despues' => $this->formatear($costoDespues),
            'valor_antes' => $this->formatear($valorAntes),
            'valor_despues' => $this->formatear($valorDespues),
            'costo_unitario' => $this->formatear($costoEntrada),
            'costo_total' => $this->formatear($valorEntrada),
            'producto_costo_promedio' => $this->formatear($productoCostoPromedio),
            'metodo_valorizacion' => $this->metodo(),
        ];
    }

    public function calcularSalida(
        StockProducto $stock,
        Producto $producto,
        float $cantidad,
        ?int $loteId = null
    ): array {
        $this->validarCantidadPositiva($cantidad);

        $stockAntes = $this->numero($stock->stock_actual);
        $valorAntes = $this->numero($stock->valor_total);
        $costoAntes = $this->numero($stock->costo_promedio);

        if ($stockAntes < $cantidad) {
            throw new RuntimeException('Stock insuficiente para realizar la operación.');
        }

        $this->asegurarCapaInicialSiNoExiste($stock, $producto, $loteId);
        $valorSalida = $this->consumirCapasFifo($stock, $cantidad, $loteId);
        $costoSalida = $cantidad > 0 ? $this->redondear($valorSalida / $cantidad) : 0.0000;
        $stockDespues = $this->redondear($stockAntes - $cantidad);
        $valorDespues = $stockDespues > 0 ? max($this->redondear($valorAntes - $valorSalida), 0.0000) : 0.0000;
        $costoDespues = $stockDespues > 0 ? $this->redondear($valorDespues / $stockDespues) : 0.0000;

        $stock->stock_actual = $stockDespues;
        $stock->valor_total = $valorDespues;
        $stock->costo_promedio = $costoDespues;
        $stock->save();

        $productoCostoPromedio = $this->actualizarCostoPromedioProducto(
            empresaId: (int) $stock->empresa_id,
            productoId: (int) $stock->producto_id
        );

        return [
            'stock_antes' => $this->formatear($stockAntes),
            'stock_despues' => $this->formatear($stockDespues),
            'costo_promedio_antes' => $this->formatear($costoAntes),
            'costo_promedio_despues' => $this->formatear($costoDespues),
            'valor_antes' => $this->formatear($valorAntes),
            'valor_despues' => $this->formatear($valorDespues),
            'costo_unitario' => $this->formatear($costoSalida),
            'costo_total' => $this->formatear($valorSalida),
            'producto_costo_promedio' => $this->formatear($productoCostoPromedio),
            'metodo_valorizacion' => $this->metodo(),
        ];
    }

    public function calcularTraspaso(
        StockProducto $stockOrigen,
        StockProducto $stockDestino,
        Producto $producto,
        float $cantidad,
        ?int $loteId = null,
        ?string $fechaMovimiento = null
    ): array {
        if ((int) $stockOrigen->bodega_id === (int) $stockDestino->bodega_id) {
            throw new RuntimeException('La bodega origen y destino no pueden ser la misma.');
        }

        $salida = $this->calcularSalida($stockOrigen, $producto, $cantidad, $loteId);
        $entrada = $this->calcularEntrada(
            stock: $stockDestino,
            producto: $producto,
            cantidad: $cantidad,
            costoUnitario: (float) $salida['costo_unitario'],
            loteId: $loteId,
            fechaMovimiento: $fechaMovimiento
        );

        return [
            'origen' => $salida,
            'destino' => $entrada,
            'costo_unitario' => $salida['costo_unitario'],
            'costo_total' => $salida['costo_total'],
            'producto_costo_promedio' => $entrada['producto_costo_promedio'],
            'metodo_valorizacion' => $this->metodo(),
        ];
    }

    private function consumirCapasFifo(StockProducto $stock, float $cantidad, ?int $loteId): float
    {
        $pendiente = $this->redondear($cantidad);
        $valorConsumido = 0.0000;

        $query = InventarioValorizacionCapa::query()
            ->where('empresa_id', $stock->empresa_id)
            ->where('producto_id', $stock->producto_id)
            ->where('bodega_id', $stock->bodega_id)
            ->abiertas()
            ->orderBy('fecha_entrada')
            ->orderBy('id')
            ->lockForUpdate();

        if ($loteId !== null) {
            $query->where('lote_id', $loteId);
        }

        $capas = $query->get();

        foreach ($capas as $capa) {
            if ($pendiente <= 0) {
                break;
            }

            $disponible = $this->numero($capa->cantidad_disponible);
            $consumir = min($pendiente, $disponible);
            $nuevoDisponible = $this->redondear($disponible - $consumir);
            $valorCapaConsumido = $this->redondear($consumir * $this->numero($capa->costo_unitario));
            $nuevoValorDisponible = $nuevoDisponible > 0
                ? $this->redondear($nuevoDisponible * $this->numero($capa->costo_unitario))
                : 0.0000;

            $capa->update([
                'cantidad_disponible' => $nuevoDisponible,
                'valor_disponible' => $nuevoValorDisponible,
                'estado' => $nuevoDisponible > 0
                    ? InventarioValorizacionCapa::ESTADO_ABIERTA
                    : InventarioValorizacionCapa::ESTADO_CONSUMIDA,
            ]);

            $valorConsumido = $this->redondear($valorConsumido + $valorCapaConsumido);
            $pendiente = $this->redondear($pendiente - $consumir);
        }

        if ($pendiente > 0) {
            throw new RuntimeException('Capas FIFO insuficientes para valorizar la salida.');
        }

        return $this->formatear($valorConsumido);
    }

    private function asegurarCapaInicialSiNoExiste(StockProducto $stock, Producto $producto, ?int $loteId): void
    {
        $existe = InventarioValorizacionCapa::query()
            ->where('empresa_id', $stock->empresa_id)
            ->where('producto_id', $stock->producto_id)
            ->where('bodega_id', $stock->bodega_id)
            ->when($loteId !== null, fn ($query) => $query->where('lote_id', $loteId))
            ->abiertas()
            ->exists();

        if ($existe || $this->numero($stock->stock_actual) <= 0) {
            return;
        }

        $cantidad = $this->numero($stock->stock_actual);
        $costo = $this->resolverCostoEntrada($stock, $producto, null);

        InventarioValorizacionCapa::create([
            'empresa_id' => (int) $stock->empresa_id,
            'producto_id' => (int) $stock->producto_id,
            'bodega_id' => (int) $stock->bodega_id,
            'lote_id' => $loteId,
            'movimiento_origen_id' => null,
            'cantidad_inicial' => $this->formatear($cantidad),
            'cantidad_disponible' => $this->formatear($cantidad),
            'costo_unitario' => $this->formatear($costo),
            'valor_disponible' => $this->formatear($cantidad * $costo),
            'fecha_entrada' => now()->subSecond(),
            'estado' => InventarioValorizacionCapa::ESTADO_ABIERTA,
        ]);
    }

    private function actualizarCostoPromedioProducto(int $empresaId, int $productoId): float
    {
        $resumen = StockProducto::query()
            ->where('empresa_id', $empresaId)
            ->where('producto_id', $productoId)
            ->selectRaw('COALESCE(SUM(stock_actual), 0) as stock_total')
            ->selectRaw('COALESCE(SUM(valor_total), 0) as valor_total')
            ->first();

        $stockTotal = $this->numero($resumen->stock_total ?? 0);
        $valorTotal = $this->numero($resumen->valor_total ?? 0);
        $costoPromedio = $stockTotal > 0 ? $this->redondear($valorTotal / $stockTotal) : 0.0000;

        Producto::query()
            ->where('empresa_id', $empresaId)
            ->where('id', $productoId)
            ->update([
                'costo_promedio' => $costoPromedio,
                'updated_at' => now(),
            ]);

        return $costoPromedio;
    }

    private function resolverCostoEntrada(StockProducto $stock, Producto $producto, ?float $costoUnitario): float
    {
        if ($costoUnitario !== null) {
            return $this->redondear($costoUnitario);
        }

        $costoStock = $this->numero($stock->costo_promedio);
        if ($costoStock > 0) {
            return $this->redondear($costoStock);
        }

        $costoProducto = $this->numero($producto->costo_promedio);
        return $costoProducto > 0 ? $this->redondear($costoProducto) : 0.0000;
    }

    private function validarCantidadPositiva(float $cantidad): void
    {
        if ($cantidad <= 0) {
            throw new RuntimeException('La cantidad debe ser mayor a cero.');
        }
    }

    private function numero(mixed $valor): float
    {
        return $this->redondear((float) $valor);
    }

    private function redondear(float $valor): float
    {
        return round($valor, self::DECIMALES);
    }

    private function formatear(float $valor): float
    {
        return (float) number_format($this->redondear($valor), self::DECIMALES, '.', '');
    }
}
