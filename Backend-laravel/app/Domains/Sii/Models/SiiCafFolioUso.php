<?php

namespace App\Domains\Sii\Models;

use App\Domains\Core\Models\User;
use Database\Factories\Sii\SiiCafFolioUsoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiiCafFolioUso extends Model
{
    use HasFactory;

    public const ESTADO_RESERVADO = 'RESERVADO';
    public const ESTADO_USADO     = 'USADO';
    public const ESTADO_HUERFANO  = 'HUERFANO';
    public const ESTADO_ANULADO   = 'ANULADO';

    protected $table = 'sii_caf_folio_uso';

    protected $fillable = [
        'caf_id',
        'folio',
        'dte_emitido_id',
        'estado',
        'reservado_at',
        'usado_at',
        'liberado_at',
        'razon_liberacion',
        'usuario_reservo_id',
    ];

    protected $casts = [
        'caf_id'             => 'integer',
        'folio'              => 'integer',
        'dte_emitido_id'     => 'integer',
        'usuario_reservo_id' => 'integer',
        'reservado_at'       => 'datetime',
        'usado_at'           => 'datetime',
        'liberado_at'        => 'datetime',
    ];

    protected static function newFactory(): SiiCafFolioUsoFactory
    {
        return SiiCafFolioUsoFactory::new();
    }

    public function caf(): BelongsTo
    {
        return $this->belongsTo(SiiCaf::class, 'caf_id');
    }

    public function dteEmitido(): BelongsTo
    {
        return $this->belongsTo(SiiDteEmitido::class, 'dte_emitido_id');
    }

    public function usuarioReservo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_reservo_id');
    }

    public function scopeReservados(Builder $query): Builder
    {
        return $query->where('estado', self::ESTADO_RESERVADO);
    }

    public function scopeUsados(Builder $query): Builder
    {
        return $query->where('estado', self::ESTADO_USADO);
    }

    public function scopeHuerfanos(Builder $query): Builder
    {
        return $query->where('estado', self::ESTADO_HUERFANO);
    }
}
