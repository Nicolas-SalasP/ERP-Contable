<?php
namespace App\Domains\Contabilidad\Models;

use Illuminate\Database\Eloquent\Model;
use App\Domains\Core\Models\Empresa;

class PlanCuenta extends Model
{
    protected $table = 'plan_cuentas';
    const UPDATED_AT = null;

    protected $fillable = [
        'empresa_id',
        'codigo',
        'nombre',
        'tipo',
        'nivel',
        'imputable',
        'activo',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }
}