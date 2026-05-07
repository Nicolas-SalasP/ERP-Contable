<?php

namespace App\Domains\Inventario\Models;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TomaFisicaDetalleInventario extends Model
{
    protected $table = 'inventario_toma_fisica_detalles';

    protected $fillable = [
        'empresa_id',
        'toma_fisica_id',
        'producto_id',
        'bodega_id',
        'lote_id',
        'stock_sistema',
        'stock_contado',
        'diferencia',
        'movimiento_ajuste_id',
        'observacion',
        'contado_por',
        'fecha_conteo',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'toma_fisica_id' => 'integer',
        'producto_id' => 'integer',
        'bodega_id' => 'integer',
        'lote_id' => 'integer',
        'movimiento_ajuste_id' => 'integer',
        'contado_por' => 'integer',

        'stock_sistema' => 'decimal:4',
        'stock_contado' => 'decimal:4',
        'diferencia' => 'decimal:4',

        'fecha_conteo' => 'datetime',
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

    public function tomaFisica(): BelongsTo
    {
        return $this->belongsTo(TomaFisicaInventario::class, 'toma_fisica_id');
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

    public function movimientoAjuste(): BelongsTo
    {
        return $this->belongsTo(MovimientoInventario::class, 'movimiento_ajuste_id');
    }

    public function contadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contado_por');
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

    public function scopeTomaFisica(Builder $query, int $tomaFisicaId): Builder
    {
        return $query->where('toma_fisica_id', $tomaFisicaId);
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

    public function scopeSinLote(Builder $query): Builder
    {
        return $query->whereNull('lote_id');
    }

    public function scopeConLote(Builder $query): Builder
    {
        return $query->whereNotNull('lote_id');
    }

    public function scopeConConteo(Builder $query): Builder
    {
        return $query->whereNotNull('stock_contado');
    }

    public function scopeSinConteo(Builder $query): Builder
    {
        return $query->whereNull('stock_contado');
    }

    public function scopeConDiferencia(Builder $query): Builder
    {
        return $query->where('diferencia', '!=', 0);
    }

    public function scopeSinDiferencia(Builder $query): Builder
    {
        return $query->where('diferencia', '=', 0);
    }

    public function scopeDiferenciaPositiva(Builder $query): Builder
    {
        return $query->where('diferencia', '>', 0);
    }

    public function scopeDiferenciaNegativa(Builder $query): Builder
    {
        return $query->where('diferencia', '<', 0);
    }

    public function scopeAjustados(Builder $query): Builder
    {
        return $query->whereNotNull('movimiento_ajuste_id');
    }

    public function scopePendientesAjuste(Builder $query): Builder
    {
        return $query
            ->where('diferencia', '!=', 0)
            ->whereNull('movimiento_ajuste_id');
    }

    public function scopeMasRecientes(Builder $query): Builder
    {
        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers de conteo
    |--------------------------------------------------------------------------
    */

    public function tieneLote(): bool
    {
        return $this->lote_id !== null;
    }

    public function fueContado(): bool
    {
        return $this->stock_contado !== null;
    }

    public function estaPendienteConteo(): bool
    {
        return $this->stock_contado === null;
    }

    public function fueAjustado(): bool
    {
        return $this->movimiento_ajuste_id !== null;
    }

    public function tieneDiferencia(): bool
    {
        return (float) $this->diferencia !== 0.0;
    }

    public function tieneDiferenciaPositiva(): bool
    {
        return (float) $this->diferencia > 0;
    }

    public function tieneDiferenciaNegativa(): bool
    {
        return (float) $this->diferencia < 0;
    }

    public function tieneDiferenciaCero(): bool
    {
        return (float) $this->diferencia === 0.0;
    }

    public function requiereMovimientoAjuste(): bool
    {
        return $this->tieneDiferencia() && ! $this->fueAjustado();
    }

    public function stockSistemaFloat(): float
    {
        return (float) $this->stock_sistema;
    }

    public function stockContadoFloat(): ?float
    {
        if ($this->stock_contado === null) {
            return null;
        }

        return (float) $this->stock_contado;
    }

    public function diferenciaFloat(): float
    {
        return (float) $this->diferencia;
    }

    public function cantidadAbsolutaDiferencia(): float
    {
        return abs((float) $this->diferencia);
    }

    public function calcularDiferencia(float $stockContado): float
    {
        return round($stockContado - (float) $this->stock_sistema, 4);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers de pertenencia
    |--------------------------------------------------------------------------
    */

    public function perteneceAEmpresa(int $empresaId): bool
    {
        return (int) $this->empresa_id === $empresaId;
    }

    public function perteneceATomaFisica(int $tomaFisicaId): bool
    {
        return (int) $this->toma_fisica_id === $tomaFisicaId;
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

    public function perteneceAMovimientoAjuste(int $movimientoId): bool
    {
        return (int) $this->movimiento_ajuste_id === $movimientoId;
    }
}