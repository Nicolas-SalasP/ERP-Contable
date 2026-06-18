<?php
namespace App\Domains\Tesoreria\Models;

use App\Domains\Core\Traits\HasEmpresaScope;

use Illuminate\Database\Eloquent\Model;
use App\Domains\Core\Models\Empresa;

class CuentaBancariaEmpresa extends Model
{
    use HasEmpresaScope;
    protected $table = 'cuentas_bancarias_empresa';
    const UPDATED_AT = null;

    protected $fillable = [
        'empresa_id',
        'banco',
        'tipo_cuenta',
        'numero_cuenta',
        'cuenta_contable',
        'saldo_actual',
        'titular',
        'rut_titular',
        'email_notificacion',
    ];

    protected $casts = [
        'numero_cuenta' => 'encrypted',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }
}