<?php

namespace App\Domains\Comercial\Models;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Traits\HasEmpresaScope;
use App\Domains\Sii\Concerns\HasSiiAttributesCliente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use ParagonIE\CipherSweet\BlindIndex;
use ParagonIE\CipherSweet\EncryptedRow;
use Spatie\LaravelCipherSweet\Concerns\UsesCipherSweet;
use Spatie\LaravelCipherSweet\Contracts\CipherSweetEncrypted;

class Cliente extends Model implements CipherSweetEncrypted
{
    use HasEmpresaScope;
    use HasSiiAttributesCliente;
    use SoftDeletes;
    use UsesCipherSweet;

    protected $table = 'clientes';

    const UPDATED_AT = null;

    protected $fillable = [
        'rut',
        'razon_social',
        'contacto_nombre',
        'contacto_email',
        'contacto_telefono',
        'direccion',
        'telefono',
        'email',
        'estado',
        'empresa_id',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    // ---------- Cifrado con CipherSweet (Ley 21.719) ----------

    public static function configureCipherSweet(EncryptedRow $encryptedRow): void
    {
        $encryptedRow
            ->addTextField('rut')
            ->addBlindIndex('rut', new BlindIndex('cliente_rut_index'));
    }
}
