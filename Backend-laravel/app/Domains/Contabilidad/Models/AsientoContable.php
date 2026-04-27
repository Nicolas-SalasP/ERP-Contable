<?php

namespace App\Domains\Contabilidad\Models;

use Illuminate\Database\Eloquent\Model;
use App\Domains\Core\Models\Empresa;

class AsientoContable extends Model
{
    protected $table = 'asientos_contables';
    const UPDATED_AT = null;

    protected $fillable = [
        'codigo_unico',
        'empresa_id',
        'numero_comprobante',
        'centro_costo_id',
        'empleado_nombre',
        'fecha',
        'glosa',
        'tipo_asiento',
        'origen_modulo',
        'origen_id',
        'usuario_id',
        'estado',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function centroCosto()
    {
        return $this->belongsTo(CentroCosto::class);
    }

    public function detalles()
    {
        return $this->hasMany(DetalleAsiento::class, 'asiento_id');
    }
}