<?php

namespace App\Domains\Inventario\Models;

use App\Domains\Core\Models\Empresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InventarioPickingAsignacion extends Model
{
    protected $table = 'inventario_picking_asignaciones';

    public const ESTADO_PENDIENTE = 'PENDIENTE';
    public const ESTADO_PARCIAL = 'PARCIAL';
    public const ESTADO_COMPLETO = 'COMPLETO';
    public const ESTADO_SIN_STOCK = 'SIN_STOCK';
    public const ESTADO_CANCELADO = 'CANCELADO';

    protected $fillable = [
        'empresa_id',
        'picking_orden_id',
        'picking_detalle_id',
        'reserva_detalle_id',
        'producto_id',
        'bodega_id',
        'ubicacion_origen_id',
        'lote_id',
        'cantidad_asignada',
        'cantidad_pickeada',
        'cantidad_faltante',
        'estado',
        'observacion',
        'fecha_confirmacion',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'picking_orden_id' => 'integer',
        'picking_detalle_id' => 'integer',
        'reserva_detalle_id' => 'integer',
        'producto_id' => 'integer',
        'bodega_id' => 'integer',
        'ubicacion_origen_id' => 'integer',
        'lote_id' => 'integer',
        'cantidad_asignada' => 'decimal:4',
        'cantidad_pickeada' => 'decimal:4',
        'cantidad_faltante' => 'decimal:4',
        'fecha_confirmacion' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(InventarioPickingOrden::class, 'picking_orden_id');
    }

    public function detalle(): BelongsTo
    {
        return $this->belongsTo(InventarioPickingDetalle::class, 'picking_detalle_id');
    }

    public function reservaDetalle(): BelongsTo
    {
        return $this->belongsTo(ReservaDetalleInventario::class, 'reserva_detalle_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function bodega(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_id');
    }

    public function ubicacionOrigen(): BelongsTo
    {
        return $this->belongsTo(InventarioUbicacion::class, 'ubicacion_origen_id');
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(LoteInventario::class, 'lote_id');
    }

    public function packingDetalle(): HasOne
    {
        return $this->hasOne(InventarioPackingDetalle::class, 'picking_asignacion_id');
    }

    public function scopeEmpresa(Builder $query, int $empresaId): Builder
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function cantidadPendientePicking(): float
    {
        return round((float) $this->cantidad_asignada - (float) $this->cantidad_pickeada, 4);
    }
}
