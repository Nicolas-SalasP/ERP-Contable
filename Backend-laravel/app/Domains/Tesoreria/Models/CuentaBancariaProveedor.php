<?php
namespace App\Domains\Tesoreria\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\Core\Models\Pais;
use App\Domains\Comercial\Models\Proveedor;

/**
 * @property-read \App\Domains\Comercial\Models\Proveedor|null $proveedor
 */
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

    protected $casts = [
        'numero_cuenta' => 'encrypted',
    ];

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function pais(): BelongsTo
    {
        return $this->belongsTo(Pais::class, 'pais_iso', 'iso');
    }
}