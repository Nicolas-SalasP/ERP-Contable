<?php

namespace App\Domains\Inventario\Models;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventarioDespachoOrden extends Model
{
    protected $table = 'inventario_despacho_ordenes';

    public const ESTADO_PENDIENTE = 'PENDIENTE';
    public const ESTADO_EN_DESPACHO = 'EN_DESPACHO';
    public const ESTADO_DESPACHADO = 'DESPACHADO';
    public const ESTADO_CON_DIFERENCIAS = 'CON_DIFERENCIAS';
    public const ESTADO_CANCELADO = 'CANCELADO';

    public const PRIORIDAD_BAJA = 'BAJA';
    public const PRIORIDAD_NORMAL = 'NORMAL';
    public const PRIORIDAD_ALTA = 'ALTA';
    public const PRIORIDAD_URGENTE = 'URGENTE';

    protected $fillable = [
        'empresa_id',
        'packing_orden_id',
        'picking_orden_id',
        'reserva_id',
        'bodega_id',
        'codigo',
        'estado',
        'prioridad',
        'referencia',
        'motivo',
        'observacion',
        'origen_modulo',
        'origen_id',
        'usuario_creador_id',
        'usuario_confirmador_id',
        'fecha_creacion',
        'fecha_inicio',
        'fecha_confirmacion',
        'fecha_cancelacion',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'packing_orden_id' => 'integer',
        'picking_orden_id' => 'integer',
        'reserva_id' => 'integer',
        'bodega_id' => 'integer',
        'origen_id' => 'integer',
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

    public function packingOrden(): BelongsTo
    {
        return $this->belongsTo(InventarioPackingOrden::class, 'packing_orden_id');
    }

    public function pickingOrden(): BelongsTo
    {
        return $this->belongsTo(InventarioPickingOrden::class, 'picking_orden_id');
    }

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(ReservaInventario::class, 'reserva_id');
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
        return $this->hasMany(InventarioDespachoDetalle::class, 'despacho_orden_id');
    }

    public function devoluciones(): HasMany
    {
        return $this->hasMany(InventarioDevolucionOrden::class, 'despacho_orden_id');
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
        return $this->estado === self::ESTADO_EN_DESPACHO;
    }

    public function puedeCancelarse(): bool
    {
        return in_array($this->estado, [self::ESTADO_PENDIENTE, self::ESTADO_EN_DESPACHO], true);
    }

    public static function estadosPermitidos(): array
    {
        return [
            self::ESTADO_PENDIENTE,
            self::ESTADO_EN_DESPACHO,
            self::ESTADO_DESPACHADO,
            self::ESTADO_CON_DIFERENCIAS,
            self::ESTADO_CANCELADO,
        ];
    }

    public static function prioridadesPermitidas(): array
    {
        return [
            self::PRIORIDAD_BAJA,
            self::PRIORIDAD_NORMAL,
            self::PRIORIDAD_ALTA,
            self::PRIORIDAD_URGENTE,
        ];
    }
}
