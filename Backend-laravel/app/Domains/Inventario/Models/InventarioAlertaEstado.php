<?php

namespace App\Domains\Inventario\Models;

use App\Domains\Core\Models\Empresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioAlertaEstado extends Model
{
    protected $table = 'inventario_alertas_estado';

    protected $fillable = [
        'empresa_id',
        'tipo',
        'severidad',
        'titulo',
        'descripcion',
        'producto_id',
        'bodega_id',
        'lote_id',
        'cantidad_actual',
        'stock_minimo',
        'stock_objetivo',
        'cantidad_sugerida',
        'fecha_referencia',
        'referencia',
        'metadata',
        'calculado_en',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'producto_id' => 'integer',
        'bodega_id' => 'integer',
        'lote_id' => 'integer',
        'cantidad_actual' => 'decimal:4',
        'stock_minimo' => 'decimal:4',
        'stock_objetivo' => 'decimal:4',
        'cantidad_sugerida' => 'decimal:4',
        'fecha_referencia' => 'date',
        'metadata' => 'array',
        'calculado_en' => 'datetime',
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

    public function scopeEmpresa(Builder $query, int $empresaId): Builder
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function scopeCriticas(Builder $query): Builder
    {
        return $query->where('severidad', 'critica');
    }
}
