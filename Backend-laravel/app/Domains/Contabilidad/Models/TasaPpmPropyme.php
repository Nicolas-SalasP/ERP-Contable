<?php

namespace App\Domains\Contabilidad\Models;

use App\Domains\Contabilidad\Exceptions\DjException;
use Illuminate\Database\Eloquent\Model;

class TasaPpmPropyme extends Model
{
    protected $table = 'tasas_ppm_propyme';

    protected $fillable = ['anio', 'tasa_base_pct', 'tasa_sobre_50000uf_pct'];

    public static function porAnio(int $anio): self
    {
        $tasa = self::where('anio', $anio)->first();

        if (! $tasa) {
            throw DjException::regla("No existe tasa PPM Propyme configurada para el año {$anio}.");
        }

        return $tasa;
    }
}
