<?php

namespace App\Domains\Rrhh\Models;

use App\Domains\Contabilidad\Models\CentroCosto;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Traits\HasEmpresaScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contrato extends Model
{
    use HasEmpresaScope, SoftDeletes;

    protected $table = 'contratos';

    protected $fillable = [
        'empresa_id',
        'empleado_id',
        'tipo',
        'fecha_inicio',
        'fecha_termino',
        'cargo',
        'departamento',
        'centro_costo_id',
        'horas_semana',
        'tipo_jornada',
        'sueldo_base',
        'causal_termino',
        'fecha_termino_real',
        'observaciones_termino',
        'estado',
        'es_contrato_activo',
        'observaciones',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_termino' => 'date',
        'fecha_termino_real' => 'date',
        'sueldo_base' => 'decimal:2',
        'es_contrato_activo' => 'boolean',
    ];

    // Tipo de contrato para AFC (determina tasas de cesantía)
    public function esIndefindo(): bool
    {
        return $this->tipo === 'INDEFINIDO';
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    public function centroCosto(): BelongsTo
    {
        return $this->belongsTo(CentroCosto::class);
    }

    public function haberes(): HasMany
    {
        return $this->hasMany(HaberDescuentoContrato::class)->where('activo', true);
    }

    public function liquidaciones(): HasMany
    {
        return $this->hasMany(Liquidacion::class);
    }
}
