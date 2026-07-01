<?php

namespace App\Domains\Comercial\Models;

use Illuminate\Database\Eloquent\Model;

class TipoCambio extends Model
{
    protected $table = 'tipos_cambio';

    protected $fillable = ['fecha', 'moneda', 'valor_clp'];

    protected $casts = [
        'fecha' => 'date',
        'valor_clp' => 'decimal:4',
    ];

    public static function obtenerParaFecha(string $fecha, string $moneda): ?float
    {
        $registro = self::where('moneda', $moneda)
            ->whereDate('fecha', '<=', $fecha)
            ->orderBy('fecha', 'desc')
            ->first();

        return $registro ? (float) $registro->valor_clp : null;
    }
}
