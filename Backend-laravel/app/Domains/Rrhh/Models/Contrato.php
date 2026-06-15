<?php

namespace App\Domains\Rrhh\Models;

use App\Domains\Contabilidad\Models\CentroCosto;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Traits\HasEmpresaScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use ParagonIE\CipherSweet\EncryptedRow;
use Spatie\LaravelCipherSweet\Concerns\UsesCipherSweet;
use Spatie\LaravelCipherSweet\Contracts\CipherSweetEncrypted;

class Contrato extends Model implements CipherSweetEncrypted
{
    use HasEmpresaScope, SoftDeletes, UsesCipherSweet;

    protected $table = 'contratos';

    protected $fillable = [
        'empresa_id',
        'empleado_id',
        'tipo',
        'fecha_inicio',
        'fecha_termino',
        'cargo',
        'departamento',
        'centro_costo_id',
        'horas_semana',
        'tipo_jornada',
        'sueldo_base',
        'causal_termino',
        'fecha_termino_real',
        'observaciones_termino',
        'estado',
        'es_contrato_activo',
        'observaciones',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_termino' => 'date',
        'fecha_termino_real' => 'date',
        'sueldo_base' => 'decimal:2',
        'es_contrato_activo' => 'boolean',
    ];

    // ---------- Cifrado con CipherSweet (Ley 21.719 — Fase 2c) ----------

    public static function configureCipherSweet(EncryptedRow $encryptedRow): void
    {
        // Fase 2c: sueldo_base cifrado en reposo.
        // El cast 'decimal:2' sigue activo: CipherSweet desencripta el string almacenado
        // y Eloquent lo re-tipifica como string numérico con precisión decimal al acceder.
        $encryptedRow->addOptionalTextField('sueldo_base');
    }

    /**
     * Compara atributos para determinar si están sucios.
     *
     * Override necesario para campos cifrados con CipherSweet que también tienen un
     * cast primitivo (decimal:2). Sin este override, Eloquent llama a castAttribute()
     * sobre el ciphertext durante getDirty() → syncChanges() → MathException.
     *
     * Para los campos gestionados por CipherSweet usamos comparación de string cruda:
     * son equivalentes si el valor sin castear es igual (decrypted == decrypted),
     * o si ambos son null. El ciphertext per-se siempre difiere del plaintext original
     * pero eso ya lo maneja excludeNonChangedEncryptedAttributesFromChanges().
     */
    public function originalIsEquivalent($key): bool
    {
        $encryptedFields = static::getCipherSweetEncryptedRow()->listEncryptedFields();

        if (in_array($key, $encryptedFields, true)) {
            if (! array_key_exists($key, $this->original)) {
                return false;
            }
            // Comparación raw: ambos son strings (plaintext o null).
            // Cuando saving acaba de cifrar, el attribute es ciphertext y el original
            // es plaintext → siempre distintos, lo cual es correcto (el campo fue "tocado").
            // La limpieza final la hace excludeNonChangedEncryptedAttributesFromChanges().
            return $this->attributes[$key] === $this->original[$key];
        }

        return parent::originalIsEquivalent($key);
    }

    // Tipo de contrato para AFC (determina tasas de cesantía)
    public function esIndefindo(): bool
    {
        return $this->tipo === 'INDEFINIDO';
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    public function centroCosto(): BelongsTo
    {
        return $this->belongsTo(CentroCosto::class);
    }

    public function haberes(): HasMany
    {
        return $this->hasMany(HaberDescuentoContrato::class)->where('activo', true);
    }

    public function liquidaciones(): HasMany
    {
        return $this->hasMany(Liquidacion::class);
    }
}
