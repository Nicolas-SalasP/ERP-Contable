<?php

namespace App\Domains\Rrhh\Models;

use App\Domains\Rrhh\Exceptions\RrhhException;
use Illuminate\Database\Eloquent\Model;

class ParametroPrevisional extends Model
{
    protected $table = 'parametros_previsionales';

    protected $fillable = [
        'vigente_desde',
        'vigente_hasta',
        'afp_cotizacion_pct',
        'afp_comisiones_json',
        'afp_sis_pct',
        'tope_imponible_uf',
        'salud_fonasa_pct',
        'afc_indefinido_trabajador_pct',
        'afc_indefinido_empleador_pct',
        'afc_plazo_fijo_trabajador_pct',
        'afc_plazo_fijo_empleador_pct',
        'afc_tope_imponible_uf',
        'imm',
        'gratificacion_tope_mensual_factor',
        'cotizacion_adicional_empleador_pct',
        'mutual_cotizacion_basica_pct',
        'mutual_codigo',
        'asignacion_familiar_tramos_json',
        'fuente',
    ];

    protected $casts = [
        'vigente_desde' => 'date',
        'vigente_hasta' => 'date',
        'afp_comisiones_json' => 'array',
        'asignacion_familiar_tramos_json' => 'array',
        'afp_cotizacion_pct' => 'decimal:4',
        'afp_sis_pct' => 'decimal:4',
        'tope_imponible_uf' => 'decimal:4',
        'salud_fonasa_pct' => 'decimal:4',
        'afc_indefinido_trabajador_pct' => 'decimal:4',
        'afc_indefinido_empleador_pct' => 'decimal:4',
        'afc_plazo_fijo_trabajador_pct' => 'decimal:4',
        'afc_plazo_fijo_empleador_pct' => 'decimal:4',
        'afc_tope_imponible_uf' => 'decimal:4',
        'cotizacion_adicional_empleador_pct' => 'decimal:4',
        'imm' => 'decimal:2',
    ];

    public function getComisionAfp(string $afpNombre): float
    {
        $comisiones = $this->afp_comisiones_json ?? [];
        $valor = $comisiones[$afpNombre] ?? $comisiones[strtolower($afpNombre)] ?? null;
        if ($valor === null) {
            // Fix 8: AFP sin comisión configurada es error de datos, no fallback silencioso
            throw RrhhException::regla("AFP \"{$afpNombre}\" sin comisión configurada en los parámetros previsionales.");
        }
        return (float) $valor;
    }

    public static function vigentePara(string $fecha): ?self
    {
        return static::where('vigente_desde', '<=', $fecha)
            ->where(function ($q) use ($fecha) {
                $q->whereNull('vigente_hasta')
                    ->orWhere('vigente_hasta', '>=', $fecha);
            })
            ->orderByDesc('vigente_desde')
            ->first();
    }
}
