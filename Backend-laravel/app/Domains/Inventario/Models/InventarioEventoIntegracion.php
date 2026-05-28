<?php

namespace App\Domains\Inventario\Models;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioEventoIntegracion extends Model
{
    protected $table = 'inventario_eventos_integracion';

    public const MODULO_ORIGEN_INVENTARIO = 'INVENTARIO';

    public const ESTADO_PENDIENTE = 'PENDIENTE';
    public const ESTADO_PROCESADO = 'PROCESADO';
    public const ESTADO_IGNORADO = 'IGNORADO';
    public const ESTADO_ERROR = 'ERROR';

    public const PRIORIDAD_BAJA = 'BAJA';
    public const PRIORIDAD_NORMAL = 'NORMAL';
    public const PRIORIDAD_ALTA = 'ALTA';
    public const PRIORIDAD_CRITICA = 'CRITICA';

    public const EVENTO_MOVIMIENTO_CREADO = 'INVENTARIO_MOVIMIENTO_CREADO';
    public const EVENTO_AJUSTE_CRITICO_CREADO = 'INVENTARIO_AJUSTE_CRITICO_CREADO';
    public const EVENTO_MERMA_REGISTRADA = 'INVENTARIO_MERMA_REGISTRADA';
    public const EVENTO_RESERVA_CREADA = 'INVENTARIO_RESERVA_CREADA';
    public const EVENTO_RESERVA_CONFIRMADA = 'INVENTARIO_RESERVA_CONFIRMADA';
    public const EVENTO_RESERVA_CANCELADA = 'INVENTARIO_RESERVA_CANCELADA';
    public const EVENTO_RESERVA_LIBERADA = 'INVENTARIO_RESERVA_LIBERADA';
    public const EVENTO_RESERVA_CONSUMIDA = 'INVENTARIO_RESERVA_CONSUMIDA';
    public const EVENTO_PICKING_CREADO = 'INVENTARIO_PICKING_CREADO';
    public const EVENTO_PICKING_CONFIRMADO = 'INVENTARIO_PICKING_CONFIRMADO';
    public const EVENTO_PICKING_CANCELADO = 'INVENTARIO_PICKING_CANCELADO';
    public const EVENTO_PACKING_CREADO = 'INVENTARIO_PACKING_CREADO';
    public const EVENTO_PACKING_CONFIRMADO = 'INVENTARIO_PACKING_CONFIRMADO';
    public const EVENTO_PACKING_CANCELADO = 'INVENTARIO_PACKING_CANCELADO';
    public const EVENTO_DESPACHO_CREADO = 'INVENTARIO_DESPACHO_CREADO';
    public const EVENTO_DESPACHO_INICIADO = 'INVENTARIO_DESPACHO_INICIADO';
    public const EVENTO_DESPACHO_CONFIRMADO = 'INVENTARIO_DESPACHO_CONFIRMADO';
    public const EVENTO_DESPACHO_CANCELADO = 'INVENTARIO_DESPACHO_CANCELADO';
    public const EVENTO_DEVOLUCION_CREADA = 'INVENTARIO_DEVOLUCION_CREADA';
    public const EVENTO_DEVOLUCION_CONFIRMADA = 'INVENTARIO_DEVOLUCION_CONFIRMADA';
    public const EVENTO_DEVOLUCION_CANCELADA = 'INVENTARIO_DEVOLUCION_CANCELADA';
    public const EVENTO_REVERSA_TOTAL_CONFIRMADA = 'INVENTARIO_REVERSA_TOTAL_CONFIRMADA';
    public const EVENTO_REVERSA_PARCIAL_CONFIRMADA = 'INVENTARIO_REVERSA_PARCIAL_CONFIRMADA';
    public const EVENTO_DIFERENCIA_POST_DESPACHO_REGISTRADA = 'INVENTARIO_DIFERENCIA_POST_DESPACHO_REGISTRADA';
    public const EVENTO_TOMA_FISICA_AJUSTADA = 'INVENTARIO_TOMA_FISICA_AJUSTADA';
    public const EVENTO_STOCK_BAJO_DETECTADO = 'INVENTARIO_STOCK_BAJO_DETECTADO';
    public const EVENTO_STOCK_UBICACION_AJUSTADO = 'INVENTARIO_STOCK_UBICACION_AJUSTADO';

    protected $fillable = [
        'empresa_id',
        'usuario_id',
        'evento',
        'modulo_origen',
        'entidad_tipo',
        'entidad_id',
        'estado',
        'prioridad',
        'payload_json',
        'metadata_json',
        'correlacion_id',
        'origen_modulo',
        'origen_id',
        'procesado_at',
        'error_mensaje',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'usuario_id' => 'integer',
        'entidad_id' => 'integer',
        'origen_id' => 'integer',
        'payload_json' => 'array',
        'metadata_json' => 'array',
        'procesado_at' => 'datetime',
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

    public function scopeEvento(Builder $query, string $evento): Builder
    {
        return $query->where('evento', $evento);
    }

    public function scopeEstado(Builder $query, string $estado): Builder
    {
        return $query->where('estado', $estado);
    }

    public function scopeEntidad(Builder $query, string $tipo, int $id): Builder
    {
        return $query->where('entidad_tipo', $tipo)->where('entidad_id', $id);
    }

    public static function eventosPermitidos(): array
    {
        return [
            self::EVENTO_MOVIMIENTO_CREADO,
            self::EVENTO_AJUSTE_CRITICO_CREADO,
            self::EVENTO_MERMA_REGISTRADA,
            self::EVENTO_RESERVA_CREADA,
            self::EVENTO_RESERVA_CONFIRMADA,
            self::EVENTO_RESERVA_CANCELADA,
            self::EVENTO_RESERVA_LIBERADA,
            self::EVENTO_RESERVA_CONSUMIDA,
            self::EVENTO_PICKING_CREADO,
            self::EVENTO_PICKING_CONFIRMADO,
            self::EVENTO_PICKING_CANCELADO,
            self::EVENTO_PACKING_CREADO,
            self::EVENTO_PACKING_CONFIRMADO,
            self::EVENTO_PACKING_CANCELADO,
            self::EVENTO_DESPACHO_CREADO,
            self::EVENTO_DESPACHO_INICIADO,
            self::EVENTO_DESPACHO_CONFIRMADO,
            self::EVENTO_DESPACHO_CANCELADO,
            self::EVENTO_DEVOLUCION_CREADA,
            self::EVENTO_DEVOLUCION_CONFIRMADA,
            self::EVENTO_DEVOLUCION_CANCELADA,
            self::EVENTO_REVERSA_TOTAL_CONFIRMADA,
            self::EVENTO_REVERSA_PARCIAL_CONFIRMADA,
            self::EVENTO_DIFERENCIA_POST_DESPACHO_REGISTRADA,
            self::EVENTO_TOMA_FISICA_AJUSTADA,
            self::EVENTO_STOCK_BAJO_DETECTADO,
            self::EVENTO_STOCK_UBICACION_AJUSTADO,
        ];
    }

    public static function estadosPermitidos(): array
    {
        return [self::ESTADO_PENDIENTE, self::ESTADO_PROCESADO, self::ESTADO_IGNORADO, self::ESTADO_ERROR];
    }

    public static function prioridadesPermitidas(): array
    {
        return [self::PRIORIDAD_BAJA, self::PRIORIDAD_NORMAL, self::PRIORIDAD_ALTA, self::PRIORIDAD_CRITICA];
    }
}
