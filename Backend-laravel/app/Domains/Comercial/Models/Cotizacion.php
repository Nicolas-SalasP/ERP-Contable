<?php

namespace App\Domains\Comercial\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Traits\HasEmpresaScope;

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
        'notas_condiciones'
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'fecha_validez' => 'date',
        'total' => 'decimal:2',
        'monto_total' => 'decimal:2',
        'es_afecta' => 'boolean',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function estado()
    {
        return $this->belongsTo(EstadoCotizacion::class, 'estado_id');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function detalles()
    {
        return $this->hasMany(CotizacionDetalle::class);
    }

    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function (Cotizacion $cotizacion) {
            $cotizacion->detalles()->delete();
        });
    }
}