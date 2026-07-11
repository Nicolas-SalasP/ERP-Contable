<?php

namespace App\Domains\Rrhh\Models;

use Illuminate\Database\Eloquent\Model;

/** Catálogo de organismos administradores Ley 16.744 (ACHS/ISL/IST/Mutual CChC). Fuente única de código Previred (campo 59) y código LRE (1152); ver migración 2026_07_10_000001. */
class Mutualidad extends Model
{
    protected $table = 'mutualidades';

    protected $fillable = [
        'nombre',
        'codigo_previred',
        'codigo_lre',
    ];
}
