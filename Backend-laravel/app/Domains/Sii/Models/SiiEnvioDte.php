<?php

namespace App\Domains\Sii\Models;

use App\Domains\Core\Models\Empresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F5.2 — Registro auditable de cada intento de subida del EnvioDTE al WS
 * DTEUpload del SII. Una fila por intento (no por DTE): si el mismo DTE
 * requiere reintento manual, se crean filas separadas con el mismo
 * dte_emitido_id pero distinto track_id.
 *
 * SEGURIDAD: request_body_completo_cifrado y respuesta_body_completo_cifrado
 * NUNCA en JSON. El $hidden los excluye automaticamente.
 */
class SiiEnvioDte extends Model
{
    public const ESTADO_PENDIENTE         = 'PENDIENTE';
    public const ESTADO_ENVIANDO          = 'ENVIANDO';
    public const ESTADO_ENVIADO           = 'ENVIADO';
    public const ESTADO_ACEPTADO          = 'ACEPTADO';
    public const ESTADO_ACEPTADO_REPAROS  = 'ACEPTADO_CON_REPAROS';
    public const ESTADO_RECHAZADO         = 'RECHAZADO';
    public const ESTADO_ERROR_TRANSPORTE  = 'ERROR_TRANSPORTE';
    public const ESTADO_ERROR_PERMANENTE  = 'ERROR_PERMANENTE';

    protected $table = 'sii_envio_dte';

    protected $fillable = [
        'empresa_id',
        'dte_emitido_id',
        'token_sesion_id',
        'ambiente_sii',
        'estado_envio',
        'track_id',
        'glosa_sii',
        'xml_envio_path',
        'xml_envio_hash_sha256',
        'request_body_completo_cifrado',
        'respuesta_body_completo_cifrado',
        'http_status_ultimo_envio',
        'intentos_envio',
        'fecha_envio',
    ];

    protected $hidden = [
        'request_body_completo_cifrado',
        'respuesta_body_completo_cifrado',
    ];

    protected $casts = [
        'fecha_envio'              => 'datetime',
        'intentos_envio'           => 'integer',
        'http_status_ultimo_envio' => 'integer',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function dteEmitido(): BelongsTo
    {
        return $this->belongsTo(SiiDteEmitido::class, 'dte_emitido_id');
    }

    public function tokenSesion(): BelongsTo
    {
        return $this->belongsTo(SiiTokenSesion::class, 'token_sesion_id');
    }

    public function scopeExitosos(Builder $query): Builder
    {
        return $query->whereIn('estado_envio', [
            self::ESTADO_ENVIADO,
            self::ESTADO_ACEPTADO,
            self::ESTADO_ACEPTADO_REPAROS,
        ]);
    }

    public function scopeFallidos(Builder $query): Builder
    {
        return $query->whereIn('estado_envio', [
            self::ESTADO_ERROR_TRANSPORTE,
            self::ESTADO_ERROR_PERMANENTE,
            self::ESTADO_RECHAZADO,
        ]);
    }

    public function scopePorEmpresa(Builder $query, int $empresaId): Builder
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function scopePorDte(Builder $query, int $dteId): Builder
    {
        return $query->where('dte_emitido_id', $dteId);
    }
}
