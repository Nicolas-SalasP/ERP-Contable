<?php

namespace App\Domains\Inventario\Models;

use App\Domains\Core\Models\Empresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoteInventario extends Model
{
    public const ESTADO_DISPONIBLE = 'DISPONIBLE';
    public const ESTADO_CUARENTENA = 'CUARENTENA';
    public const ESTADO_BLOQUEADO = 'BLOQUEADO';
    protected $table = 'inventario_lotes';

    protected $fillable = [
        'empresa_id',
        'producto_id',
        'codigo_lote',
        'fecha_fabricacion',
        'fecha_vencimiento',
        'observacion',
        'activo',
        'estado_operativo',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'producto_id' => 'integer',
        'fecha_fabricacion' => 'date',
        'fecha_vencimiento' => 'date',
        'activo' => 'boolean',
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

    public function stocks(): HasMany
    {
        return $this->hasMany(StockLoteInventario::class, 'lote_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoLoteInventario::class, 'lote_id');
    }

    public function ajustesCriticos(): HasMany
    {
        return $this->hasMany(AjusteCriticoInventario::class, 'lote_id');
    }


    public function reservaDetalles(): HasMany
    {
        return $this->hasMany(ReservaDetalleInventario::class, 'lote_id');
    }

    public function reservaConsumos(): HasMany
    {
        return $this->hasMany(ReservaConsumoInventario::class, 'lote_id');
    }
    public function tomaFisicaDetalles(): HasMany
    {
    return $this->hasMany(TomaFisicaDetalleInventario::class, 'lote_id');
    }

    public function scopeEmpresa(Builder $query, int $empresaId): Builder
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function scopeProducto(Builder $query, int $productoId): Builder
    {
        return $query->where('producto_id', $productoId);
    }

    public function scopeCodigo(Builder $query, string $codigoLote): Builder
    {
        return $query->where('codigo_lote', $codigoLote);
    }

    public function scopeActivo(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function scopeInactivo(Builder $query): Builder
    {
        return $query->where('activo', false);
    }

    public function scopeConVencimiento(Builder $query): Builder
    {
        return $query->whereNotNull('fecha_vencimiento');
    }

    public function scopeVencidos(Builder $query): Builder
    {
        return $query
            ->whereNotNull('fecha_vencimiento')
            ->whereDate('fecha_vencimiento', '<', now()->toDateString());
    }

    public function scopePorVencerHasta(Builder $query, string $fecha): Builder
    {
        return $query
            ->whereNotNull('fecha_vencimiento')
            ->whereDate('fecha_vencimiento', '<=', $fecha);
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

    public function estaActivo(): bool
    {
        return $this->activo === true;
    }

    public function tieneFechaVencimiento(): bool
    {
        return $this->fecha_vencimiento !== null;
    }

    public function estaVencido(): bool
    {
        if ($this->fecha_vencimiento === null) {
            return false;
        }

        return $this->fecha_vencimiento->toDateString() < now()->toDateString();
    }

    public function estaEnCuarentena(): bool
    {
        return $this->estado_operativo === self::ESTADO_CUARENTENA;
    }

    public function estaBloqueadoOperativamente(): bool
    {
        return in_array($this->estado_operativo, [self::ESTADO_CUARENTENA, self::ESTADO_BLOQUEADO], true);
    }

    public function puedeMoverseSalida(): bool
    {
        return !$this->estaVencido() && !$this->estaBloqueadoOperativamente();
    }

    public function perteneceAEmpresa(int $empresaId): bool
    {
        return (int) $this->empresa_id === $empresaId;
    }

    public function perteneceAProducto(int $productoId): bool
    {
        return (int) $this->producto_id === $productoId;
    }
}