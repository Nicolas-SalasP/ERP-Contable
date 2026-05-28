<?php

namespace App\Domains\Inventario\Models;

use App\Domains\Core\Models\Empresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioDevolucionDetalle extends Model
{
    protected $table = 'inventario_devolucion_detalles';

    public const ESTADO_PENDIENTE = 'PENDIENTE';
    public const ESTADO_ACEPTADO = 'ACEPTADO';
    public const ESTADO_PARCIAL = 'PARCIAL';
    public const ESTADO_RECHAZADO = 'RECHAZADO';
    public const ESTADO_CANCELADO = 'CANCELADO';

    protected $fillable = [
        'empresa_id',
        'devolucion_orden_id',
        'despacho_detalle_id',
        'producto_id',
        'bodega_id',
        'ubicacion_destino_id',
        'lote_id',
        'cantidad_despachada_original',
        'cantidad_ya_reversada',
        'cantidad_devolver',
        'cantidad_aceptada',
        'cantidad_rechazada',
        'estado',
        'motivo',
        'observacion',
        'movimiento_inventario_id',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'devolucion_orden_id' => 'integer',
        'despacho_detalle_id' => 'integer',
        'producto_id' => 'integer',
        'bodega_id' => 'integer',
        'ubicacion_destino_id' => 'integer',
        'lote_id' => 'integer',
        'movimiento_inventario_id' => 'integer',
        'cantidad_despachada_original' => 'decimal:4',
        'cantidad_ya_reversada' => 'decimal:4',
        'cantidad_devolver' => 'decimal:4',
        'cantidad_aceptada' => 'decimal:4',
        'cantidad_rechazada' => 'decimal:4',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function devolucion(): BelongsTo
    {
        return $this->belongsTo(InventarioDevolucionOrden::class, 'devolucion_orden_id');
    }

    public function despachoDetalle(): BelongsTo
    {
        return $this->belongsTo(InventarioDespachoDetalle::class, 'despacho_detalle_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function bodega(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_id');
    }

    public function ubicacionDestino(): BelongsTo
    {
        return $this->belongsTo(InventarioUbicacion::class, 'ubicacion_destino_id');
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(LoteInventario::class, 'lote_id');
    }

    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(MovimientoInventario::class, 'movimiento_inventario_id');
    }

    public function scopeEmpresa(Builder $query, int $empresaId): Builder
    {
        return $query->where('empresa_id', $empresaId);
    }
}
