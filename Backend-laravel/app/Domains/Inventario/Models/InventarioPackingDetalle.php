<?php

namespace App\Domains\Inventario\Models;

use App\Domains\Core\Models\Empresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InventarioPackingDetalle extends Model
{
    protected $table = 'inventario_packing_detalles';

    public const ESTADO_PENDIENTE = 'PENDIENTE';
    public const ESTADO_PARCIAL = 'PARCIAL';
    public const ESTADO_EMPACADO = 'EMPACADO';
    public const ESTADO_CON_DIFERENCIAS = 'CON_DIFERENCIAS';
    public const ESTADO_CANCELADO = 'CANCELADO';

    protected $fillable = [
        'empresa_id',
        'packing_orden_id',
        'picking_detalle_id',
        'picking_asignacion_id',
        'producto_id',
        'ubicacion_origen_id',
        'lote_id',
        'cantidad_pickeada',
        'cantidad_empacada',
        'cantidad_faltante',
        'estado',
        'observacion',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'packing_orden_id' => 'integer',
        'picking_detalle_id' => 'integer',
        'picking_asignacion_id' => 'integer',
        'producto_id' => 'integer',
        'ubicacion_origen_id' => 'integer',
        'lote_id' => 'integer',
        'cantidad_pickeada' => 'decimal:4',
        'cantidad_empacada' => 'decimal:4',
        'cantidad_faltante' => 'decimal:4',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(InventarioPackingOrden::class, 'packing_orden_id');
    }

    public function pickingDetalle(): BelongsTo
    {
        return $this->belongsTo(InventarioPickingDetalle::class, 'picking_detalle_id');
    }

    public function pickingAsignacion(): BelongsTo
    {
        return $this->belongsTo(InventarioPickingAsignacion::class, 'picking_asignacion_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function ubicacionOrigen(): BelongsTo
    {
        return $this->belongsTo(InventarioUbicacion::class, 'ubicacion_origen_id');
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(LoteInventario::class, 'lote_id');
    }

    public function despachoDetalle(): HasOne
    {
        return $this->hasOne(InventarioDespachoDetalle::class, 'packing_detalle_id');
    }

    public function scopeEmpresa(Builder $query, int $empresaId): Builder
    {
        return $query->where('empresa_id', $empresaId);
    }
}
