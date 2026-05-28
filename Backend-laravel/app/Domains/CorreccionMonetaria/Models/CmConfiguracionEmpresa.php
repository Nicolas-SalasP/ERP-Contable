<?php

namespace App\Domains\CorreccionMonetaria\Models;

use Illuminate\Database\Eloquent\Model;
use App\Domains\Core\Models\Empresa;

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