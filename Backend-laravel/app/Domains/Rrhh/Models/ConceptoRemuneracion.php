<?php

namespace App\Domains\Rrhh\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Modelo HÍBRIDO (conceptos del sistema con empresa_id null + conceptos por empresa): no usa HasEmpresaScope porque ocultaría los conceptos del sistema, filtrado explícito via empresa_id IN (null, X); excluido del guardián de cobertura (ver EmpresaScopeCoberturaTest). */
class ConceptoRemuneracion extends Model
{
    protected $table = 'concepto_remuneraciones';

    // Códigos de los conceptos del sistema; no cambiar sin revisar LiquidacionService.
    public const SUELDO_BASE         = 'SUELDO_BASE';
    public const GRATIFICACION       = 'GRATIFICACION';
    public const HORAS_EXTRA         = 'HORAS_EXTRA';
    public const COLACION            = 'COLACION';
    public const MOVILIZACION        = 'MOVILIZACION';
    public const ASIGNACION_FAMILIAR = 'ASIGNACION_FAMILIAR';
    public const AFP_COTIZACION      = 'AFP_COTIZACION';
    public const AFP_COMISION        = 'AFP_COMISION';
    public const SALUD               = 'SALUD';
    public const AFC_TRABAJADOR      = 'AFC_TRABAJADOR';
    public const IMPUESTO_UNICO      = 'IMPUESTO_UNICO';

    protected $fillable = [
        'empresa_id',
        'codigo',
        'nombre',
        'descripcion',
        'tipo',
        'regla_calculo',
        'es_sistema',
        'activo',
        'orden',
    ];

    protected $casts = [
        'es_sistema' => 'boolean',
        'activo' => 'boolean',
    ];

    public function haberes(): HasMany
    {
        return $this->hasMany(HaberDescuentoContrato::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(LiquidacionDetalle::class);
    }
}
