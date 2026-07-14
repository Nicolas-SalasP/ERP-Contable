<?php

namespace App\Domains\Inventario\Models;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Core\Traits\HasEmpresaScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $empresa_id
 * @property int $movimiento_inventario_id
 * @property int $tipo_ajuste_critico_id
 * @property int $producto_id
 * @property int $bodega_id
 * @property int|null $lote_id
 * @property string $cantidad
 * @property string $costo_unitario
 * @property string $costo_total
 * @property string|null $motivo
 * @property string|null $observacion
 * @property string|null $referencia
 * @property string|null $origen_modulo
 * @property int|null $origen_id
 * @property int $registrado_por
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read int|null $total_ajustes
 */
class AjusteCriticoInventario extends Model
{
    use HasEmpresaScope;

    protected $table = 'inventario_ajustes_criticos';

    protected $fillable = [
        'empresa_id',
        'movimiento_inventario_id',
        'tipo_ajuste_critico_id',
        'producto_id',
        'bodega_id',
        'lote_id',
        'cantidad',
        'costo_unitario',
        'costo_total',
        'motivo',
        'observacion',
        'referencia',
        'origen_modulo',
        'origen_id',
        'registrado_por',
        'anulado_at',
        'anulado_por',
        'motivo_anulacion',
        'movimiento_reversa_id',
        'valorizacion_capa_id',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'movimiento_inventario_id' => 'integer',
        'tipo_ajuste_critico_id' => 'integer',
        'producto_id' => 'integer',
        'bodega_id' => 'integer',
        'lote_id' => 'integer',
        'origen_id' => 'integer',
        'registrado_por' => 'integer',
        'anulado_por' => 'integer',
        'movimiento_reversa_id' => 'integer',
        'valorizacion_capa_id' => 'integer',
        'anulado_at' => 'datetime',

        'cantidad' => 'decimal:4',
        'costo_unitario' => 'decimal:4',
        'costo_total' => 'decimal:4',
    ];

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    /** @return BelongsTo<MovimientoInventario, $this> */
    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(MovimientoInventario::class, 'movimiento_inventario_id');
    }

    /** @return BelongsTo<TipoAjusteCritico, $this> */
    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoAjusteCritico::class, 'tipo_ajuste_critico_id');
    }

    /** @return BelongsTo<Producto, $this> */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    /** @return BelongsTo<Bodega, $this> */
    public function bodega(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_id');
    }

    /** @return BelongsTo<LoteInventario, $this> */
    public function lote(): BelongsTo
    {
        return $this->belongsTo(LoteInventario::class, 'lote_id');
    }

    /** @return BelongsTo<User, $this> */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    /** @return BelongsTo<User, $this> */
    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    /** @return BelongsTo<MovimientoInventario, $this> */
    public function movimientoReversa(): BelongsTo
    {
        return $this->belongsTo(MovimientoInventario::class, 'movimiento_reversa_id');
    }

    /** @return BelongsTo<InventarioValorizacionCapa, $this> */
    public function valorizacionCapa(): BelongsTo
    {
        return $this->belongsTo(InventarioValorizacionCapa::class, 'valorizacion_capa_id');
    }

    public function estaAnulado(): bool
    {
        return $this->anulado_at !== null;
    }

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

    public function scopeLote(Builder $query, int $loteId): Builder
    {
        return $query->where('lote_id', $loteId);
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

    public function esAjustePositivo(): bool
    {
        return $this->tipo?->esAjustePositivo() === true;
    }

    public function esAjusteNegativo(): bool
    {
        return $this->tipo?->esAjusteNegativo() === true;
    }

    public function tieneLote(): bool
    {
        return $this->lote_id !== null;
    }

    public function costoTotalCalculado(): float
    {
        return round((float) $this->cantidad * (float) $this->costo_unitario, 4);
    }

    public function tieneReferenciaExterna(): bool
    {
        return ! empty($this->referencia)
            || ! empty($this->origen_modulo)
            || ! empty($this->origen_id);
    }
}
