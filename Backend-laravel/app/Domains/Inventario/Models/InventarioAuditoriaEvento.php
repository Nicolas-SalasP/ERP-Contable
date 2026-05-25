<?php

namespace App\Domains\Inventario\Models;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioAuditoriaEvento extends Model
{
    protected $table = 'inventario_auditoria_eventos';

    public const MODULO_INVENTARIO = 'INVENTARIO';

    public const SEVERIDAD_INFO = 'INFO';
    public const SEVERIDAD_WARNING = 'WARNING';
    public const SEVERIDAD_CRITICAL = 'CRITICAL';

    public const ESTADO_REGISTRADO = 'REGISTRADO';
    public const ESTADO_OBSERVADO = 'OBSERVADO';
    public const ESTADO_RESUELTO = 'RESUELTO';
    public const ESTADO_IGNORADO = 'IGNORADO';

    public const ACCION_PRODUCTO_CREADO = 'PRODUCTO_CREADO';
    public const ACCION_PRODUCTO_ACTUALIZADO = 'PRODUCTO_ACTUALIZADO';
    public const ACCION_MOVIMIENTO_CREADO = 'MOVIMIENTO_CREADO';
    public const ACCION_AJUSTE_CRITICO_CREADO = 'AJUSTE_CRITICO_CREADO';
    public const ACCION_MERMA_REGISTRADA = 'MERMA_REGISTRADA';
    public const ACCION_RESERVA_CREADA = 'RESERVA_CREADA';
    public const ACCION_RESERVA_CONFIRMADA = 'RESERVA_CONFIRMADA';
    public const ACCION_RESERVA_CANCELADA = 'RESERVA_CANCELADA';
    public const ACCION_PICKING_CREADO = 'PICKING_CREADO';
    public const ACCION_PICKING_CONFIRMADO = 'PICKING_CONFIRMADO';
    public const ACCION_PICKING_CANCELADO = 'PICKING_CANCELADO';
    public const ACCION_PACKING_CREADO = 'PACKING_CREADO';
    public const ACCION_PACKING_CONFIRMADO = 'PACKING_CONFIRMADO';
    public const ACCION_PACKING_CANCELADO = 'PACKING_CANCELADO';
    public const ACCION_DESPACHO_CREADO = 'DESPACHO_CREADO';
    public const ACCION_DESPACHO_INICIADO = 'DESPACHO_INICIADO';
    public const ACCION_DESPACHO_CONFIRMADO = 'DESPACHO_CONFIRMADO';
    public const ACCION_DESPACHO_CANCELADO = 'DESPACHO_CANCELADO';
    public const ACCION_DEVOLUCION_CREADA = 'DEVOLUCION_CREADA';
    public const ACCION_DEVOLUCION_CONFIRMADA = 'DEVOLUCION_CONFIRMADA';
    public const ACCION_DEVOLUCION_CANCELADA = 'DEVOLUCION_CANCELADA';
    public const ACCION_REVERSA_TOTAL_CONFIRMADA = 'REVERSA_TOTAL_CONFIRMADA';
    public const ACCION_REVERSA_PARCIAL_CONFIRMADA = 'REVERSA_PARCIAL_CONFIRMADA';
    public const ACCION_DIFERENCIA_POST_DESPACHO_REGISTRADA = 'DIFERENCIA_POST_DESPACHO_REGISTRADA';
    public const ACCION_TOMA_FISICA_CREADA = 'TOMA_FISICA_CREADA';
    public const ACCION_TOMA_FISICA_AJUSTADA = 'TOMA_FISICA_AJUSTADA';
    public const ACCION_STOCK_UBICACION_AJUSTADO = 'STOCK_UBICACION_AJUSTADO';
    public const ACCION_ACCESO_NO_AUTORIZADO = 'ACCESO_NO_AUTORIZADO';
    public const ACCION_OPERACION_BLOQUEADA = 'OPERACION_BLOQUEADA';

    protected $fillable = [
        'empresa_id',
        'usuario_id',
        'modulo',
        'accion',
        'entidad_tipo',
        'entidad_id',
        'severidad',
        'estado',
        'descripcion',
        'ip',
        'user_agent',
        'referencia',
        'motivo',
        'observacion',
        'origen_modulo',
        'origen_id',
        'metadata_json',
        'antes_json',
        'despues_json',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'usuario_id' => 'integer',
        'entidad_id' => 'integer',
        'origen_id' => 'integer',
        'metadata_json' => 'array',
        'antes_json' => 'array',
        'despues_json' => 'array',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function scopeEmpresa(Builder $query, int $empresaId): Builder
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function scopeAccion(Builder $query, string $accion): Builder
    {
        return $query->where('accion', $accion);
    }

    public function scopeEntidad(Builder $query, string $tipo, int $id): Builder
    {
        return $query->where('entidad_tipo', $tipo)->where('entidad_id', $id);
    }

    public function scopeSeveridad(Builder $query, string $severidad): Builder
    {
        return $query->where('severidad', $severidad);
    }

    public static function severidadesPermitidas(): array
    {
        return [self::SEVERIDAD_INFO, self::SEVERIDAD_WARNING, self::SEVERIDAD_CRITICAL];
    }

    public static function estadosPermitidos(): array
    {
        return [self::ESTADO_REGISTRADO, self::ESTADO_OBSERVADO, self::ESTADO_RESUELTO, self::ESTADO_IGNORADO];
    }

    public static function accionesPermitidas(): array
    {
        return [
            self::ACCION_PRODUCTO_CREADO,
            self::ACCION_PRODUCTO_ACTUALIZADO,
            self::ACCION_MOVIMIENTO_CREADO,
            self::ACCION_AJUSTE_CRITICO_CREADO,
            self::ACCION_MERMA_REGISTRADA,
            self::ACCION_RESERVA_CREADA,
            self::ACCION_RESERVA_CONFIRMADA,
            self::ACCION_RESERVA_CANCELADA,
            self::ACCION_PICKING_CREADO,
            self::ACCION_PICKING_CONFIRMADO,
            self::ACCION_PICKING_CANCELADO,
            self::ACCION_PACKING_CREADO,
            self::ACCION_PACKING_CONFIRMADO,
            self::ACCION_PACKING_CANCELADO,
            self::ACCION_DESPACHO_CREADO,
            self::ACCION_DESPACHO_INICIADO,
            self::ACCION_DESPACHO_CONFIRMADO,
            self::ACCION_DESPACHO_CANCELADO,
            self::ACCION_DEVOLUCION_CREADA,
            self::ACCION_DEVOLUCION_CONFIRMADA,
            self::ACCION_DEVOLUCION_CANCELADA,
            self::ACCION_REVERSA_TOTAL_CONFIRMADA,
            self::ACCION_REVERSA_PARCIAL_CONFIRMADA,
            self::ACCION_DIFERENCIA_POST_DESPACHO_REGISTRADA,
            self::ACCION_TOMA_FISICA_CREADA,
            self::ACCION_TOMA_FISICA_AJUSTADA,
            self::ACCION_STOCK_UBICACION_AJUSTADO,
            self::ACCION_ACCESO_NO_AUTORIZADO,
            self::ACCION_OPERACION_BLOQUEADA,
        ];
    }
}
