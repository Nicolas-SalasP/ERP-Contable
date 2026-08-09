<?php

namespace App\Domains\Integraciones\Models;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Traits\HasEmpresaScope;
use App\Domains\Inventario\Models\InventarioDevolucionOrden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Igual que IntegracionVentaIdempotencia: lleva HasEmpresaScope por consistencia/defensa en
 * profundidad, aunque DevolucionIntegracionService::crear() SIEMPRE filtra empresa_id explicito
 * (el pipeline de Integraciones corre sin sesion Sanctum, el scope global esta apagado ahi).
 */
class IntegracionDevolucionIdempotencia extends Model
{
    use HasEmpresaScope;

    protected $table = 'integracion_devolucion_idempotencias';

    protected $fillable = [
        'empresa_id',
        'clave',
        'devolucion_orden_id',
        'respuesta_status',
        'respuesta_json',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'devolucion_orden_id' => 'integer',
        'respuesta_status' => 'integer',
        'respuesta_json' => 'array',
    ];

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** @return BelongsTo<InventarioDevolucionOrden, $this> */
    public function devolucionOrden(): BelongsTo
    {
        return $this->belongsTo(InventarioDevolucionOrden::class, 'devolucion_orden_id');
    }
}
