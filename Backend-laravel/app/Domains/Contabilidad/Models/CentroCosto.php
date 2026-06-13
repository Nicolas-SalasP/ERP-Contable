<?php
namespace App\Domains\Contabilidad\Models;

use App\Domains\Core\Traits\HasEmpresaScope;

use Illuminate\Database\Eloquent\Model;
use App\Domains\Core\Models\Empresa;

class CentroCosto extends Model
{
    use HasEmpresaScope;
    protected $table = 'centros_costo';
    public $timestamps = false;

    protected $fillable = [
        'empresa_id',
        'codigo',
        'nombre',
        'activo',
        ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }
}