<?php

namespace App\Domains\Activos\Models;

use Illuminate\Database\Eloquent\Model;
use App\Domains\Contabilidad\Models\CentroCosto;

class ActivoFijo extends Model
{
    protected $table = 'activos_fijos';

    protected $fillable = [
        'empresa_id',
        'codigo',
        'nombre',
        'descripcion',
        'cuenta_activo_codigo',
        'cuenta_depreciacion_codigo',
        'cuenta_gasto_codigo',
        'centro_costo_id',
        'valor_adquisicion',
        'fecha_adquisicion',
        'vida_util_meses',
        'valor_residual',
        'estado',
        'depreciacion_acumulada'
    ];

    public function centroCosto()
    {
        return $this->belongsTo(CentroCosto::class, 'centro_costo_id');
    }
}