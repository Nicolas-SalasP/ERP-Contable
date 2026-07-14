<?php

namespace App\Domains\Comercial\Models;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Traits\HasEmpresaScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read Cliente|null $cliente
 * @property-read EstadoCotizacion|null $estado
 * @property-read Collection<int, CotizacionDetalle> $detalles
 */
class Cotizacion extends Model
{
    use HasEmpresaScope;
    use SoftDeletes;

    protected $table = 'cotizaciones';

    const UPDATED_AT = null;

    protected $fillable = [
        'cliente_id',
        'nombre_cliente',
        'numero_cotizacion',
        'fecha_emision',
        'fecha_validez',
        'validez',
        'subtotal',
        'porcentaje_descuento',
        'monto_descuento',
        'monto_neto',
        'porcentaje_iva',
        'monto_iva',
        'monto_total',
        'total',
        'estado_id',
        'empresa_id',
        'es_afecta',
        'notas_condiciones',
        'metodo_pago',
        'condiciones_pago',
        'plazo_entrega',
        'comentarios',
        'garantia',
        'enviada_at',
        'usuario_envio_id',
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'fecha_validez' => 'date',
        'total' => 'decimal:2',
        'monto_total' => 'decimal:2',
        'es_afecta' => 'boolean',
        'enviada_at' => 'datetime',
    ];

    /** @return BelongsTo<Cliente, $this> */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /** @return BelongsTo<EstadoCotizacion, $this> */
    public function estado(): BelongsTo
    {
        return $this->belongsTo(EstadoCotizacion::class, 'estado_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** @return HasMany<CotizacionDetalle, $this> */
    public function detalles(): HasMany
    {
        return $this->hasMany(CotizacionDetalle::class);
    }

    /** @return HasMany<Factura, $this> */
    public function facturas(): HasMany
    {
        return $this->hasMany(Factura::class);
    }

    public function documentosAdjuntos(): HasMany
    {
        return $this->hasMany(DocumentoAdjunto::class);
    }

    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function (Cotizacion $cotizacion) {
            $cotizacion->detalles()->delete();
        });
    }
}
