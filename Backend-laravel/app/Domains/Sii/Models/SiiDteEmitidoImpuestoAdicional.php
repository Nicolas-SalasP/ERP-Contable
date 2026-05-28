<?php

namespace App\Domains\Sii\Models;

use Database\Factories\Sii\SiiDteEmitidoImpuestoAdicionalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiiDteEmitidoImpuestoAdicional extends Model
{
    use HasFactory;

    protected $table = 'sii_dte_emitido_impuesto_adicional';

    protected $fillable = [
        'dte_emitido_id',
        'dte_emitido_detalle_id',
        'codigo_impuesto',
        'tasa',
        'monto',
    ];

    protected $casts = [
        'codigo_impuesto' => 'integer',
        'tasa'            => 'decimal:2',
        'monto'           => 'decimal:2',
    ];

    protected static function newFactory(): SiiDteEmitidoImpuestoAdicionalFactory
    {
        return SiiDteEmitidoImpuestoAdicionalFactory::new();
    }

    public function dte(): BelongsTo
    {
        return $this->belongsTo(SiiDteEmitido::class, 'dte_emitido_id');
    }

    public function detalle(): BelongsTo
    {
        return $this->belongsTo(SiiDteEmitidoDetalle::class, 'dte_emitido_detalle_id');
    }
}
