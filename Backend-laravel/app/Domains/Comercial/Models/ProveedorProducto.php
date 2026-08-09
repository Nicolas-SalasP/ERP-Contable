<?php

namespace App\Domains\Comercial\Models;

use App\Domains\Core\Traits\HasEmpresaScope;
use App\Domains\Inventario\Models\Producto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Costo de reposicion de un producto para un proveedor especifico. Un producto
 * puede tener varios proveedores; PricingService usa el mas barato entre los
 * vigentes para sugerir precio de venta.
 *
 * @property int $id
 * @property int $empresa_id
 * @property int $proveedor_id
 * @property int $producto_id
 * @property string|null $codigo_proveedor
 * @property string $costo_neto
 * @property string $moneda
 * @property \Illuminate\Support\Carbon $vigente_desde
 * @property bool $activo
 * @property-read \App\Domains\Comercial\Models\Proveedor $proveedor
 * @property-read \App\Domains\Inventario\Models\Producto $producto
 */
class ProveedorProducto extends Model
{
    use HasEmpresaScope;

    protected $table = 'proveedor_productos';

    protected $fillable = [
        'empresa_id',
        'proveedor_id',
        'producto_id',
        'codigo_proveedor',
        'costo_neto',
        'moneda',
        'vigente_desde',
        'activo',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'proveedor_id' => 'integer',
        'producto_id' => 'integer',
        'costo_neto' => 'decimal:4',
        'vigente_desde' => 'date',
        'activo' => 'boolean',
    ];

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
