<?php

namespace App\Domains\Rrhh\Models;

use App\Domains\Rrhh\Exceptions\RrhhException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class TablaImpuestoUnico extends Model
{
    protected $table = 'tabla_impuesto_unico';

    protected $fillable = [
        'anio',
        'orden',
        'desde_utm',
        'hasta_utm',
        'tasa',
        'factor_deduccion_utm',
        'fuente',
    ];

    protected $casts = [
        'desde_utm' => 'decimal:4',
        'hasta_utm' => 'decimal:4',
        'tasa' => 'decimal:4',
        'factor_deduccion_utm' => 'decimal:4',
    ];

    public static function paraAnio(int $anio): Collection
    {
        return static::where('anio', $anio)->orderBy('orden')->get();
    }

    /**
     * Calcula el impuesto único dado la base tributable en pesos y el valor de la UTM.
     *
     * @param  int    $anio
     * @param  float  $baseTributablePesos
     * @param  float  $valorUtm
     * @return float  impuesto en pesos (redondeado)
     */
    public static function calcularImpuesto(int $anio, float $baseTributablePesos, float $valorUtm): float
    {
        if ($valorUtm <= 0) {
            return 0.0;
        }

        $baseEnUtm = $baseTributablePesos / $valorUtm;
        $tramos = static::paraAnio($anio);

        // Fix 7: tabla vacía es error de configuración, no 0 silencioso
        if ($tramos->isEmpty()) {
            throw RrhhException::regla("No existe tabla de impuesto único para el año {$anio}.");
        }

        foreach ($tramos as $tramo) {
            $hasta = $tramo->hasta_utm !== null ? (float) $tramo->hasta_utm : PHP_FLOAT_MAX;

            if ($baseEnUtm <= (float) $tramo->desde_utm) {
                continue;
            }

            if ($baseEnUtm <= $hasta) {
                $impuesto = ($baseTributablePesos * (float) $tramo->tasa)
                    - ((float) $tramo->factor_deduccion_utm * $valorUtm);

                return max(0.0, round($impuesto));
            }
        }

        return 0.0;
    }
}
