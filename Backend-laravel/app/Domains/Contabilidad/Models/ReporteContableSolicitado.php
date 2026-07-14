<?php

namespace App\Domains\Contabilidad\Models;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Core\Traits\HasEmpresaScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReporteContableSolicitado extends Model
{
    use HasEmpresaScope;

    public const ESTADO_PENDIENTE = 'PENDIENTE';
    public const ESTADO_PROCESANDO = 'PROCESANDO';
    public const ESTADO_ENVIADO = 'ENVIADO';
    public const ESTADO_ERROR = 'ERROR';

    protected $table = 'reportes_contables_solicitados';

    protected $fillable = [
        'empresa_id',
        'usuario_id',
        'tipo_reporte',
        'fecha_inicio',
        'fecha_fin',
        'filtro',
        'cuenta_contable',
        'email_destino',
        'estado',
        'error_mensaje',
        'enviado_at',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'enviado_at' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
