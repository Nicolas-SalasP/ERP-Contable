<?php

namespace App\Domains\Inventario\Models;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Traits\HasEmpresaScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoSerie extends Model
{
    use HasEmpresaScope;

    protected $table = 'inventario_producto_series';

    public const ESTADO_EN_STOCK = 'en_stock';

    public const ESTADO_VENDIDO = 'vendido';

    public const ESTADO_DEVUELTO = 'devuelto';

    public const ESTADO_EN_SERVICIO_TECNICO = 'en_servicio_tecnico';

    protected $fillable = [
        'empresa_id',
        'producto_id',
        'lote_id',
        'numero_serie',
        'estado',
        'venta_referencia',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'producto_id' => 'integer',
        'lote_id' => 'integer',
    ];

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** @return BelongsTo<Producto, $this> */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    /** @return BelongsTo<LoteInventario, $this> */
    public function lote(): BelongsTo
    {
        return $this->belongsTo(LoteInventario::class, 'lote_id');
    }

    public function scopeEmpresa(Builder $query, int $empresaId): Builder
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function scopeProducto(Builder $query, int $productoId): Builder
    {
        return $query->where('producto_id', $productoId);
    }

    public static function estadosPermitidos(): array
    {
        return [
            self::ESTADO_EN_STOCK,
            self::ESTADO_VENDIDO,
            self::ESTADO_DEVUELTO,
            self::ESTADO_EN_SERVICIO_TECNICO,
        ];
    }
}
