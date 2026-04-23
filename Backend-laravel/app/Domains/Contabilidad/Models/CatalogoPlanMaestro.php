<?php
namespace App\Domains\Contabilidad\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogoPlanMaestro extends Model
{
    protected $table = 'catalogo_plan_maestro';
    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'nombre',
        'tipo',
        'nivel',
        'imputable',
        ];
}