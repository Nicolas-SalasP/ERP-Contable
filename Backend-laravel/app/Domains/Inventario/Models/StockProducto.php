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
}