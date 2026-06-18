<?php

namespace App\Domains\Comercial\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CotizacionDetalle extends Model
{
    use SoftDeletes;

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