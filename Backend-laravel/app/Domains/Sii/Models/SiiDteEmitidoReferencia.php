<?php

namespace App\Domains\Sii\Models;

use Database\Factories\Sii\SiiDteEmitidoReferenciaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiiDteEmitidoReferencia extends Model
{
    use HasFactory;

    protected $table = 'sii_dte_emitido_referencia';

    public const CODIGO_ANULA          = 1;
    public const CODIGO_CORRIGE_TEXTO  = 2;
    public const CODIGO_CORRIGE_MONTO  = 3;

    protected $fillable = [
        'dte_emitido_id',
        'numero_linea',
        'tipo_documento_referencia',
        'folio_referencia',
        'fecha_referencia',
        'codigo_referencia',
        'razon_referencia',
        'rut_otro_contribuyente',
    ];

    protected $casts = [
        'numero_linea'      => 'integer',
        'fecha_referencia'  => 'date',
        'codigo_referencia' => 'integer',
    ];

    protected static function newFactory(): SiiDteEmitidoReferenciaFactory
    {
        return SiiDteEmitidoReferenciaFactory::new();
    }

    public function dte(): BelongsTo
    {
        return $this->belongsTo(SiiDteEmitido::class, 'dte_emitido_id');
    }
}
