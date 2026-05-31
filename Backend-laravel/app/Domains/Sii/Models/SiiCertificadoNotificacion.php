<?php

namespace App\Domains\Sii\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiiCertificadoNotificacion extends Model
{
    public const ESTADO_ENVIADA = 'enviada';
    public const ESTADO_FALLIDA = 'fallida';

    protected $table = 'sii_certificado_notificaciones';

    protected $fillable = [
        'certificado_id',
        'nivel',
        'enviada_a',
        'dias_para_vencer',
        'estado_envio',
        'error_mensaje',
        'enviada_at',
    ];

    protected $casts = [
        'certificado_id'    => 'integer',
        'dias_para_vencer'  => 'integer',
        'enviada_at'        => 'datetime',
    ];

    public function certificado(): BelongsTo
    {
        return $this->belongsTo(SiiCertificadoEmpresa::class, 'certificado_id');
    }

    public function scopeEnviadas(Builder $query): Builder
    {
        return $query->where('estado_envio', self::ESTADO_ENVIADA);
    }

    public function scopeFallidas(Builder $query): Builder
    {
        return $query->where('estado_envio', self::ESTADO_FALLIDA);
    }

    public function scopeHoy(Builder $query): Builder
    {
        return $query->whereDate('enviada_at', now()->toDateString());
    }
}
