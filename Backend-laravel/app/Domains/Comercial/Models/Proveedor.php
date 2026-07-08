<?php
namespace App\Domains\Comercial\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\Pais;
use App\Domains\Core\Traits\HasEmpresaScope;
use App\Domains\Tesoreria\Models\CuentaBancariaProveedor;
class Proveedor extends Model
{
    use SoftDeletes;
    use HasEmpresaScope;

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
}