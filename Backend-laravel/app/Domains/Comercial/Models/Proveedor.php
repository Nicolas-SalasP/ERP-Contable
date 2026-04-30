<?php
namespace App\Domains\Comercial\Models;

use Illuminate\Database\Eloquent\Model;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\Pais;
use App\Domains\Tesoreria\Models\CuentaBancariaProveedor;
class Proveedor extends Model
{
    protected $table = 'proveedores';
    const UPDATED_AT = null;

    protected $fillable = [
        'empresa_id',
        'codigo_interno',
        'rut',
        'razon_social',
        'pais_iso',
        'moneda_defecto',
        'region',
        'comuna',
        'direccion',
        'telefono',
        'email_contacto',
        'nombre_contacto',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function pais()
    {
        return $this->belongsTo(Pais::class, 'pais_iso', 'iso');
    }

    public function cuentasBancarias()
    {
        return $this->hasMany(CuentaBancariaProveedor::class);
    }
}