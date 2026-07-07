<?php

namespace App\Domains\Rrhh\Models;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Traits\HasEmpresaScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use ParagonIE\CipherSweet\BlindIndex;
use ParagonIE\CipherSweet\EncryptedRow;
use Spatie\LaravelCipherSweet\Concerns\UsesCipherSweet;
use Spatie\LaravelCipherSweet\Contracts\CipherSweetEncrypted;

class Empleado extends Model implements CipherSweetEncrypted
{
    use HasEmpresaScope, SoftDeletes, UsesCipherSweet;

    protected $table = 'empleados';

    protected $fillable = [
        'empresa_id',
        'rut',
        'nombres',
        'apellido_paterno',
        'apellido_materno',
        'fecha_nacimiento',
        'sexo',
        'nacionalidad',
        'estado_civil',
        'email',
        'telefono',
        'direccion',
        'ciudad',
        'region',
        'pais',
        'afp',
        'tipo_salud',
        'isapre_nombre',
        'isapre_plan_uf',
        'isapre_cotizacion_adicional_pct',
        'banco_nombre',
        'banco_tipo_cuenta',
        'banco_numero_cuenta_cifrado',
        'estado',
        'fecha_ingreso_empresa',
        'observaciones',
        'anonimizado_at',
        'bloqueado_at',
        'comuna_codigo_lre',
        'organismo_mutual_codigo',
        'ccaf_codigo',
        'pensionado_vejez',
        'pensionado_invalidez',
    ];

    // Datos bancarios nunca salen en serialización (Ley 21.719)
    protected $hidden = [
        'banco_numero_cuenta_cifrado',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'fecha_ingreso_empresa' => 'date',
        'isapre_plan_uf' => 'decimal:4',
        'isapre_cotizacion_adicional_pct' => 'decimal:2',
        'anonimizado_at' => 'datetime',
        'bloqueado_at' => 'datetime',
    ];

    // ---------- Cifrado con CipherSweet (Ley 21.719 — Fase 2a/2b) ----------

    public static function configureCipherSweet(EncryptedRow $encryptedRow): void
    {
        $encryptedRow
            // Fase 2b: RUT cifrado con blind index para búsqueda exacta y control de unicidad.
            ->addTextField('rut')
            ->addBlindIndex('rut', new BlindIndex('empleado_rut_index'))
            // Fase 2a: campos de contacto.
            ->addOptionalTextField('email')
            ->addOptionalTextField('telefono')
            ->addOptionalTextField('direccion')
            // Fase 2c: fecha de nacimiento cifrada (dato sensible Ley 21.719); el cast 'date' sigue activo porque CipherSweet desencripta el string y Eloquent lo re-tipifica como Carbon al acceder.
            ->addOptionalTextField('fecha_nacimiento');
    }

    /** Override necesario para campos cifrados con cast primitivo (date): sin esto, Eloquent llama a fromDateTime() sobre el ciphertext durante getDirty() y marcaría siempre como dirty aunque no lo esté. */
    public function originalIsEquivalent($key): bool
    {
        $encryptedFields = static::getCipherSweetEncryptedRow()->listEncryptedFields();

        if (in_array($key, $encryptedFields, true)) {
            if (! array_key_exists($key, $this->original)) {
                return false;
            }
            return $this->attributes[$key] === $this->original[$key];
        }

        return parent::originalIsEquivalent($key);
    }

    // ---------- Nombre completo ----------

    public function getNombreCompletoAttribute(): string
    {
        $partes = array_filter([
            $this->nombres,
            $this->apellido_paterno,
            $this->apellido_materno,
        ]);
        return implode(' ', $partes);
    }

    // ---------- Cifrado de datos bancarios (Ley 21.719) ----------

    public function setBancoNumeroCuentaCifradoAttribute(?string $valor): void
    {
        $this->attributes['banco_numero_cuenta_cifrado'] = $valor
            ? Crypt::encryptString($valor)
            : null;
    }

    public function getBancoNumeroCuentaAttribute(): ?string
    {
        if (empty($this->attributes['banco_numero_cuenta_cifrado'])) {
            return null;
        }
        try {
            return Crypt::decryptString($this->attributes['banco_numero_cuenta_cifrado']);
        } catch (\Throwable) {
            return null;
        }
    }

    // ---------- Relaciones ----------

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class);
    }

    public function contratoActivo(): HasMany
    {
        return $this->hasMany(Contrato::class)
            ->where('es_contrato_activo', true)
            ->where(function ($q) {
                $q->whereNull('fecha_termino')
                  ->orWhere('fecha_termino', '>=', now()->toDateString());
            })
            ->limit(1);
    }

    public function cargasFamiliares(): HasMany
    {
        return $this->hasMany(CargaFamiliar::class)->where('activa', true);
    }

    public function liquidaciones(): HasMany
    {
        return $this->hasMany(Liquidacion::class);
    }

    public function provisionVacaciones(): HasMany
    {
        return $this->hasMany(ProvisionVacaciones::class);
    }

    public function finiquitos(): HasMany
    {
        return $this->hasMany(Finiquito::class);
    }
}
