<?php

namespace App\Domains\Sii\Models;

use App\Domains\Core\Models\Empresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Certificado digital .pfx por empresa.
 *
 * SEGURIDAD: pfx_cifrado y password_cifrada NUNCA deben aparecer en JSON.
 * El $hidden de Eloquent los excluye automaticamente de toJson()/toArray().
 */
class SiiCertificadoEmpresa extends Model
{
    public const ESTADO_ACTIVO     = 'activo';
    public const ESTADO_CUARENTENA = 'cuarentena';
    public const ESTADO_REVOCADO   = 'revocado';

    // Niveles de alerta (7) consumidos por MonitorearVencimientoCertificadosJob.
    public const ALERTA_VENCIDO     = 'VENCIDO';        // dias < 0
    public const ALERTA_CRITICA_T1  = 'CRITICA_T1';     // 0..1
    public const ALERTA_CRITICA_T7  = 'CRITICA_T7';     // 2..7
    public const ALERTA_ALTA_T15    = 'ALTA_T15';       // 8..15
    public const ALERTA_MEDIA_T30   = 'MEDIA_T30';      // 16..30
    public const ALERTA_BAJA_T60    = 'BAJA_T60';       // 31..60
    public const ALERTA_SIN_ALERTA  = 'SIN_ALERTA';     // > 60

    protected $table = 'sii_certificado_empresa';

    protected $fillable = [
        'empresa_id',
        'pfx_cifrado',
        'password_cifrada',
        'subject_rut',
        'subject_common_name',
        'issuer_common_name',
        'valido_desde',
        'valido_hasta',
        'fingerprint_sha256',
        'estado',
    ];

    protected $hidden = [
        'pfx_cifrado',
        'password_cifrada',
    ];

    protected $casts = [
        'valido_desde' => 'datetime',
        'valido_hasta' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function notificaciones(): HasMany
    {
        return $this->hasMany(SiiCertificadoNotificacion::class, 'certificado_id');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estado', self::ESTADO_ACTIVO);
    }

    public function scopePorEmpresa(Builder $query, int $empresaId): Builder
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function scopePorVencer(Builder $query, int $dias = 30): Builder
    {
        $ahora  = now();
        $limite = now()->addDays($dias);

        return $query
            ->where('estado', self::ESTADO_ACTIVO)
            ->whereBetween('valido_hasta', [$ahora, $limite]);
    }

    public function isVigente(): bool
    {
        if ($this->valido_hasta === null) {
            return false;
        }

        return $this->valido_hasta->isFuture();
    }

    /**
     * Dias restantes hasta el vencimiento. Positivo = futuro, negativo = pasado.
     * Calculo basado en epoch para evitar ambiguedades de signo entre versiones
     * de Carbon.
     */
    public function diasParaVencer(): int
    {
        if ($this->valido_hasta === null) {
            return 0;
        }

        $diffSegundos = $this->valido_hasta->getTimestamp() - now()->getTimestamp();

        return (int) floor($diffSegundos / 86400);
    }

    /**
     * Nivel de alerta segun la matriz definida en OT-F2.3 (7 niveles).
     */
    public function nivelAlerta(): string
    {
        $dias = $this->diasParaVencer();

        if ($dias < 0)   return self::ALERTA_VENCIDO;
        if ($dias <= 1)  return self::ALERTA_CRITICA_T1;
        if ($dias <= 7)  return self::ALERTA_CRITICA_T7;
        if ($dias <= 15) return self::ALERTA_ALTA_T15;
        if ($dias <= 30) return self::ALERTA_MEDIA_T30;
        if ($dias <= 60) return self::ALERTA_BAJA_T60;

        return self::ALERTA_SIN_ALERTA;
    }

    /**
     * Existe ALGUNA notificacion enviada con este nivel para este cert?
     * Para niveles one-shot (BAJA_T60, MEDIA_T30, ALTA_T15).
     */
    public function haEnviadoNivel(string $nivel): bool
    {
        return $this->notificaciones()
            ->where('nivel', $nivel)
            ->where('estado_envio', SiiCertificadoNotificacion::ESTADO_ENVIADA)
            ->exists();
    }

    /**
     * Existe notificacion enviada HOY con este nivel?
     * Para niveles diarios (CRITICA_T7, CRITICA_T1, VENCIDO).
     */
    public function haEnviadoNivelHoy(string $nivel): bool
    {
        return $this->notificaciones()
            ->where('nivel', $nivel)
            ->where('estado_envio', SiiCertificadoNotificacion::ESTADO_ENVIADA)
            ->whereDate('enviada_at', now()->toDateString())
            ->exists();
    }
}
