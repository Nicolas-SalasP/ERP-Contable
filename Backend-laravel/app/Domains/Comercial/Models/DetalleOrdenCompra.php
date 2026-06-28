<?php

namespace App\Domains\Comercial\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $orden_compra_id
 * @property string $producto_descripcion
 * @property string|null $codigo_producto
 * @property string $cantidad
 * @property string $unidad
 * @property string $precio_unitario
 * @property string $subtotal
 * @property string $cantidad_recibida
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Domains\Comercial\Models\OrdenCompra $ordenCompra
 */
class DetalleOrdenCompra extends Model
{
    protected $table = 'detalle_ordenes_compra';

    protected $fillable = [
        'orden_compra_id',
        'producto_descripcion',
        'codigo_producto',
        'cantidad',
        'unidad',
        'precio_unitario',
        'subtotal',
        'cantidad_recibida',
    ];

    protected $casts = [
        'cantidad' => 'decimal:3',
        'precio_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'cantidad_recibida' => 'decimal:3',
    ];

    public function ordenCompra(): BelongsTo
    {
        return $this->belongsTo(OrdenCompra::class, 'orden_compra_id');
    }
}
