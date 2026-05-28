<?php

namespace App\Domains\Sii\Models;

use App\Domains\Comercial\Models\FacturaDetalle;
use Database\Factories\Sii\SiiDteEmitidoDetalleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SiiDteEmitidoDetalle extends Model
{
    use HasFactory;

    protected $table = 'sii_dte_emitido_detalle';

    protected $fillable = [
        'dte_emitido_id',
        'numero_linea',
        'factura_detalle_id',
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
        'monto_item',
    ];

    protected $casts = [
        'numero_linea'    => 'integer',
        'cantidad'        => 'decimal:4',
        'precio_unitario' => 'decimal:4',
        'descuento_monto' => 'decimal:4',
        'recargo_monto'   => 'decimal:4',
        'descuento_pct'   => 'decimal:2',
        'recargo_pct'     => 'decimal:2',
        'monto_item'      => 'decimal:2',
        'exento'          => 'boolean',
    ];

    protected static function newFactory(): SiiDteEmitidoDetalleFactory
    {
        return SiiDteEmitidoDetalleFactory::new();
    }

    public function dte(): BelongsTo
    {
        return $this->belongsTo(SiiDteEmitido::class, 'dte_emitido_id');
    }

    public function facturaDetalle(): BelongsTo
    {
        return $this->belongsTo(FacturaDetalle::class, 'factura_detalle_id');
    }

    public function impuestosAdicionales(): HasMany
    {
        return $this->hasMany(SiiDteEmitidoImpuestoAdicional::class, 'dte_emitido_detalle_id');
    }
}
