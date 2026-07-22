<?php

namespace App\Domains\Inventario\Models;

use App\Domains\Core\Traits\HasEmpresaScope;

use App\Domains\Core\Models\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read \App\Domains\Inventario\Models\UnidadMedida|null $unidadMedida
 * @property-read \App\Domains\Inventario\Models\Bodega|null $bodegaDefecto
 */
class Producto extends Model
{
    use HasEmpresaScope;
    use \App\Domains\Sii\Concerns\HasSiiAttributesProducto;

    protected $table = 'inventario_productos';

    protected $fillable = [
        'empresa_id',
        'sku',
        'nombre',
        'descripcion',
        'tipo_producto',
        'unidad_medida_id',
        'metodo_valorizacion',
        'costo_promedio',
        'precio_venta_neto',
        'afecto_iva',
        'codigo_barra',
        'stock_minimo',
        'bodega_defecto_id',
        'permite_merma',
        'maneja_lotes',
        'requiere_fecha_vencimiento',
        'activo',
        'visible_web',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'unidad_medida_id' => 'integer',
        'bodega_defecto_id' => 'integer',

        'costo_promedio' => 'decimal:4',
        'precio_venta_neto' => 'decimal:4',
        'stock_minimo' => 'decimal:4',

        'afecto_iva' => 'boolean',
        'permite_merma' => 'boolean',
        'maneja_lotes' => 'boolean',
        'requiere_fecha_vencimiento' => 'boolean',
        'activo' => 'boolean',
        'visible_web' => 'boolean',
    ];

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** @return BelongsTo<UnidadMedida, $this> */
    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class, 'unidad_medida_id');
    }

    /** @return BelongsTo<Bodega, $this> */
    public function bodegaDefecto(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_defecto_id');
    }

    /** @return HasMany<StockProducto, $this> */
    public function stocks(): HasMany
    {
        return $this->hasMany(StockProducto::class, 'producto_id');
    }

    /** @return HasMany<LoteInventario, $this> */
    public function lotes(): HasMany
    {
        return $this->hasMany(LoteInventario::class, 'producto_id');
    }

    /** @return HasMany<StockLoteInventario, $this> */
    public function stockLotes(): HasMany
    {
        return $this->hasMany(StockLoteInventario::class, 'producto_id');
    }

    /** @return HasMany<MovimientoLoteInventario, $this> */
    public function movimientosLotes(): HasMany
    {
        return $this->hasMany(MovimientoLoteInventario::class, 'producto_id');
    }

    /** @return HasMany<AjusteCriticoInventario, $this> */
    public function ajustesCriticos(): HasMany
    {
        return $this->hasMany(AjusteCriticoInventario::class, 'producto_id');
    }

    /** @return HasMany<ReservaDetalleInventario, $this> */
    public function reservaDetalles(): HasMany
    {
        return $this->hasMany(ReservaDetalleInventario::class, 'producto_id');
    }

    /** @return HasMany<ReservaConsumoInventario, $this> */
    public function reservaConsumos(): HasMany
    {
        return $this->hasMany(ReservaConsumoInventario::class, 'producto_id');
    }

    /** @return HasMany<TomaFisicaDetalleInventario, $this> */
    public function tomaFisicaDetalles(): HasMany
    {
        return $this->hasMany(TomaFisicaDetalleInventario::class, 'producto_id');
    }
    public function estaActivo(): bool
    {
        return $this->activo === true;
    }

    public function manejaLotes(): bool
    {
        return $this->maneja_lotes === true;
    }

    public function requiereFechaVencimiento(): bool
    {
        return $this->requiere_fecha_vencimiento === true;
    }

    public function perteneceAEmpresa(int $empresaId): bool
    {
        return (int) $this->empresa_id === $empresaId;
    }
}