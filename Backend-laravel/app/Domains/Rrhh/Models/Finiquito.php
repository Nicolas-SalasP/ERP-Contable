<?php

namespace App\Domains\Rrhh\Models;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Traits\HasEmpresaScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Finiquito extends Model
{
    use HasEmpresaScope;

    protected $table = 'finiquitos';

    protected $fillable = [
        'empresa_id',
        'empleado_id',
        'contrato_id',
        'causal',
        'fecha_termino',
        'fecha_inicio_contrato',
        'ultima_remuneracion_mensual',
        'promedio_remuneraciones_variables',
        'remuneracion_base_calculo',
        'anios_servicio',
        'meses_fraccion',
        'fraccion_cuenta_como_anio',
        'anios_calculo',
        'monto_indemnizacion_anos',
        'tiene_aviso_previo',
        'monto_aviso_previo',
        'dias_vacaciones_proporcionales',
        'monto_vacaciones_proporcionales',
        'haberes_pendientes',
        'descuentos_pendientes',
        'total_bruto',
        'total_neto',
        'estado',
        'observaciones',
        'comprobante_contable',
    ];

    protected $casts = [
        'fecha_termino' => 'date',
        'fecha_inicio_contrato' => 'date',
        'fraccion_cuenta_como_anio' => 'boolean',
        'tiene_aviso_previo' => 'boolean',
        'ultima_remuneracion_mensual' => 'decimal:2',
        'promedio_remuneraciones_variables' => 'decimal:2',
        'remuneracion_base_calculo' => 'decimal:2',
        'monto_indemnizacion_anos' => 'decimal:2',
        'monto_aviso_previo' => 'decimal:2',
        'dias_vacaciones_proporcionales' => 'decimal:4',
        'monto_vacaciones_proporcionales' => 'decimal:2',
        'haberes_pendientes' => 'decimal:2',
        'descuentos_pendientes' => 'decimal:2',
        'total_bruto' => 'decimal:2',
        'total_neto' => 'decimal:2',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }
}
