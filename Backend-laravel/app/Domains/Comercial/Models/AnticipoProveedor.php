<?php

namespace App\Domains\Comercial\Models;

use Illuminate\Database\Eloquent\Model;
use App\Domains\Core\Models\Empresa;

class AnticipoProveedor extends Model
{
    protected $table = 'anticipos_proveedores';

    protected $fillable = [
        'empresa_id',
        'proveedor_id',
        'monto',
        'referencia',
        'estado',
        'movimiento_id'
    ];

    protected $appends = ['fecha', 'saldo_disponible'];

    public function getFechaAttribute()
    {
        return $this->created_at ? $this->created_at->format('Y-m-d') : null;
    }

    public function getSaldoDisponibleAttribute()
    {
        return $this->estado === 'APLICADO' ? 0 : $this->monto;
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }
}