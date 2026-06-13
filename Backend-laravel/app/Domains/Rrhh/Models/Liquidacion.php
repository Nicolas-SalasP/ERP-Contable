<?php

namespace App\Domains\Rrhh\Models;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Traits\HasEmpresaScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Liquidacion extends Model
{
    use HasEmpresaScope;

    protected $table = 'liquidaciones';

    public const ESTADO_BORRADOR = 'BORRADOR';
    public const ESTADO_EMITIDA  = 'EMITIDA';
    public const ESTADO_PAGADA   = 'PAGADA';
    public const ESTADO_ANULADA  = 'ANULADA';

    protected $fillable = [
        'empresa_id',
        'empleado_id',
        'contrato_id',
        'anio',
        'mes',
        'parametro_previsional_id',
        'indicador_mensual_id',
        'total_haberes_imponibles',
        'total_haberes_no_imponibles',
        'total_haberes',
        'base_imponible',
        'base_tributable',
        'total_descuentos_legales',
        'total_descuentos_voluntarios',
        'total_descuentos',
        'liquido_a_pagar',
        'aporte_empleador_afc',
        'aporte_empleador_sis',
        'aporte_empleador_mutual',
        'salud_legal',
        'salud_adicional',
        'aporte_empleador_reforma',
        'estado',
        'observaciones',
        'comprobante_contable',
    ];

    protected $casts = [
        'total_haberes_imponibles' => 'decimal:2',
        'total_haberes_no_imponibles' => 'decimal:2',
        'total_haberes' => 'decimal:2',
        'base_imponible' => 'decimal:2',
        'base_tributable' => 'decimal:2',
        'total_descuentos_legales' => 'decimal:2',
        'total_descuentos_voluntarios' => 'decimal:2',
        'total_descuentos' => 'decimal:2',
        'liquido_a_pagar' => 'decimal:2',
        'aporte_empleador_afc' => 'decimal:2',
        'aporte_empleador_sis' => 'decimal:2',
        'aporte_empleador_mutual' => 'decimal:2',
        'salud_legal' => 'decimal:2',
        'salud_adicional' => 'decimal:2',
        'aporte_empleador_reforma' => 'decimal:2',
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

    public function parametro(): BelongsTo
    {
        return $this->belongsTo(ParametroPrevisional::class, 'parametro_previsional_id');
    }

    public function indicador(): BelongsTo
    {
        return $this->belongsTo(IndicadorMensual::class, 'indicador_mensual_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(LiquidacionDetalle::class)->orderBy('orden');
    }
}
