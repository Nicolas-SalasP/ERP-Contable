<?php

namespace App\Domains\Rrhh\Models;

use App\Domains\Core\Traits\HasEmpresaScope;
use Illuminate\Database\Eloquent\Model;

/** Mapeo de categorías RRHH a cuentas del Plan de Cuentas por empresa, configurado una vez por el administrador antes de usar R5; los tipos opcionales de vacaciones solo entran al asiento si están configurados en par (GASTO + PASIVO) y el monto devengado es > 0. */
class RrhhMapeoContable extends Model
{
    use HasEmpresaScope;

    protected $table = 'rrhh_mapeo_contable';

    // ── Tipos de cuenta ──────────────────────────────────────────────────────

    // DEBE (gastos del período)
    public const GASTO_REMUNERACIONES       = 'GASTO_REMUNERACIONES';
    public const GASTO_LEYES_SOCIALES       = 'GASTO_LEYES_SOCIALES';
    public const GASTO_PROVISION_VACACIONES = 'GASTO_PROVISION_VACACIONES'; // opcional

    // HABER (pasivos al cierre del período)
    public const PASIVO_LIQUIDO_PAGAR              = 'PASIVO_LIQUIDO_PAGAR';
    public const PASIVO_RETENCIONES_PREVISIONALES  = 'PASIVO_RETENCIONES_PREVISIONALES'; // AFP+salud+AFC trab.
    public const PASIVO_IMPUESTO_UNICO             = 'PASIVO_IMPUESTO_UNICO';
    public const PASIVO_LEYES_SOCIALES             = 'PASIVO_LEYES_SOCIALES';   // aportes empleador por pagar
    public const PASIVO_DESCUENTOS_VOLUNTARIOS     = 'PASIVO_DESCUENTOS_VOLUNTARIOS'; // APV, préstamos, etc.
    public const PASIVO_PROVISION_VACACIONES       = 'PASIVO_PROVISION_VACACIONES'; // opcional

    /** Deben estar configurados para que la centralización se pueda ejecutar. */
    public const TIPOS_REQUERIDOS = [
        self::GASTO_REMUNERACIONES,
        self::GASTO_LEYES_SOCIALES,
        self::PASIVO_LIQUIDO_PAGAR,
        self::PASIVO_RETENCIONES_PREVISIONALES,
        self::PASIVO_IMPUESTO_UNICO,
        self::PASIVO_LEYES_SOCIALES,
    ];

    /** Opcionales: se incluyen en el asiento solo si el monto del período es > 0 y están configurados; las cuentas de vacaciones deben configurarse en par. */
    public const TIPOS_OPCIONALES = [
        self::GASTO_PROVISION_VACACIONES,
        self::PASIVO_PROVISION_VACACIONES,
        self::PASIVO_DESCUENTOS_VOLUNTARIOS,
    ];

    public const TODOS_LOS_TIPOS = [
        self::GASTO_REMUNERACIONES,
        self::GASTO_LEYES_SOCIALES,
        self::GASTO_PROVISION_VACACIONES,
        self::PASIVO_LIQUIDO_PAGAR,
        self::PASIVO_RETENCIONES_PREVISIONALES,
        self::PASIVO_IMPUESTO_UNICO,
        self::PASIVO_LEYES_SOCIALES,
        self::PASIVO_DESCUENTOS_VOLUNTARIOS,
        self::PASIVO_PROVISION_VACACIONES,
    ];

    protected $fillable = [
        'empresa_id',
        'tipo_cuenta',
        'cuenta_contable_codigo',
        'descripcion',
    ];
}
