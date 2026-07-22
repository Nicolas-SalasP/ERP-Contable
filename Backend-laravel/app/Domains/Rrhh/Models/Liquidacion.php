<?php

namespace App\Domains\Rrhh\Models;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Traits\HasEmpresaScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use ParagonIE\CipherSweet\EncryptedRow;
use Spatie\LaravelCipherSweet\Concerns\UsesCipherSweet;
use Spatie\LaravelCipherSweet\Contracts\CipherSweetEncrypted;

class Liquidacion extends Model implements CipherSweetEncrypted
{
    use HasEmpresaScope, SoftDeletes, UsesCipherSweet;

    protected $table = 'liquidaciones';

    public const ESTADO_BORRADOR = 'BORRADOR';

    public const ESTADO_EMITIDA = 'EMITIDA';

    public const ESTADO_PAGADA = 'PAGADA';

    public const ESTADO_ANULADA = 'ANULADA';

    protected $fillable = [
        'empresa_id',
        'empleado_id',
        'contrato_id',
        'anio',
        'mes',
        'parametro_previsional_id',
        'indicador_mensual_id',
        'total_haberes_imponibles',
        'total_haberes_no_imponibles',
        'total_haberes',
        'base_imponible',
        'base_tributable',
        'total_descuentos_legales',
        'total_descuentos_voluntarios',
        'total_descuentos',
        'liquido_a_pagar',
        'aporte_empleador_afc',
        'aporte_empleador_sis',
        'aporte_empleador_mutual',
        'salud_legal',
        'salud_adicional',
        'aporte_empleador_reforma',
        'dias_trabajados',
        'dias_licencia_medica',
        'dias_vacaciones',
        'estado',
        'observaciones',
        'comprobante_contable',
    ];

    // CipherSweet incluye 'liquido_a_pagar' en el INSERT aunque no se pase explicito,
    // saltandose el DEFAULT 0 de la columna y mandando NULL (columna NOT NULL) -- este
    // default a nivel de modelo evita el NULL antes de que CipherSweet cifre el valor.
    protected $attributes = [
        'liquido_a_pagar' => 0,
    ];

    protected $casts = [
        'total_haberes_imponibles' => 'decimal:2',
        'total_haberes_no_imponibles' => 'decimal:2',
        'total_haberes' => 'decimal:2',
        'base_imponible' => 'decimal:2',
        'base_tributable' => 'decimal:2',
        'total_descuentos_legales' => 'decimal:2',
        'total_descuentos_voluntarios' => 'decimal:2',
        'total_descuentos' => 'decimal:2',
        'liquido_a_pagar' => 'decimal:2',
        'aporte_empleador_afc' => 'decimal:2',
        'aporte_empleador_sis' => 'decimal:2',
        'aporte_empleador_mutual' => 'decimal:2',
        'salud_legal' => 'decimal:2',
        'salud_adicional' => 'decimal:2',
        'aporte_empleador_reforma' => 'decimal:2',
    ];

    // ---------- Cifrado con CipherSweet (Ley 21.719) ----------

    public static function configureCipherSweet(EncryptedRow $encryptedRow): void
    {
        // Solo el monto final (lo que realmente se paga) -- cifrar los 14 montos
        // intermedios rompería SUM() en SQL usado por reportes/centralizacion contable;
        // liquido_a_pagar es el unico que se agrega siempre vía Collection::sum() en PHP
        // (ya decodifica), nunca en una query cruda.
        $encryptedRow->addOptionalTextField('liquido_a_pagar');
    }

    /** Override necesario para campos cifrados con cast primitivo (decimal:2): sin esto, Eloquent llama a castAttribute() sobre el ciphertext durante getDirty() → syncChanges() → MathException. */
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

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function parametro(): BelongsTo
    {
        return $this->belongsTo(ParametroPrevisional::class, 'parametro_previsional_id');
    }

    public function indicador(): BelongsTo
    {
        return $this->belongsTo(IndicadorMensual::class, 'indicador_mensual_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(LiquidacionDetalle::class)->orderBy('orden');
    }
}
