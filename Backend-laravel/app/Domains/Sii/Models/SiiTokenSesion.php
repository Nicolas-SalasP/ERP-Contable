<?php

namespace App\Domains\Sii\Models;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Traits\HasEmpresaScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Sesion autenticada contra el WS SII (F5.1); SEGURIDAD: token nunca debe aparecer en JSON, el $hidden lo excluye automaticamente de toJson()/toArray().
 *
 * @property int $id
 * @property int $empresa_id
 * @property string $ambiente
 * @property string $token
 * @property Carbon $fecha_obtencion
 * @property Carbon $fecha_expiracion
 * @property int $intentos_uso
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SiiTokenSesion extends Model
{
    use HasEmpresaScope;

    public const AMBIENTE_CERTIFICACION = 'certificacion';

    public const AMBIENTE_PRODUCCION = 'produccion';

    /** Boleta (39/41) requiere un token propio, distinto al de Factura/NC/ND (exigencia del SII). */
    public const AMBITO_FACTURA = 'factura';

    public const AMBITO_BOLETA = 'boleta';

    protected $table = 'sii_token_sesion';

    protected $fillable = [
        'empresa_id',
        'ambiente',
        'ambito',
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
        'fecha_obtencion' => 'datetime',
        'fecha_expiracion' => 'datetime',
        'ultimo_uso_en' => 'datetime',
        'intentos_uso' => 'integer',
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

    public function scopePorAmbito(Builder $query, string $ambito): Builder
    {
        return $query->where('ambito', $ambito);
    }

    /** Incrementa el contador de usos y actualiza ultimo_uso_en a now; util para auditoria de cuanto se reusa una sesion. */
    public function registrarUso(): void
    {
        $this->increment('intentos_uso');
        $this->update(['ultimo_uso_en' => now()]);
    }

    public function estaVigente(): bool
    {
        return $this->fecha_expiracion->isFuture();
    }
}
