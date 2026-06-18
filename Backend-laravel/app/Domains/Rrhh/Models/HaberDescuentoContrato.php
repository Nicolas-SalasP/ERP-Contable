<?php

namespace App\Domains\Rrhh\Models;

use App\Domains\Core\Traits\HasEmpresaScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HaberDescuentoContrato extends Model
{
    use HasEmpresaScope;

    protected $table = 'haber_descuento_contratos';

    protected $fillable = [
        'empresa_id',
        'contrato_id',
        'nombre',
        'concepto_remuneracion_id',
        'tipo',
        'modalidad_valor',
        'monto',
        'porcentaje',
        'activo',
        'vigente_desde',
        'vigente_hasta',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'porcentaje' => 'decimal:4',
        'activo' => 'boolean',
        'vigente_desde' => 'date',
        'vigente_hasta' => 'date',
    ];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function concepto(): BelongsTo
    {
        return $this->belongsTo(ConceptoRemuneracion::class, 'concepto_remuneracion_id');
    }
}
