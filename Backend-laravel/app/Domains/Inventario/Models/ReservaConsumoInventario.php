<?php

namespace App\Domains\Inventario\Models;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservaConsumoInventario extends Model
{
    protected $table = 'inventario_reserva_consumos';

    protected $fillable = [
        'empresa_id',
        'reserva_id',
        'reserva_detalle_id',
        'movimiento_inventario_id',
        'producto_id',
        'bodega_id',
        'lote_id',
        'cantidad_consumida',
        'consumido_por',
        'fecha_consumo',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'reserva_id' => 'integer',
        'reserva_detalle_id' => 'integer',
        'movimiento_inventario_id' => 'integer',
        'producto_id' => 'integer',
        'bodega_id' => 'integer',
        'lote_id' => 'integer',
        'consumido_por' => 'integer',

        'cantidad_consumida' => 'decimal:4',
        'fecha_consumo' => 'datetime',
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

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(ReservaInventario::class, 'reserva_id');
    }

    public function detalle(): BelongsTo
    {
        return $this->belongsTo(ReservaDetalleInventario::class, 'reserva_detalle_id');
    }

    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(MovimientoInventario::class, 'movimiento_inventario_id');
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

    public function consumidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consumido_por');
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

    public function scopeReserva(Builder $query, int $reservaId): Builder
    {
        return $query->where('reserva_id', $reservaId);
    }

    public function scopeDetalle(Builder $query, int $detalleId): Builder
    {
        return $query->where('reserva_detalle_id', $detalleId);
    }

    public function scopeMovimiento(Builder $query, int $movimientoId): Builder
    {
        return $query->where('movimiento_inventario_id', $movimientoId);
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

    public function scopeDesde(Builder $query, string $fecha): Builder
    {
        return $query->whereDate('fecha_consumo', '>=', $fecha);
    }

    public function scopeHasta(Builder $query, string $fecha): Builder
    {
        return $query->whereDate('fecha_consumo', '<=', $fecha);
    }

    public function scopeMasRecientes(Builder $query): Builder
    {
        return $query
            ->orderByDesc('fecha_consumo')
            ->orderByDesc('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function tieneLote(): bool
    {
        return $this->lote_id !== null;
    }

    public function perteneceAEmpresa(int $empresaId): bool
    {
        return (int) $this->empresa_id === $empresaId;
    }

    public function perteneceAReserva(int $reservaId): bool
    {
        return (int) $this->reserva_id === $reservaId;
    }

    public function perteneceADetalle(int $detalleId): bool
    {
        return (int) $this->reserva_detalle_id === $detalleId;
    }

    public function perteneceAMovimiento(int $movimientoId): bool
    {
        return (int) $this->movimiento_inventario_id === $movimientoId;
    }
}