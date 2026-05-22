<?php

namespace App\Domains\Core\Models;

use Illuminate\Database\Eloquent\Model;

class Auditoria extends Model
{
    protected $table = 'auditorias';

    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'nombre_usuario',
        'operacion',
        'estado_anterior',
        'estado_nuevo',
        'detalle',
        'referencia_cruzada'
    ];

    public function auditable()
    {
        return $this->morphTo();
    }
}