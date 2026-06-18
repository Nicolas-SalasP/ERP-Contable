<?php

namespace App\Domains\Comercial\Models;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Traits\HasEmpresaScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HonorarioRecibido extends Model
{
    use HasEmpresaScope, SoftDeletes;

    protected $table = 'honorarios_recibidos';

    protected $fillable = [
        'empresa_id',
        'proveedor_id',
        'rut_prestador',
        'nombre_prestador',
        'numero_boleta',
        'fecha',
        'monto_bruto',
        'tasa_retencion_pct',
        'monto_retencion',
        'monto_liquido',
    ];

    protected $casts = [
        'fecha'              => 'date',
        'tasa_retencion_pct' => 'decimal:2',
    ];

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function scopeDelAnio(Builder $query, int $anio): Builder
    {
        return $query->whereYear('fecha', $anio);
    }

    public function scopeDelPeriodo(Builder $query, int $mes, int $anio): Builder
    {
        return $query->whereYear('fecha', $anio)->whereMonth('fecha', $mes);
    }
}
