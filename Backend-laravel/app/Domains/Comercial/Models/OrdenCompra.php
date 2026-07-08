<?php

namespace App\Domains\Comercial\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Traits\HasEmpresaScope;

/**
 * @property int $id
 * @property int $empresa_id
 * @property int|null $proveedor_id
 * @property string $numero_oc
 * @property \Illuminate\Support\Carbon $fecha_emision
 * @property \Illuminate\Support\Carbon|null $fecha_entrega_esperada
 * @property string $estado
 * @property string $moneda
 * @property string $tipo_cambio
 * @property string $subtotal
 * @property string $impuesto
 * @property string $total
 * @property string|null $notas
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Domains\Core\Models\Empresa $empresa
 * @property-read \App\Domains\Comercial\Models\Proveedor|null $proveedor
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Domains\Comercial\Models\DetalleOrdenCompra> $detalles
 */
class OrdenCompra extends Model
{
    use SoftDeletes;
    use HasEmpresaScope;

    protected $table = 'ordenes_compra';

    protected $fillable = [
        'empresa_id',
        'proveedor_id',
        'numero_oc',
        'fecha_emision',
        'fecha_entrega_esperada',
        'estado',
        'moneda',
        'tipo_cambio',
        'subtotal',
        'impuesto',
        'total',
        'notas',
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'fecha_entrega_esperada' => 'date',
        'tipo_cambio' => 'decimal:4',
        'subtotal' => 'decimal:2',
        'impuesto' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleOrdenCompra::class, 'orden_compra_id');
    }

    public function documentosAdjuntos(): HasMany
    {
        return $this->hasMany(DocumentoAdjunto::class, 'orden_compra_id');
    }
}
