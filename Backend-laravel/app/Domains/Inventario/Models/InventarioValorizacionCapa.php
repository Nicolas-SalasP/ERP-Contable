<?php

namespace App\Domains\Inventario\Models;

use App\Domains\Core\Models\Empresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioValorizacionCapa extends Model
{
    public const ESTADO_ABIERTA = 'ABIERTA';
    public const ESTADO_CONSUMIDA = 'CONSUMIDA';

    protected $table = 'inventario_valorizacion_capas';

    protected $fillable = [
        'empresa_id',
        'producto_id',
        'bodega_id',
        'lote_id',
        'movimiento_origen_id',
        'cantidad_inicial',
        'cantidad_disponible',
        'costo_unitario',
        'valor_disponible',
        'fecha_entrada',
        'estado',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'producto_id' => 'integer',
        'bodega_id' => 'integer',
        'lote_id' => 'integer',
        'movimiento_origen_id' => 'integer',
        'cantidad_inicial' => 'decimal:4',
        'cantidad_disponible' => 'decimal:4',
        'costo_unitario' => 'decimal:4',
        'valor_disponible' => 'decimal:4',
        'fecha_entrada' => 'datetime',
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

    public function lote(): BelongsTo
    {
        return $this->belongsTo(LoteInventario::class, 'lote_id');
    }

    public function movimientoOrigen(): BelongsTo
    {
        return $this->belongsTo(MovimientoInventario::class, 'movimiento_origen_id');
    }

    public function scopeAbiertas(Builder $query): Builder
    {
        return $query->where('estado', self::ESTADO_ABIERTA)
            ->where('cantidad_disponible', '>', 0);
    }
}
