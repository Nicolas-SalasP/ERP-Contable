<?php

namespace App\Domains\CorreccionMonetaria\Models;

use App\Domains\Core\Models\Empresa;
use Illuminate\Database\Eloquent\Model;

// NO agregar HasEmpresaScope aqui (ya se intento y se revirtio): EmpresaObserver::created()
// crea la config para la EMPRESA NUEVA que se esta onboardeando, mientras el actor autenticado
// (quien dispara la creacion, ej. un staff o el propio signup) tiene su propia empresa_activa_id
// distinta. El scope global filtraria por la empresa del actor, no la nueva, rompiendo el
// onboarding. Todas las queries actuales (Service + Observer) ya filtran empresa_id a mano.
class CmConfiguracionEmpresa extends Model
{
    protected $table = 'cm_configuracion_empresa';

    protected $fillable = [
        'empresa_id',
        'aplica_cm',
        'modalidad',
        'mes_cierre',
        'cuenta_activos_codigo',
        'cuenta_depreciacion_codigo',
        'cuenta_patrimonio_codigo',
        'cuenta_existencias_codigo',
        'cuenta_pasivos_codigo',
        'activo',
    ];

    protected $casts = [
        'aplica_cm' => 'boolean',
        'activo' => 'boolean',
        'mes_cierre' => 'integer',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function cuentas()
    {
        return $this->hasMany(CmConfiguracionCuenta::class, 'empresa_id', 'empresa_id');
    }

    public function puedeEjecutarMes(int $mes): bool
    {
        if ($this->modalidad === 'mensual') {
            return true;
        }

        return $mes === $this->mes_cierre;
    }

    public function getNombreMesCierreAttribute(): string
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

        return $meses[$this->mes_cierre] ?? "Mes {$this->mes_cierre}";
    }
}
