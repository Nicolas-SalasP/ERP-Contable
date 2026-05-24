<?php

namespace App\Domains\Sii\Models;

use App\Domains\Core\Models\Empresa;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon as IlluminateCarbon;

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

    public const ALERTA_CRITICA    = 'critica';     // <= 7 dias
    public const ALERTA_ALTA       = 'alta';        // <= 15 dias
    public const ALERTA_MEDIA      = 'media';       // <= 30 dias
    public const ALERTA_BAJA       = 'baja';        // <= 60 dias
    public const ALERTA_SIN_ALERTA = 'sin_alerta';  // > 60 dias

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

    public function diasParaVencer(): int
    {
        if ($this->valido_hasta === null) {
            return 0;
        }

        return (int) floor(now()->diffInDays($this->valido_hasta, false));
    }

    public function nivelAlerta(): string
    {
        if (! $this->isVigente()) {
            return self::ALERTA_CRITICA;
        }

        $dias = $this->diasParaVencer();

        return match (true) {
            $dias <= 7  => self::ALERTA_CRITICA,
            $dias <= 15 => self::ALERTA_ALTA,
            $dias <= 30 => self::ALERTA_MEDIA,
            $dias <= 60 => self::ALERTA_BAJA,
            default     => self::ALERTA_SIN_ALERTA,
        };
    }
}
