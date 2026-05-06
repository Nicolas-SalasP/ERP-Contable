<?php

namespace App\Domains\Inventario\Models;

use App\Domains\Core\Models\Empresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLoteInventario extends Model
{
    protected $table = 'inventario_stock_lotes';

    protected $fillable = [
        'empresa_id',
        'producto_id',
        'bodega_id',
        'lote_id',
        'stock_actual',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'producto_id' => 'integer',
        'bodega_id' => 'integer',
        'lote_id' => 'integer',
        'stock_actual' => 'decimal:4',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function bodega(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_id');
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(LoteInventario::class, 'lote_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeEmpresa(Builder $query, int $empresaId): Builder
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function scopeProducto(Builder $query, int $productoId): Builder
    {
        return $query->where('producto_id', $productoId);
    }

    public function scopeBodega(Builder $query, int $bodegaId): Builder
    {
        return $query->where('bodega_id', $bodegaId);
    }

    public function scopeLote(Builder $query, int $loteId): Builder
    {
        return $query->where('lote_id', $loteId);
    }

    public function scopeConStock(Builder $query): Builder
    {
        return $query->where('stock_actual', '>', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function tieneStock(): bool
    {
        return (float) $this->stock_actual > 0;
    }

    public function stockDisponible(): float
    {
        return (float) $this->stock_actual;
    }

    public function tieneStockSuficiente(float $cantidad): bool
    {
        return (float) $this->stock_actual >= $cantidad;
    }

    public function perteneceAEmpresa(int $empresaId): bool
    {
        return (int) $this->empresa_id === $empresaId;
    }

    public function perteneceAProducto(int $productoId): bool
    {
        return (int) $this->producto_id === $productoId;
    }

    public function perteneceABodega(int $bodegaId): bool
    {
        return (int) $this->bodega_id === $bodegaId;
    }

    public function perteneceALote(int $loteId): bool
    {
        return (int) $this->lote_id === $loteId;
    }
}