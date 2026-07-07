<?php

namespace App\Domains\Core\Models;

use App\Domains\Core\Traits\HasEmpresaScope;
use Illuminate\Database\Eloquent\Model;

/** Incidentes de seguridad (Ley 21.663/21.719): categorias_datos_afectados guarda SOLO categorías tipológicas, NUNCA datos personales reales. */
class IncidenteSeguridad extends Model
{
    use HasEmpresaScope;

    protected $table = 'incidentes_seguridad';

    public const SEVERIDAD_BAJA    = 'BAJA';
    public const SEVERIDAD_MEDIA   = 'MEDIA';
    public const SEVERIDAD_ALTA    = 'ALTA';
    public const SEVERIDAD_CRITICA = 'CRITICA';

    public const SEVERIDADES = [
        self::SEVERIDAD_BAJA,
        self::SEVERIDAD_MEDIA,
        self::SEVERIDAD_ALTA,
        self::SEVERIDAD_CRITICA,
    ];

    public const ESTADO_ABIERTO   = 'ABIERTO';
    public const ESTADO_CONTENIDO = 'CONTENIDO';
    public const ESTADO_CERRADO   = 'CERRADO';

    public const ESTADOS = [
        self::ESTADO_ABIERTO,
        self::ESTADO_CONTENIDO,
        self::ESTADO_CERRADO,
    ];

    protected $fillable = [
        'empresa_id',
        'titulo',
        'descripcion',
        'severidad',
        'origen',
        'categorias_datos_afectados',
        'n_afectados_estimado',
        'detectado_at',
        'alerta_temprana_at',
        'reporte_csirt_at',
        'notificacion_agencia_at',
        'notificacion_afectados_at',
        'estado',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'detectado_at'               => 'datetime',
            'alerta_temprana_at'         => 'datetime',
            'reporte_csirt_at'           => 'datetime',
            'notificacion_agencia_at'    => 'datetime',
            'notificacion_afectados_at'  => 'datetime',
            'n_afectados_estimado'       => 'integer',
        ];
    }
}
