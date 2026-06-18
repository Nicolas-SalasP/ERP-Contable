<?php

namespace App\Domains\Rrhh\Models;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Traits\HasEmpresaScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LreEnvio extends Model
{
    use HasEmpresaScope, SoftDeletes;

    protected $table = 'lre_envios';

    public const ESTADO_GENERADO         = 'GENERADO';
    public const ESTADO_VALIDADO         = 'VALIDADO';
    public const ESTADO_CONFIRMADO_DT    = 'CONFIRMADO_DT';
    public const ESTADO_ERROR_VALIDACION = 'ERROR_VALIDACION';

    protected $fillable = [
        'empresa_id',
        'anio',
        'mes',
        'estado',
        'cantidad_trabajadores',
        'archivo_path',
        'numero_confirmacion_dt',
        'errores_validacion',
        'confirmado_at',
    ];

    protected $casts = [
        'errores_validacion' => 'array',
        'confirmado_at'      => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function scopeDelPeriodo(Builder $query, int $anio, int $mes): Builder
    {
        return $query->where('anio', $anio)->where('mes', $mes);
    }
}
