<?php

namespace App\Domains\Sii\Models\Catalogos;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UnidadSii extends Model
{
    protected $table = 'sii_cat_unidades';

    protected $guarded = [];

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
