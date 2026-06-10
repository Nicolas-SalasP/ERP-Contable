<?php

namespace App\Domains\Contabilidad\Models;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Core\Traits\HasEmpresaScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Periodo contable cerrado (empresa + anio + mes). Solo se persisten los CERRADOS;
 * la ausencia de fila significa que el periodo esta ABIERTO.
 */
class PeriodoContable extends Model
{
    use HasEmpresaScope;

    protected $table = 'periodos_contables';

    public const ESTADO_CERRADO = 'CERRADO';
    public const ESTADO_ABIERTO = 'ABIERTO';

    protected $fillable = [
        'empresa_id',
        'anio',
        'mes',
        'estado',
        'cerrado_por',
        'cerrado_at',
        'reabierto_por',
        'reabierto_at',
        'motivo',
    ];

    protected $casts = [
        'anio' => 'integer',
        'mes' => 'integer',
        'cerrado_at' => 'datetime',
        'reabierto_at' => 'datetime',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function cerradoPor()
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }

    public function reabiertoPor()
    {
        return $this->belongsTo(User::class, 'reabierto_por');
    }
}
