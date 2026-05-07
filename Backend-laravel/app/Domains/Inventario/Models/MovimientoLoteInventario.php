<?php

namespace App\Domains\Inventario\Models;

use App\Domains\Core\Models\Empresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoLoteInventario extends Model
{
    protected $table = 'inventario_movimiento_lotes';

    protected $fillable = [
        'empresa_id',
        'movimiento_inventario_id',
        'producto_id',
        'lote_id',
        'bodega_origen_id',
        'bodega_destino_id',
        'cantidad',
        'stock_lote_origen_antes',
        'stock_lote_origen_despues',
        'stock_lote_destino_antes',
        'stock_lote_destino_despues',
        'costo_unitario',
        'costo_total',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'movimiento_inventario_id' => 'integer',
        'producto_id' => 'integer',
        'lote_id' => 'integer',
        'bodega_origen_id' => 'integer',
        'bodega_destino_id' => 'integer',

        'cantidad' => 'decimal:4',
        'stock_lote_origen_antes' => 'decimal:4',
        'stock_lote_origen_despues' => 'decimal:4',
        'stock_lote_destino_antes' => 'decimal:4',
        'stock_lote_destino_despues' => 'decimal:4',
        'costo_unitario' => 'decimal:4',
        'costo_total' => 'decimal:4',
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

    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(MovimientoInventario::class, 'movimiento_inventario_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(LoteInventario::class, 'lote_id');
    }

    public function bodegaOrigen(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_origen_id');
    }

    public function bodegaDestino(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_destino_id');
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

    public function scopeLote(Builder $query, int $loteId): Builder
    {
        return $query->where('lote_id', $loteId);
    }

    public function scopeMovimiento(Builder $query, int $movimientoId): Builder
    {
        return $query->where('movimiento_inventario_id', $movimientoId);
    }

    public function scopeBodega(Builder $query, int $bodegaId): Builder
    {
        return $query->where(function (Builder $subQuery) use ($bodegaId) {
            $subQuery
                ->where('bodega_origen_id', $bodegaId)
                ->orWhere('bodega_destino_id', $bodegaId);
        });
    }

    public function scopeMasRecientes(Builder $query): Builder
    {
        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function esEntradaLote(): bool
    {
        return $this->bodega_origen_id === null
            && $this->bodega_destino_id !== null;
    }

    public function esSalidaLote(): bool
    {
        return $this->bodega_origen_id !== null
            && $this->bodega_destino_id === null;
    }

    public function esTraspasoLote(): bool
    {
        return $this->bodega_origen_id !== null
            && $this->bodega_destino_id !== null;
    }

    public function costoTotalCalculado(): float
    {
        return round((float) $this->cantidad * (float) $this->costo_unitario, 4);
    }
}