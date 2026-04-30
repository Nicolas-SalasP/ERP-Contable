<?php

namespace App\Domains\Activos\Models;

use Illuminate\Database\Eloquent\Model;

class ProyectoActivo extends Model
{
    protected $table = 'proyectos_activos';
    protected $primaryKey = 'id_proyecto';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'tipo_activo_id',
        'anio_fabricacion',
        'vida_util_meses',
        'centro_costo_id',
        'empleado_id',
        'valor_total_original',
        'estado'
    ];
}