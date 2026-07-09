<?php

namespace App\Domains\Alertas\Models;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Core\Traits\HasEmpresaScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alerta extends Model
{
    use HasEmpresaScope;

    protected $table = 'alertas';

    public const NIVEL_INFO = 'info';

    public const NIVEL_ADVERTENCIA = 'advertencia';

    public const NIVEL_CRITICO = 'critico';

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_ENVIADA = 'enviada';

    public const ESTADO_RESUELTA = 'resuelta';

    public const ESTADO_DESCARTADA = 'descartada';

    protected $fillable = [
        'empresa_id',
        'tipo',
        'nivel',
        'entidad_type',
        'entidad_id',
        'mensaje',
        'datos',
        'estado',
        'enviada_at',
        'resuelta_at',
        'resuelta_por',
        'error_mensaje',
    ];

    protected $casts = [
        'datos' => 'array',
        'enviada_at' => 'datetime',
        'resuelta_at' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function resueltaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelta_por');
    }

    public function scopePendientes($query)
    {
        return $query->whereIn('estado', [self::ESTADO_PENDIENTE, self::ESTADO_ENVIADA]);
    }
}
