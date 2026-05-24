<?php

namespace App\Domains\Inventario\Models;

use App\Domains\Core\Models\Empresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockUbicacionInventario extends Model
{
    protected $table = 'inventario_stock_ubicaciones';

    public const ESTADO_DISPONIBLE = 'DISPONIBLE';
    public const ESTADO_EN_RECEPCION = 'EN_RECEPCION';
    public const ESTADO_EN_PUTAWAY = 'EN_PUTAWAY';
    public const ESTADO_CUARENTENA = 'CUARENTENA';
    public const ESTADO_BLOQUEADO = 'BLOQUEADO';
    public const ESTADO_EN_TRANSITO_INTERNO = 'EN_TRANSITO_INTERNO';

    protected $fillable = [
        'empresa_id',
        'producto_id',
        'bodega_id',
        'ubicacion_id',
        'lote_id',
        'lote_key',
        'stock_actual',
        'stock_reservado',
        'stock_bloqueado',
        'stock_cuarentena',
        'stock_en_transito',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'producto_id' => 'integer',
        'bodega_id' => 'integer',
        'ubicacion_id' => 'integer',
        'lote_id' => 'integer',
        'lote_key' => 'integer',
        'stock_actual' => 'decimal:4',
        'stock_reservado' => 'decimal:4',
        'stock_bloqueado' => 'decimal:4',
        'stock_cuarentena' => 'decimal:4',
        'stock_en_transito' => 'decimal:4',
    ];

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

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(InventarioUbicacion::class, 'ubicacion_id');
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(LoteInventario::class, 'lote_id');
    }

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

    public function scopeUbicacion(Builder $query, int $ubicacionId): Builder
    {
        return $query->where('ubicacion_id', $ubicacionId);
    }

    public static function estadosPermitidos(): array
    {
        return [
            self::ESTADO_DISPONIBLE,
            self::ESTADO_EN_RECEPCION,
            self::ESTADO_EN_PUTAWAY,
            self::ESTADO_CUARENTENA,
            self::ESTADO_BLOQUEADO,
            self::ESTADO_EN_TRANSITO_INTERNO,
        ];
    }

    public function stockDisponible(): float
    {
        return round(
            (float) $this->stock_actual
            - (float) $this->stock_reservado
            - (float) $this->stock_bloqueado
            - (float) $this->stock_cuarentena
            - (float) $this->stock_en_transito,
            4
        );
    }

    public function tieneDisponible(float $cantidad): bool
    {
        return $this->stockDisponible() >= round($cantidad, 4);
    }
}
