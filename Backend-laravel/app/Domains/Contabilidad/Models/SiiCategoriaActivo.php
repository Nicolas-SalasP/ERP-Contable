<?php
namespace App\Domains\Contabilidad\Models;

use Illuminate\Database\Eloquent\Model;

class SiiCategoriaActivo extends Model
{
    protected $table = 'sii_categorias_activos';
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'vida_util_normal',
        'vida_util_acelerada',
        ];
}