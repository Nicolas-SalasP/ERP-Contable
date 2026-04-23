<?php
namespace App\Domains\Core\Models;

use Illuminate\Database\Eloquent\Model;

class Pais extends Model
{
    protected $table = 'paises';
    protected $primaryKey = 'iso';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'iso',
        'nombre',
        'moneda_defecto',
        'etiqueta_id',
        'activo',
        ];
}