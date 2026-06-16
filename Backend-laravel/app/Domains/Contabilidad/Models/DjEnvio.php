<?php

namespace App\Domains\Contabilidad\Models;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Traits\HasEmpresaScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DjEnvio extends Model
{
    use HasEmpresaScope;
    use SoftDeletes;

    const ESTADO_GENERADO    = 'GENERADO';
    const ESTADO_VALIDADO    = 'VALIDADO';
    const ESTADO_CON_ERRORES = 'CON_ERRORES';
    const ESTADO_DESCARGADO  = 'DESCARGADO';
    const ESTADO_PRESENTADO  = 'PRESENTADO';

    protected $table = 'dj_envios';

    protected $fillable = [
        'empresa_id',
        'codigo_dj',
        'anio',
        'estado',
        'cantidad_registros',
        'archivo_path',
        'folio_presentacion',
        'presentado_at',
        'errores_validacion',
        'anio_40_horas',
    ];

    protected $casts = [
        'errores_validacion' => 'array',
        'presentado_at'      => 'datetime',
    ];

    public function scopePorCodigo(Builder $query, string $codigo): Builder
    {
        return $query->where('codigo_dj', $codigo);
    }

    public function scopeDelAnio(Builder $query, int $anio): Builder
    {
        return $query->where('anio', $anio);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}
