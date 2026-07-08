<?php

namespace App\Domains\Comercial\Models;

use App\Domains\Core\Traits\HasEmpresaScope;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\Core\Models\Empresa;

class AnticipoCliente extends Model
{
    use HasEmpresaScope;
    protected $table = 'anticipos_clientes';

    protected $fillable = [
        'empresa_id',
        'cliente_id',
        'monto',
        'monto_original',
        'saldo_disponible',
        'fecha_real',
        'referencia',
        'estado',
        'movimiento_id',
        'asiento_id'
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

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
