<?php

namespace App\Domains\Inventario\Models;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AjusteCriticoInventario extends Model
{
    protected $table = 'inventario_ajustes_criticos';

    protected $fillable = [
        'empresa_id',
        'movimiento_inventario_id',
        'tipo_ajuste_critico_id',
        'producto_id',
        'bodega_id',
        'cantidad',
        'costo_unitario',
        'costo_total',
        'motivo',
        'observacion',
        'referencia',
        'origen_modulo',
        'origen_id',
        'registrado_por',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'movimiento_inventario_id' => 'integer',
        'tipo_ajuste_critico_id' => 'integer',
        'producto_id' => 'integer',
        'bodega_id' => 'integer',
        'origen_id' => 'integer',
        'registrado_por' => 'integer',

        'cantidad' => 'decimal:4',
        'costo_unitario' => 'decimal:4',
        'costo_total' => 'decimal:4',
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

    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(MovimientoInventario::class, 'movimiento_inventario_id');
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoAjusteCritico::class, 'tipo_ajuste_critico_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function bodega(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
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

    public function scopeProducto(Builder $query, int $productoId): Builder
    {
        return $query->where('producto_id', $productoId);
    }

    public function scopeBodega(Builder $query, int $bodegaId): Builder
    {
        return $query->where('bodega_id', $bodegaId);
    }

    public function scopeTipoAjusteCritico(Builder $query, int $tipoAjusteCriticoId): Builder
    {
        return $query->where('tipo_ajuste_critico_id', $tipoAjusteCriticoId);
    }

    public function scopeDesde(Builder $query, string $fecha): Builder
    {
        return $query->whereDate('created_at', '>=', $fecha);
    }

    public function scopeHasta(Builder $query, string $fecha): Builder
    {
        return $query->whereDate('created_at', '<=', $fecha);
    }

    public function scopeOrigen(Builder $query, string $origenModulo, ?int $origenId = null): Builder
    {
        $query->where('origen_modulo', $origenModulo);

        if ($origenId !== null) {
            $query->where('origen_id', $origenId);
        }

        return $query;
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

    public function esAjustePositivo(): bool
    {
        return $this->tipo?->esAjustePositivo() === true;
    }

    public function esAjusteNegativo(): bool
    {
        return $this->tipo?->esAjusteNegativo() === true;
    }

    public function costoTotalCalculado(): float
    {
        return round((float) $this->cantidad * (float) $this->costo_unitario, 4);
    }

    public function tieneReferenciaExterna(): bool
    {
        return !empty($this->referencia)
            || !empty($this->origen_modulo)
            || !empty($this->origen_id);
    }
}