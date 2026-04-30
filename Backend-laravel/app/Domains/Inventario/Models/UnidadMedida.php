<?php

namespace App\Domains\Inventario\Models;

use Illuminate\Database\Eloquent\Model;

class UnidadMedida extends Model
{
    protected $table = 'inventario_unidades_medida';

    protected $fillable = [
        'codigo',
        'nombre',
        'codigo_sii',
        'permite_decimal',
        'activo',
    ];

    protected $casts = [
        'permite_decimal' => 'boolean',
        'activo' => 'boolean',
    ];
}