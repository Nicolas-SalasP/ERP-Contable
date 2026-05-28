<?php

namespace App\Domains\CorreccionMonetaria\Models;

use Illuminate\Database\Eloquent\Model;
use App\Domains\Core\Models\User;

class CmIndiceIpc extends Model
{
    protected $table = 'cm_indices_ipc';

    protected $fillable = [
        'anio',
        'mes',
        'variacion_mensual',
        'variacion_acumulada_anual',
        'factor_multiplicador',
        'fuente',
        'url_respuesta_api',
        'observacion',
        'creado_por_usuario_id',
    ];

    protected $casts = [
        'anio' => 'integer',
        'mes' => 'integer',
        'variacion_mensual' => 'decimal:4',
        'variacion_acumulada_anual' => 'decimal:4',
        'factor_multiplicador' => 'decimal:6',
    ];

    public function creadoPor()
    {
        return $this->belongsTo(User::class, 'creado_por_usuario_id');
    }

    public function getNombreMesAttribute(): string
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
        return $meses[$this->mes] ?? "Mes {$this->mes}";
    }

    public function scopeDelAnio($query, int $anio)
    {
        return $query->where('anio', $anio)->orderBy('mes');
    }

    public function scopeManual($query)
    {
        return $query->where('fuente', 'manual');
    }
}