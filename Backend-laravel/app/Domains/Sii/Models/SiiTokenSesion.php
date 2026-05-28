<?php

namespace App\Domains\Sii\Models;

use App\Domains\Core\Models\Empresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sesion autenticada contra el WS SII (F5.1).
 *
 * SEGURIDAD: token NUNCA debe aparecer en JSON. El $hidden lo excluye
 * automaticamente de toJson()/toArray().
 */
class SiiTokenSesion extends Model
{
    public const AMBIENTE_CERTIFICACION = 'certificacion';
    public const AMBIENTE_PRODUCCION    = 'produccion';

    protected $table = 'sii_token_sesion';

    protected $fillable = [
        'empresa_id',
        'ambiente',
        'token',
        'semilla_usada',
        'hash_firma_semilla',
        'fecha_obtencion',
        'fecha_expiracion',
        'ultimo_uso_en',
        'intentos_uso',
    ];

    protected $hidden = [
        'token',
    ];

    protected $casts = [
        'fecha_obtencion'  => 'datetime',
        'fecha_expiracion' => 'datetime',
        'ultimo_uso_en'    => 'datetime',
        'intentos_uso'     => 'integer',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function scopeActiva(Builder $query): Builder
    {
        return $query->where('fecha_expiracion', '>', now());
    }

    public function scopePorEmpresa(Builder $query, int $empresaId): Builder
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function scopePorAmbiente(Builder $query, string $ambiente): Builder
    {
        return $query->where('ambiente', $ambiente);
    }

    /**
     * Incrementa el contador de usos y actualiza ultimo_uso_en a now.
     * Util para auditoria de cuanto se reusa una sesion.
     */
    public function registrarUso(): void
    {
        $this->increment('intentos_uso');
        $this->update(['ultimo_uso_en' => now()]);
    }

    public function estaVigente(): bool
    {
        return $this->fecha_expiracion !== null && $this->fecha_expiracion->isFuture();
    }
}
