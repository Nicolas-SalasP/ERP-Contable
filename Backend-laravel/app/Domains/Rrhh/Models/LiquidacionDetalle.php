<?php

namespace App\Domains\Rrhh\Models;

use App\Domains\Core\Traits\HasEmpresaScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiquidacionDetalle extends Model
{
    use HasEmpresaScope;

    protected $table = 'liquidacion_detalles';

    protected $fillable = [
        'empresa_id',
        'liquidacion_id',
        'concepto_remuneracion_id',
        'codigo_concepto',
        'nombre_concepto',
        'tipo',
        'base_calculo',
        'tasa_aplicada',
        'monto',
        'orden',
    ];

    protected $casts = [
        'base_calculo' => 'decimal:2',
        'tasa_aplicada' => 'decimal:4',
        'monto' => 'decimal:2',
    ];

    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(Liquidacion::class);
    }

    public function concepto(): BelongsTo
    {
        return $this->belongsTo(ConceptoRemuneracion::class, 'concepto_remuneracion_id');
    }
}
