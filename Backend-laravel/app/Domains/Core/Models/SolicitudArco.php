<?php

namespace App\Domains\Core\Models;

use App\Domains\Core\Traits\HasEmpresaScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Ley 21.719: registro de solicitudes ARCO+ (Acceso, Portabilidad, Supresión, Bloqueo) como evidencia ante la autoridad de protección de datos. */
class SolicitudArco extends Model
{
    use HasEmpresaScope;

    protected $table = 'solicitudes_arco';

    public const TIPO_ACCESO       = 'ACCESO';
    public const TIPO_PORTABILIDAD = 'PORTABILIDAD';
    public const TIPO_SUPRESION    = 'SUPRESION';
    public const TIPO_BLOQUEO      = 'BLOQUEO';

    public const ESTADO_COMPLETADA         = 'COMPLETADA';
    public const ESTADO_PARCIAL_RETENCION  = 'PARCIAL_RETENCION';
    public const ESTADO_RECHAZADA          = 'RECHAZADA';

    protected $fillable = [
        'empresa_id',
        'titular_type',
        'titular_id',
        'tipo',
        'estado',
        'solicitado_por',
        'motivo',
        'resultado',
    ];

    public function titular(): MorphTo
    {
        return $this->morphTo();
    }
}
