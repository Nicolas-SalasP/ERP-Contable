<?php

namespace App\Domains\CorreccionMonetaria\Models;

use Illuminate\Database\Eloquent\Model;

class CmConfiguracionCuenta extends Model
{
    protected $table = 'cm_configuracion_cuentas';

    protected $fillable = [
        'empresa_id',
        'cuenta_codigo',
        'rol_cm',
        'aplica',
        'factor_override',
    ];

    protected $casts = [
        'aplica' => 'boolean',
        'factor_override' => 'decimal:6',
    ];

    const ROL_ACTIVO_NO_MONETARIO = 'ACTIVO_NO_MONETARIO';
    const ROL_DEPRECIACION_ACUMULADA = 'DEPRECIACION_ACUMULADA';
    const ROL_INVENTARIO = 'INVENTARIO';
    const ROL_PATRIMONIO_CAPITAL = 'PATRIMONIO_CAPITAL';
    const ROL_PASIVO_NO_MONETARIO = 'PASIVO_NO_MONETARIO';

    public function scopeActivas($query)
    {
        return $query->where('aplica', true);
    }

    public function scopeConRol($query, string $rol)
    {
        return $query->where('rol_cm', $rol);
    }

    public function getLabelRolAttribute(): string
    {
        return match ($this->rol_cm) {
            self::ROL_ACTIVO_NO_MONETARIO => 'Activo No Monetario',
            self::ROL_DEPRECIACION_ACUMULADA => 'Depreciación Acumulada',
            self::ROL_INVENTARIO => 'Existencias / Inventario',
            self::ROL_PATRIMONIO_CAPITAL => 'Patrimonio / Capital',
            self::ROL_PASIVO_NO_MONETARIO => 'Pasivo No Monetario',
            default => $this->rol_cm,
        };
    }
}