<?php

namespace App\Domains\Inventario\Services\Valorizacion;

use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\StockProducto;

interface ValorizacionStrategyInterface
{
    public function metodo(): string;

    public function calcularEntrada(
        StockProducto $stock,
        Producto $producto,
        float $cantidad,
        ?float $costoUnitario = null,
        ?int $loteId = null,
        ?string $fechaMovimiento = null
    ): array;

    public function calcularSalida(
        StockProducto $stock,
        Producto $producto,
        float $cantidad,
        ?int $loteId = null
    ): array;

    public function calcularTraspaso(
        StockProducto $stockOrigen,
        StockProducto $stockDestino,
        Producto $producto,
        float $cantidad,
        ?int $loteId = null,
        ?string $fechaMovimiento = null
    ): array;
}
