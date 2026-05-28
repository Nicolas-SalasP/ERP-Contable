<?php

namespace App\Domains\Inventario\Models;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InventarioPickingOrden extends Model
{
    protected $table = 'inventario_picking_ordenes';

    public const ESTADO_BORRADOR = 'BORRADOR';
    public const ESTADO_PENDIENTE = 'PENDIENTE';
    public const ESTADO_EN_PREPARACION = 'EN_PREPARACION';
    public const ESTADO_PICKING_COMPLETO = 'PICKING_COMPLETO';
    public const ESTADO_CON_DIFERENCIAS = 'CON_DIFERENCIAS';
    public const ESTADO_CANCELADO = 'CANCELADO';

    public const PRIORIDAD_BAJA = 'BAJA';
    public const PRIORIDAD_NORMAL = 'NORMAL';
    public const PRIORIDAD_ALTA = 'ALTA';
    public const PRIORIDAD_URGENTE = 'URGENTE';

    protected $fillable = [
        'empresa_id',
        'bodega_id',
        'reserva_id',
        'codigo',
        'estado',
        'prioridad',
        'referencia',
        'motivo',
        'observacion',
        'origen_modulo',
        'origen_id',
        'usuario_creador_id',
        'usuario_asignado_id',
        'fecha_creacion',
        'fecha_asignacion',
        'fecha_inicio',
        'fecha_confirmacion',
        'fecha_cancelacion',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'bodega_id' => 'integer',
        'reserva_id' => 'integer',
        'origen_id' => 'integer',
        'usuario_creador_id' => 'integer',
        'usuario_asignado_id' => 'integer',
        'fecha_creacion' => 'datetime',
        'fecha_asignacion' => 'datetime',
        'fecha_inicio' => 'datetime',
        'fecha_confirmacion' => 'datetime',
        'fecha_cancelacion' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function bodega(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_id');
    }

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(ReservaInventario::class, 'reserva_id');
    }

    public function usuarioCreador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_creador_id');
    }

    public function usuarioAsignado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_asignado_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(InventarioPickingDetalle::class, 'picking_orden_id');
    }

    public function packing(): HasOne
    {
        return $this->hasOne(InventarioPackingOrden::class, 'picking_orden_id');
    }

    public function scopeEmpresa(Builder $query, int $empresaId): Builder
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function scopeEstado(Builder $query, string $estado): Builder
    {
        return $query->where('estado', $estado);
    }

    public static function estadosPermitidos(): array
    {
        return [
            self::ESTADO_BORRADOR,
            self::ESTADO_PENDIENTE,
            self::ESTADO_EN_PREPARACION,
            self::ESTADO_PICKING_COMPLETO,
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

    public function puedeAsignarse(): bool
    {
        return in_array($this->estado, [self::ESTADO_BORRADOR, self::ESTADO_PENDIENTE], true)
            && $this->reserva_id === null;
    }

    public function puedeIniciarse(): bool
    {
        return in_array($this->estado, [self::ESTADO_PENDIENTE, self::ESTADO_CON_DIFERENCIAS], true)
            && $this->reserva_id !== null;
    }

    public function puedeConfirmarse(): bool
    {
        return in_array($this->estado, [self::ESTADO_EN_PREPARACION], true)
            && $this->reserva_id !== null;
    }

    public function puedeCancelarse(): bool
    {
        return $this->fecha_confirmacion === null
            && in_array($this->estado, [self::ESTADO_BORRADOR, self::ESTADO_PENDIENTE, self::ESTADO_EN_PREPARACION, self::ESTADO_CON_DIFERENCIAS], true);
    }
}
