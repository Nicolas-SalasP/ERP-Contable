<?php

namespace App\Domains\Contabilidad\Models;

use Illuminate\Database\Eloquent\Model;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;

class AsientoContable extends Model
{
    protected $table = 'asientos_contables';
    const UPDATED_AT = null;

    protected $fillable = [
        'codigo_unico',
        'empresa_id',
        'numero_comprobante',
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

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}