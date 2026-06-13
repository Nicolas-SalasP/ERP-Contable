<?php

namespace App\Domains\Rrhh\Models;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Traits\HasEmpresaScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CargaFamiliar extends Model
{
    use HasEmpresaScope;

    protected $table = 'cargas_familiares';

    protected $fillable = [
        'empresa_id',
        'empleado_id',
        'rut',
        'nombre',
        'tipo',
        'fecha_nacimiento',
        'estudia',
        'activa',
        'vigente_desde',
        'vigente_hasta',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'vigente_desde' => 'date',
        'vigente_hasta' => 'date',
        'estudia' => 'boolean',
        'activa' => 'boolean',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }
}
