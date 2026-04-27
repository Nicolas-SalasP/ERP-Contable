<?php

namespace App\Domains\Comercial\Models;

use Illuminate\Database\Eloquent\Model;

class CotizacionDetalle extends Model
{
    protected $table = 'cotizacion_detalles';
    public $timestamps = false;

    protected $fillable = [
        'cotizacion_id',
        'producto_id',
        'producto_nombre',
        'descripcion',
        'cantidad',
        'precio',
        'precio_unitario', 
        'subtotal'
    ];

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class);
    }
}