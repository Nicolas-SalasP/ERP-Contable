<?php
namespace App\Domains\Tesoreria\Models;

use Illuminate\Database\Eloquent\Model;
use App\Domains\Core\Models\Pais;
use App\Domains\Comercial\Models\Proveedor;

class CuentaBancariaProveedor extends Model
{
    protected $table = 'cuentas_bancarias_proveedores';
    public $timestamps = false;

    protected $fillable = [
        'proveedor_id',
        'banco',
        'numero_cuenta',
        'tipo_cuenta',
        'pais_iso',
        'swift_bic',
        'activo',
    ];

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function pais()
    {
        return $this->belongsTo(Pais::class, 'pais_iso', 'iso');
    }
}