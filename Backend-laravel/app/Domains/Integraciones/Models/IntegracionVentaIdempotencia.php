<?php

namespace App\Domains\Integraciones\Models;

use App\Domains\Comercial\Models\Factura;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Traits\HasEmpresaScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Igual que IntegracionApiKey: lleva HasEmpresaScope por consistencia/defensa en profundidad,
 * aunque VentaIntegracionService::confirmar() SIEMPRE filtra empresa_id explicito (el pipeline
 * de Integraciones corre sin sesion Sanctum, asi que el scope global esta apagado ahi -- ver
 * AutenticarApiKey). El scope si aplica si esta tabla se consulta alguna vez desde una ruta
 * autenticada por Sanctum (ej. un futuro panel admin de idempotencias).
 */
class IntegracionVentaIdempotencia extends Model
{
    use HasEmpresaScope;

    protected $table = 'integracion_venta_idempotencias';

    protected $fillable = [
        'empresa_id',
        'clave',
        'factura_id',
        'respuesta_status',
        'respuesta_json',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'factura_id' => 'integer',
        'respuesta_status' => 'integer',
        'respuesta_json' => 'array',
    ];

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** @return BelongsTo<Factura, $this> */
    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }
}
