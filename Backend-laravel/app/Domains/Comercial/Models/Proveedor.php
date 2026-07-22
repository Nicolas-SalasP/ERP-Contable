<?php

namespace App\Domains\Comercial\Models;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\Pais;
use App\Domains\Core\Traits\HasEmpresaScope;
use App\Domains\Tesoreria\Models\CuentaBancariaProveedor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use ParagonIE\CipherSweet\BlindIndex;
use ParagonIE\CipherSweet\EncryptedRow;
use Spatie\LaravelCipherSweet\Concerns\UsesCipherSweet;
use Spatie\LaravelCipherSweet\Contracts\CipherSweetEncrypted;

class Proveedor extends Model implements CipherSweetEncrypted
{
    use HasEmpresaScope;
    use SoftDeletes;
    use UsesCipherSweet;

    protected $table = 'proveedores';

    const UPDATED_AT = null;

    protected $fillable = [
        'empresa_id',
        'codigo_interno',
        'rut',
        'razon_social',
        'pais_iso',
        'moneda_defecto',
        'estado',
        'region',
        'comuna',
        'direccion',
        'telefono',
        'email_contacto',
        'nombre_contacto',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function pais(): BelongsTo
    {
        return $this->belongsTo(Pais::class, 'pais_iso', 'iso');
    }

    public function cuentasBancarias(): HasMany
    {
        return $this->hasMany(CuentaBancariaProveedor::class);
    }

    // ---------- Cifrado con CipherSweet (Ley 21.719) ----------

    public static function configureCipherSweet(EncryptedRow $encryptedRow): void
    {
        // rut es nullable (proveedores extranjeros pueden no tenerlo en este formato).
        $encryptedRow
            ->addOptionalTextField('rut')
            ->addBlindIndex('rut', new BlindIndex('proveedor_rut_index'));
    }
}
