<?php

namespace App\Domains\Inventario\Models;

use App\Domains\Core\Traits\HasEmpresaScope;

use App\Domains\Core\Models\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockProducto extends Model
{
    use HasEmpresaScope;
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

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** @return BelongsTo<Producto, $this> */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    /** @return BelongsTo<Bodega, $this> */
    public function bodega(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_id');
    }

    /** @return HasMany<StockLoteInventario, $this> */
    public function stockLotes(): HasMany
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