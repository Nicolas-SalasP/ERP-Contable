<?php

namespace App\Domains\Inventario\Models;

use App\Domains\Core\Models\Empresa;
use Illuminate\Database\Eloquent\Model;

class StockProducto extends Model
{
    protected $table = 'inventario_stock';

    protected $fillable = [
        'empresa_id',
        'producto_id',
        'bodega_id',
        'stock_actual',
        'costo_promedio',
        'valor_total',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'producto_id' => 'integer',
        'bodega_id' => 'integer',

        'stock_actual' => 'decimal:4',
        'costo_promedio' => 'decimal:4',
        'valor_total' => 'decimal:4',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function bodega()
    {
        return $this->belongsTo(Bodega::class, 'bodega_id');
    }

    public function stockLotes()
    {
        return $this->hasMany(StockLoteInventario::class, 'producto_id', 'producto_id')
            ->where('bodega_id', $this->bodega_id);
    }

    public function stockDisponible(): float
    {
        return (float) $this->stock_actual;
    }

    public function tieneStockSuficiente(float $cantidad): bool
    {
        return (float) $this->stock_actual >= $cantidad;
    }
}