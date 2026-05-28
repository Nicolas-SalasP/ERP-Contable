<?php

namespace App\Domains\CorreccionMonetaria\Models;

use Illuminate\Database\Eloquent\Model;
use App\Domains\Contabilidad\Models\AsientoContable;
use App\Domains\Core\Models\User;

class CmEjecucion extends Model
{
    protected $table = 'cm_ejecuciones';

    protected $fillable = [
        'empresa_id',
        'periodo_mes',
        'periodo_anio',
        'tipo',
        'estado',
        'factor_ipc_utilizado',
        'variacion_porcentual',
        'total_ajuste_activos',
        'total_ajuste_depreciacion',
        'total_ajuste_patrimonio',
        'total_ajuste_existencias',
        'total_ajuste_pasivos',
        'total_cm_neto',
        'asiento_id',
        'usuario_id',
        'observacion',
    ];

    protected $casts = [
        'periodo_mes' => 'integer',
        'periodo_anio' => 'integer',
        'factor_ipc_utilizado' => 'decimal:6',
        'variacion_porcentual' => 'decimal:4',
        'total_ajuste_activos' => 'decimal:2',
        'total_ajuste_depreciacion' => 'decimal:2',
        'total_ajuste_patrimonio' => 'decimal:2',
        'total_ajuste_existencias' => 'decimal:2',
        'total_ajuste_pasivos' => 'decimal:2',
        'total_cm_neto' => 'decimal:2',
    ];

    public function asiento()
    {
        return $this->belongsTo(AsientoContable::class, 'asiento_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function getNombreMesAttribute(): string
    {
        $meses = [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre',
        ];
        return $meses[$this->periodo_mes] ?? "Mes {$this->periodo_mes}";
    }

    public function getLabelPeriodoAttribute(): string
    {
        return $this->nombre_mes . ' ' . $this->periodo_anio;
    }

    public function scopeEjecutadas($query)
    {
        return $query->where('estado', 'ejecutada');
    }

    public function bloqueaPeriodo(): bool
    {
        return $this->estado === 'ejecutada';
    }
}