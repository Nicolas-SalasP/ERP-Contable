<?php

namespace App\Domains\Rrhh\Models;

use App\Domains\Core\Models\User;
use App\Domains\Core\Traits\HasEmpresaScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitudVacaciones extends Model
{
    use HasEmpresaScope;

    protected $table = 'solicitudes_vacaciones';

    public const ESTADO_PENDIENTE = 'PENDIENTE';
    public const ESTADO_APROBADA = 'APROBADA';
    public const ESTADO_RECHAZADA = 'RECHAZADA';
    public const ESTADO_ANULADA = 'ANULADA';

    protected $fillable = [
        'empresa_id',
        'empleado_id',
        'contrato_id',
        'fecha_desde',
        'fecha_hasta',
        'dias_habiles',
        'estado',
        'observacion',
        'motivo_rechazo',
        'motivo_anulacion',
        'solicitado_por',
        'resuelto_por',
        'resuelto_at',
    ];

    protected $casts = [
        'fecha_desde' => 'date',
        'fecha_hasta' => 'date',
        'dias_habiles' => 'decimal:4',
        'resuelto_at' => 'datetime',
    ];

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function solicitadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por');
    }

    public function resueltoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelto_por');
    }
}
