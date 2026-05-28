<?php

namespace App\Domains\Inventario\Models;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventarioDevolucionOrden extends Model
{
    protected $table = 'inventario_devolucion_ordenes';

    public const TIPO_DEVOLUCION = 'DEVOLUCION';
    public const TIPO_REVERSA_TOTAL = 'REVERSA_TOTAL';
    public const TIPO_REVERSA_PARCIAL = 'REVERSA_PARCIAL';
    public const TIPO_DIFERENCIA_POST_DESPACHO = 'DIFERENCIA_POST_DESPACHO';

    public const ESTADO_PENDIENTE = 'PENDIENTE';
    public const ESTADO_CONFIRMADA = 'CONFIRMADA';
    public const ESTADO_CANCELADA = 'CANCELADA';
    public const ESTADO_CON_DIFERENCIAS = 'CON_DIFERENCIAS';

    protected $fillable = [
        'empresa_id',
        'despacho_orden_id',
        'bodega_id',
        'codigo',
        'tipo',
        'estado',
        'motivo',
        'referencia',
        'observacion',
        'origen_modulo',
        'origen_id',
        'usuario_creador_id',
        'usuario_confirmador_id',
        'fecha_creacion',
        'fecha_confirmacion',
        'fecha_cancelacion',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'despacho_orden_id' => 'integer',
        'bodega_id' => 'integer',
        'origen_id' => 'integer',
        'usuario_creador_id' => 'integer',
        'usuario_confirmador_id' => 'integer',
        'fecha_creacion' => 'datetime',
        'fecha_confirmacion' => 'datetime',
        'fecha_cancelacion' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function despacho(): BelongsTo
    {
        return $this->belongsTo(InventarioDespachoOrden::class, 'despacho_orden_id');
    }

    public function bodega(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(InventarioDevolucionDetalle::class, 'devolucion_orden_id');
    }

    public function usuarioCreador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_creador_id');
    }

    public function usuarioConfirmador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_confirmador_id');
    }

    public function scopeEmpresa(Builder $query, int $empresaId): Builder
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function puedeConfirmarse(): bool
    {
        return $this->estado === self::ESTADO_PENDIENTE;
    }

    public function puedeCancelarse(): bool
    {
        return $this->estado === self::ESTADO_PENDIENTE;
    }

    public function esTipoFisico(): bool
    {
        return in_array($this->tipo, [
            self::TIPO_DEVOLUCION,
            self::TIPO_REVERSA_TOTAL,
            self::TIPO_REVERSA_PARCIAL,
        ], true);
    }

    public static function tiposPermitidos(): array
    {
        return [
            self::TIPO_DEVOLUCION,
            self::TIPO_REVERSA_TOTAL,
            self::TIPO_REVERSA_PARCIAL,
            self::TIPO_DIFERENCIA_POST_DESPACHO,
        ];
    }

    public static function estadosPermitidos(): array
    {
        return [
            self::ESTADO_PENDIENTE,
            self::ESTADO_CONFIRMADA,
            self::ESTADO_CANCELADA,
            self::ESTADO_CON_DIFERENCIAS,
        ];
    }
}
