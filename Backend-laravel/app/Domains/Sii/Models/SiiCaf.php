<?php

namespace App\Domains\Sii\Models;

use App\Domains\Core\Models\Empresa;
use Database\Factories\Sii\SiiCafFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * CAF (Codigo de Autorizacion de Folios) por empresa y tipo DTE.
 *
 * SEGURIDAD: rsa_sk_cifrada y xml_completo_cifrado NUNCA en JSON.
 */
class SiiCaf extends Model
{
    use HasFactory;

    public const ESTADO_ACTIVO   = 'activo';
    public const ESTADO_AGOTADO  = 'agotado';
    public const ESTADO_VENCIDO  = 'vencido';
    public const ESTADO_REVOCADO = 'revocado';

    protected $table = 'sii_caf';

    protected $fillable = [
        'empresa_id',
        'tipo_dte',
        'folio_desde',
        'folio_hasta',
        'folio_actual',
        'folios_usados',
        'folios_huerfanos',
        'fecha_autorizacion',
        'fecha_vencimiento',
        'rut_empresa_caf',
        'razon_social_caf',
        'sii_idk',
        'rsa_sk_cifrada',
        'xml_completo_cifrado',
        'rsa_pubk',
        'firma_caf',
        'estado',
    ];

    protected $hidden = [
        'rsa_sk_cifrada',
        'xml_completo_cifrado',
    ];

    protected $casts = [
        'tipo_dte'           => 'integer',
        'folio_desde'        => 'integer',
        'folio_hasta'        => 'integer',
        'folio_actual'       => 'integer',
        'folios_usados'      => 'integer',
        'folios_huerfanos'   => 'integer',
        'fecha_autorizacion' => 'date',
        'fecha_vencimiento'  => 'date',
    ];

    protected static function newFactory(): SiiCafFactory
    {
        return SiiCafFactory::new();
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function folios(): HasMany
    {
        return $this->hasMany(SiiCafFolioUso::class, 'caf_id');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estado', self::ESTADO_ACTIVO);
    }

    public function scopePorEmpresa(Builder $query, int $empresaId): Builder
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function scopePorTipo(Builder $query, int $tipoDte): Builder
    {
        return $query->where('tipo_dte', $tipoDte);
    }

    public function scopeConFoliosDisponibles(Builder $query): Builder
    {
        return $query->whereColumn('folio_actual', '<=', 'folio_hasta');
    }

    public function foliosDisponibles(): int
    {
        return max(0, $this->folio_hasta - $this->folio_actual + 1);
    }

    public function estaAgotado(): bool
    {
        return $this->folio_actual > $this->folio_hasta;
    }

    public function estaVencido(): bool
    {
        return $this->fecha_vencimiento !== null && $this->fecha_vencimiento->isPast();
    }
}
