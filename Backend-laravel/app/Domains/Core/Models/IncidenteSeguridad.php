<?php

namespace App\Domains\Core\Models;

use App\Domains\Core\Traits\HasEmpresaScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Fase 6 — Registro de incidentes de seguridad y brechas de datos personales.
 *
 * Ley 21.663: alerta temprana 3h / reporte 72h al CSIRT.
 * Ley 21.719: notificación a la Agencia de Protección de Datos + titulares.
 *
 * El campo categorias_datos_afectados almacena SOLO categorías tipológicas
 * (ej. "datos de salud, RUT") — NUNCA datos personales reales (ver Ley 21.719).
 */
class IncidenteSeguridad extends Model
{
    use HasEmpresaScope;

    protected $table = 'incidentes_seguridad';

    // -------------------------------------------------------------------------
    // Valores permitidos para severidad
    // -------------------------------------------------------------------------
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

    // -------------------------------------------------------------------------
    // Valores permitidos para estado
    // -------------------------------------------------------------------------
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
