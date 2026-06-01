<?php

namespace App\Domains\Comercial\Models;

use App\Domains\Inventario\Models\Producto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacturaDetalle extends Model
{
    protected $table = 'facturas_detalles';

    protected $fillable = [
        'factura_id',
        'numero_linea',
        'producto_id',
        'codigo_item',
        'tipo_codigo',
        'nombre_item',
        'descripcion',
        'cantidad',
        'unidad_medida',
        'precio_unitario',
        'descuento_pct',
        'descuento_monto',
        'recargo_pct',
        'recargo_monto',
        'exento',
        'codigo_impuesto_adicional',
        'monto_item',
    ];

    protected $casts = [
        'factura_id'                => 'integer',
        'numero_linea'              => 'integer',
        'producto_id'               => 'integer',
        'cantidad'                  => 'decimal:4',
        'precio_unitario'           => 'decimal:4',
        'descuento_monto'           => 'decimal:4',
        'recargo_monto'             => 'decimal:4',
        'descuento_pct'             => 'decimal:2',
        'recargo_pct'               => 'decimal:2',
        'monto_item'                => 'decimal:2',
        'exento'                    => 'boolean',
        'codigo_impuesto_adicional' => 'integer',
    ];

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function scopePorFactura(Builder $query, int $facturaId): Builder
    {
        return $query->where('factura_id', $facturaId);
    }
}
