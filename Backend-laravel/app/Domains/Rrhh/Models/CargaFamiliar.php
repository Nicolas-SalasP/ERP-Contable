<?php

namespace App\Domains\Rrhh\Models;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Traits\HasEmpresaScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use ParagonIE\CipherSweet\EncryptedRow;
use Spatie\LaravelCipherSweet\Concerns\UsesCipherSweet;
use Spatie\LaravelCipherSweet\Contracts\CipherSweetEncrypted;

/**
 * Fase 2b — Ley 21.719: el RUT de cargas familiares se cifra en reposo con
 * CipherSweet. No requiere blind index porque el campo no se usa en búsquedas.
 */
class CargaFamiliar extends Model implements CipherSweetEncrypted
{
    use HasEmpresaScope, UsesCipherSweet;

    protected $table = 'cargas_familiares';

    protected $fillable = [
        'empresa_id',
        'empleado_id',
        'rut',
        'nombre',
        'tipo',
        'fecha_nacimiento',
        'estudia',
        'activa',
        'vigente_desde',
        'vigente_hasta',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'vigente_desde' => 'date',
        'vigente_hasta' => 'date',
        'estudia' => 'boolean',
        'activa' => 'boolean',
    ];

    // ---------- Cifrado con CipherSweet (Ley 21.719 — Fase 2b) ----------

    public static function configureCipherSweet(EncryptedRow $encryptedRow): void
    {
        $encryptedRow
            // RUT nullable, sin blind index (no se busca por RUT de carga familiar)
            ->addOptionalTextField('rut');
    }

    // ---------- Relaciones ----------

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }
}
