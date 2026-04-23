<?php
namespace App\Domains\Contabilidad\Models;

use Illuminate\Database\Eloquent\Model;
use App\Domains\Core\Models\Empresa;

class CentroCosto extends Model
{
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