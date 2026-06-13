<?php

namespace App\Domains\Rrhh\Models;

use Illuminate\Database\Eloquent\Model;

class IndicadorMensual extends Model
{
    protected $table = 'indicadores_mensuales';

    protected $fillable = [
        'anio',
        'mes',
        'uf_valor',
        'utm_valor',
        'uta_valor',
        'fuente',
    ];

    protected $casts = [
        'uf_valor' => 'decimal:4',
        'utm_valor' => 'decimal:2',
        'uta_valor' => 'decimal:2',
    ];

    public static function paraPeriodo(int $anio, int $mes): ?self
    {
        return static::where('anio', $anio)->where('mes', $mes)->first();
    }
}
