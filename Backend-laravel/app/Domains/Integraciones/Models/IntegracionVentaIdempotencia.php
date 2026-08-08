<?php

namespace App\Domains\Integraciones\Models;

use App\Domains\Comercial\Models\Factura;
use App\Domains\Core\Models\Empresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegracionVentaIdempotencia extends Model
{
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
