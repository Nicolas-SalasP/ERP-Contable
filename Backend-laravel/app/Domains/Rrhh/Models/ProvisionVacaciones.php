<?php

namespace App\Domains\Rrhh\Models;

use App\Domains\Core\Traits\HasEmpresaScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProvisionVacaciones extends Model
{
    use HasEmpresaScope;

    protected $table = 'provision_vacaciones';

    protected $fillable = [
        'empresa_id',
        'empleado_id',
        'contrato_id',
        'anio',
        'mes',
        'dias_devengados_mes',
        'saldo_dias_habiles',
        'monto_devengado_mes',
        'monto_provisionado_total',
        'remuneracion_diaria',
    ];

    protected $casts = [
        'dias_devengados_mes' => 'decimal:4',
        'saldo_dias_habiles' => 'decimal:4',
        'monto_devengado_mes' => 'decimal:2',
        'monto_provisionado_total' => 'decimal:2',
        'remuneracion_diaria' => 'decimal:2',
    ];

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }
}
