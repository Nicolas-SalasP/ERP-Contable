<?php

namespace App\Domains\Sii\Models;

use Database\Factories\Sii\SiiDteEmitidoTrasladoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Datos especificos de Guia de Despacho 52 (1:1 con SiiDteEmitido).
 */
class SiiDteEmitidoTraslado extends Model
{
    use HasFactory;

    protected $table = 'sii_dte_emitido_traslado';

    // IndTraslado segun XSD oficial.
    public const IND_OPERACION_CONSTITUYE_VENTA = 1;
    public const IND_VENTA_POR_EFECTUAR         = 2;
    public const IND_CONSIGNACIONES             = 3;
    public const IND_ENTREGA_GRATUITA           = 4;
    public const IND_TRASLADO_INTERNO           = 5;
    public const IND_OTROS_TRASLADOS            = 6;
    public const IND_GUIA_DEVOLUCION            = 7;
    public const IND_TRASLADO_EXPORTACION       = 8;

    protected $fillable = [
        'dte_emitido_id',
        'indicador_traslado',
        'rut_chofer',
        'nombre_chofer',
        'patente',
        'rut_transportista',
        'direccion_destino',
        'comuna_destino',
        'ciudad_destino',
    ];

    protected $casts = [
        'indicador_traslado' => 'integer',
    ];

    protected static function newFactory(): SiiDteEmitidoTrasladoFactory
    {
        return SiiDteEmitidoTrasladoFactory::new();
    }

    public function dte(): BelongsTo
    {
        return $this->belongsTo(SiiDteEmitido::class, 'dte_emitido_id');
    }

    public function madera(): HasOne
    {
        return $this->hasOne(SiiDteEmitidoMadera::class, 'dte_emitido_traslado_id');
    }
}
