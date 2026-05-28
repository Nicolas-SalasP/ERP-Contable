<?php

namespace App\Domains\Sii\Models\Catalogos;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ImpuestoSii extends Model
{
    public const CODIGO_IVA = 14;

    protected $table = 'sii_cat_impuestos';

    protected $guarded = [];

    protected $casts = [
        'tasa'         => 'decimal:2',
        'es_adicional' => 'boolean',
        'activo'       => 'boolean',
    ];

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
