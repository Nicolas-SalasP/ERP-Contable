<?php

namespace App\Domains\Comercial\Models;

use App\Domains\Comercial\Exceptions\ComercialException;
use Illuminate\Database\Eloquent\Model;

class TasaRetencionHonorarios extends Model
{
    protected $table = 'tasas_retencion_honorarios';

    protected $fillable = ['anio', 'tasa_pct'];

    protected $casts = [
        'tasa_pct' => 'decimal:2',
    ];

    /**
     * Devuelve la tasa de retención para un año exacto.
     * Lanza ComercialException si no existe configuración para ese año.
     */
    public static function porAnio(int $anio): self
    {
        $tasa = static::where('anio', $anio)->first();

        if ($tasa === null) {
            throw ComercialException::regla("Sin tasa de retención configurada para el año {$anio}.");
        }

        return $tasa;
    }
}
