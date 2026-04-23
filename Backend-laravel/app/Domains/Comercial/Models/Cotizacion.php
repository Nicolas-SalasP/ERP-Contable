<?php

namespace App\Domains\Comercial\Models;

use Illuminate\Database\Eloquent\Model;
use App\Domains\Core\Models\Empresa;

class Cotizacion extends Model
{
    protected $table = 'cotizaciones';
    const UPDATED_AT = null;

    protected $fillable = [
        'cliente_id',
        'nombre_cliente',
        'fecha_emision',
        'total',
        'estado_id',
        'empresa_id',
        'es_afecta',
        'validez',
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'total' => 'decimal:2',
        'es_afecta' => 'boolean',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function estado()
    {
        return $this->belongsTo(EstadoCotizacion::class, 'estado_id');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function detalles()
    {
        return $this->hasMany(CotizacionDetalle::class);
    }
}