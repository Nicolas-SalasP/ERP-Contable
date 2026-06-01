<?php

namespace App\Domains\Inventario\Models;

use App\Domains\Core\Models\Empresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReservaDetalleInventario extends Model
{
    protected $table = 'inventario_reserva_detalles';

    protected $fillable = [
        'empresa_id',
        'reserva_id',
        'producto_id',
        'bodega_id',
        'ubicacion_id',
        'lote_id',
        'estado_stock',
        'cantidad_reservada',
        'cantidad_consumida',
        'cantidad_liberada',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'reserva_id' => 'integer',
        'producto_id' => 'integer',
        'bodega_id' => 'integer',
        'ubicacion_id' => 'integer',
        'lote_id' => 'integer',

        'cantidad_reservada' => 'decimal:4',
        'cantidad_consumida' => 'decimal:4',
        'cantidad_liberada' => 'decimal:4',
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

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(InventarioUbicacion::class, 'ubicacion_id');
    }

    public function consumos(): HasMany
    {
        return $this->hasMany(ReservaConsumoInventario::class, 'reserva_detalle_id');
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

    public function scopeUbicacion(Builder $query, int $ubicacionId): Builder
    {
        return $query->where('ubicacion_id', $ubicacionId);
    }

    public function scopeSinLote(Builder $query): Builder
    {
        return $query->whereNull('lote_id');
    }

    public function scopeConLote(Builder $query): Builder
    {
        return $query->whereNotNull('lote_id');
    }

    public function scopeReservasQueComprometenDisponibilidad(Builder $query): Builder
    {
        return $query->whereHas('reserva', function (Builder $subQuery) {
            $subQuery->comprometenDisponibilidad();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function cantidadPendiente(): float
    {
        $pendiente = (float) $this->cantidad_reservada
            - (float) $this->cantidad_consumida
            - (float) $this->cantidad_liberada;

        return round(max(0, $pendiente), 4);
    }

    public function cantidadComprometida(): float
    {
        return $this->cantidadPendiente();
    }

    public function tienePendiente(): bool
    {
        return $this->cantidadPendiente() > 0;
    }

    public function tieneLote(): bool
    {
        return $this->lote_id !== null;
    }

    public function puedeLiberar(float $cantidad): bool
    {
        return $cantidad > 0 && $this->cantidadPendiente() >= $cantidad;
    }

    public function puedeConsumir(float $cantidad): bool
    {
        return $cantidad > 0 && $this->cantidadPendiente() >= $cantidad;
    }

    public function estaTotalmenteCerrado(): bool
    {
        return $this->cantidadPendiente() <= 0;
    }

    public function perteneceAEmpresa(int $empresaId): bool
    {
        return (int) $this->empresa_id === $empresaId;
    }

    public function perteneceAReserva(int $reservaId): bool
    {
        return (int) $this->reserva_id === $reservaId;
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