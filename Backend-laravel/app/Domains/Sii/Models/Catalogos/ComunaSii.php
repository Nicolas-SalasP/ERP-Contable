<?php

namespace App\Domains\Sii\Models\Catalogos;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ComunaSii extends Model
{
    protected $table = 'sii_cat_comunas';

    protected $guarded = [];

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
