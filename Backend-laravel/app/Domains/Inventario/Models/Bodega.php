<?php

namespace App\Domains\Inventario\Models;

use App\Domains\Core\Models\Empresa;
use Illuminate\Database\Eloquent\Model;

class Bodega extends Model
{
    protected $table = 'inventario_bodegas';

    protected $fillable = [
        'empresa_id',
        'codigo',
        'nombre',
        'direccion',
        'estado',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }
}