<?php

namespace App\Domains\Sii\Models;

use Database\Factories\Sii\SiiDteEmitidoMaderaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiiDteEmitidoMadera extends Model
{
    use HasFactory;

    protected $table = 'sii_dte_emitido_madera';

    protected $fillable = [
        'dte_emitido_traslado_id',
        'rol_predio_origen',
        'rol_predio_destino',
        'aviso_ejecucion',
        'codigo_plan_conaf',
        'georef_origen_lat',
        'georef_origen_lng',
        'georef_destino_lat',
        'georef_destino_lng',
    ];

    protected $casts = [
        'georef_origen_lat'  => 'decimal:7',
        'georef_origen_lng'  => 'decimal:7',
        'georef_destino_lat' => 'decimal:7',
        'georef_destino_lng' => 'decimal:7',
    ];

    protected static function newFactory(): SiiDteEmitidoMaderaFactory
    {
        return SiiDteEmitidoMaderaFactory::new();
    }

    public function traslado(): BelongsTo
    {
        return $this->belongsTo(SiiDteEmitidoTraslado::class, 'dte_emitido_traslado_id');
    }
}
