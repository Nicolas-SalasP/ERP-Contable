<?php

namespace App\Domains\Inventario\Models;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InventarioPackingOrden extends Model
{
    protected $table = 'inventario_packing_ordenes';

    public const ESTADO_PENDIENTE = 'PENDIENTE';
    public const ESTADO_EN_EMPAQUE = 'EN_EMPAQUE';
    public const ESTADO_EMPACADO = 'EMPACADO';
    public const ESTADO_CON_DIFERENCIAS = 'CON_DIFERENCIAS';
    public const ESTADO_CANCELADO = 'CANCELADO';

    protected $fillable = [
        'empresa_id',
        'picking_orden_id',
        'bodega_id',
        'codigo',
        'estado',
        'observacion',
        'usuario_creador_id',
        'usuario_confirmador_id',
        'fecha_creacion',
        'fecha_inicio',
        'fecha_confirmacion',
        'fecha_cancelacion',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'picking_orden_id' => 'integer',
        'bodega_id' => 'integer',
        'usuario_creador_id' => 'integer',
        'usuario_confirmador_id' => 'integer',
        'fecha_creacion' => 'datetime',
        'fecha_inicio' => 'datetime',
        'fecha_confirmacion' => 'datetime',
        'fecha_cancelacion' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function pickingOrden(): BelongsTo
    {
        return $this->belongsTo(InventarioPickingOrden::class, 'picking_orden_id');
    }

    public function bodega(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_id');
    }

    public function usuarioCreador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_creador_id');
    }

    public function usuarioConfirmador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_confirmador_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(InventarioPackingDetalle::class, 'packing_orden_id');
    }

    public function despacho(): HasOne
    {
        return $this->hasOne(InventarioDespachoOrden::class, 'packing_orden_id');
    }

    public function scopeEmpresa(Builder $query, int $empresaId): Builder
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function puedeIniciarse(): bool
    {
        return $this->estado === self::ESTADO_PENDIENTE;
    }

    public function puedeConfirmarse(): bool
    {
        return $this->estado === self::ESTADO_EN_EMPAQUE;
    }

    public function puedeCancelarse(): bool
    {
        return in_array($this->estado, [self::ESTADO_PENDIENTE, self::ESTADO_EN_EMPAQUE, self::ESTADO_CON_DIFERENCIAS], true);
    }
}
