<?php

namespace App\Domains\Comercial\Models;

use Illuminate\Database\Eloquent\Model;

class EstadoCotizacion extends Model
{
    protected $table = 'estado_cotizaciones';
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'descripcion',
    ];

    public function cotizaciones()
    {
        return $this->hasMany(Cotizacion::class, 'estado_id');
    }
}