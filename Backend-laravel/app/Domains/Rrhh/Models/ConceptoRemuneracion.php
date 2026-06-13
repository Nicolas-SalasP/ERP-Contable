<?php

namespace App\Domains\Rrhh\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo de conceptos de remuneración. Es un modelo HÍBRIDO: contiene
 * conceptos del sistema (empresa_id = null, globales y compartidos por todas las
 * empresas) y conceptos personalizados por empresa (empresa_id no nulo). Por eso
 * NO usa HasEmpresaScope — un scope global ocultaría los conceptos del sistema.
 * El filtrado por empresa, cuando aplica, es explícito: where empresa_id IN (null, X).
 * Excluido del guardián de cobertura de scope (ver EmpresaScopeCoberturaTest).
 */
class ConceptoRemuneracion extends Model
{
    protected $table = 'concepto_remuneraciones';

    // Códigos de los conceptos del sistema (no cambiar sin revisar LiquidacionService)
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
