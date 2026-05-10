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
        'monto_original',
        'saldo_disponible',
        'fecha_real',
        'referencia',
        'estado',
        'movimiento_id'
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'monto_original' => 'decimal:2',
        'saldo_disponible' => 'decimal:2',
        'fecha_real' => 'date',
    ];

    public function getSaldoDisponibleAttribute($value)
    {
        if ($value !== null) {
            return (float) $value;
        }
        return $this->estado === 'APLICADO' ? 0 : (float) $this->monto;
    }

    public function getFechaAttribute()
    {
        if ($this->fecha_real) {
            return $this->fecha_real->format('Y-m-d');
        }
        return $this->created_at ? $this->created_at->format('Y-m-d') : null;
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