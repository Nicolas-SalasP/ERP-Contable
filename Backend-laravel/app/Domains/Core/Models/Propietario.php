<?php

namespace App\Domains\Core\Models;

use App\Domains\Core\Scopes\EmpresaScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Propietario extends Model
{
    use SoftDeletes;

    protected $table = 'propietarios';

    protected $fillable = [
        'empresa_id',
        'rut',
        'nombre',
        'porcentaje_participacion',
    ];

    protected $casts = [
        'porcentaje_participacion' => 'float',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new EmpresaScope);
    }
}
