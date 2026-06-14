<?php

namespace App\Domains\Core\Models;

use Illuminate\Database\Eloquent\Model;

class PoliticaPrivacidad extends Model
{
    protected $table = 'politicas_privacidad';

    protected $fillable = [
        'version',
        'titulo',
        'contenido',
        'vigente_desde',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'activa'        => 'boolean',
            'vigente_desde' => 'date',
        ];
    }
}
