<?php

namespace App\Domains\Inventario\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MovimientoInventario extends Model
{
    protected $table = 'inventario_movimientos';

    public const TIPO_ENTRADA = 'entrada';
    public const TIPO_SALIDA = 'salida';
    public const TIPO_TRASPASO = 'traspaso';
    public const TIPO_AJUSTE_POSITIVO = 'ajuste_positivo';
    public const TIPO_AJUSTE_NEGATIVO = 'ajuste_negativo';

    public const MOTIVO_COMPRA = 'compra';
    public const MOTIVO_VENTA_INTERNA = 'venta_interna';
    public const MOTIVO_TRASPASO_BODEGA = 'traspaso_bodega';
    public const MOTIVO_CORRECCION_STOCK = 'correccion_stock';
    public const MOTIVO_MERMA = 'merma';
    public const MOTIVO_PERDIDA = 'perdida';
    public const MOTIVO_DEVOLUCION = 'devolucion';
    public const MOTIVO_INGRESO_MANUAL = 'ingreso_manual';
    public const MOTIVO_EGRESO_MANUAL = 'egreso_manual';

    protected $fillable = [
        'empresa_id',
        'producto_id',
        'tipo',
        'bodega_origen_id',
        'bodega_destino_id',
        'cantidad',
        'stock_origen_antes',
        'stock_origen_despues',
        'stock_destino_antes',
        'stock_destino_despues',
        'costo_unitario',
        'costo_total',
        'referencia',
        'motivo',
        'observacion',
        'created_by',
        'fecha_movimiento',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'producto_id' => 'integer',
        'bodega_origen_id' => 'integer',
        'bodega_destino_id' => 'integer',
        'created_by' => 'integer',

        'cantidad' => 'decimal:4',
        'stock_origen_antes' => 'decimal:4',
        'stock_origen_despues' => 'decimal:4',
        'stock_destino_antes' => 'decimal:4',
        'stock_destino_despues' => 'decimal:4',
        'costo_unitario' => 'decimal:4',
        'costo_total' => 'decimal:4',

        'fecha_movimiento' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function bodegaOrigen(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_origen_id');
    }

    public function bodegaDestino(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_destino_id');
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(MovimientoLoteInventario::class, 'movimiento_inventario_id');
    }

    public function consumosReserva(): HasMany
    {
        return $this->hasMany(ReservaConsumoInventario::class, 'movimiento_inventario_id');
    }
    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeEmpresa(Builder $query, int $empresaId): Builder
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function scopeProducto(Builder $query, int $productoId): Builder
    {
        return $query->where('producto_id', $productoId);
    }

    public function scopeTipo(Builder $query, string $tipo): Builder
    {
        return $query->where('tipo', $tipo);
    }

    public function scopeBodega(Builder $query, int $bodegaId): Builder
    {
        return $query->where(function (Builder $subQuery) use ($bodegaId) {
            $subQuery
                ->where('bodega_origen_id', $bodegaId)
                ->orWhere('bodega_destino_id', $bodegaId);
        });
    }

    public function scopeDesde(Builder $query, string $fecha): Builder
    {
        return $query->whereDate('fecha_movimiento', '>=', $fecha);
    }

    public function scopeHasta(Builder $query, string $fecha): Builder
    {
        return $query->whereDate('fecha_movimiento', '<=', $fecha);
    }

    public function scopeOrdenKardex(Builder $query): Builder
    {
        return $query
            ->orderBy('fecha_movimiento')
            ->orderBy('id');
    }

    public function scopeMasRecientes(Builder $query): Builder
    {
        return $query
            ->orderByDesc('fecha_movimiento')
            ->orderByDesc('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers estáticos
    |--------------------------------------------------------------------------
    */

    public static function tiposPermitidos(): array
    {
        return [
            self::TIPO_ENTRADA,
            self::TIPO_SALIDA,
            self::TIPO_TRASPASO,
            self::TIPO_AJUSTE_POSITIVO,
            self::TIPO_AJUSTE_NEGATIVO,
        ];
    }

    public static function motivosPermitidos(): array
    {
        return [
            self::MOTIVO_COMPRA,
            self::MOTIVO_VENTA_INTERNA,
            self::MOTIVO_TRASPASO_BODEGA,
            self::MOTIVO_CORRECCION_STOCK,
            self::MOTIVO_MERMA,
            self::MOTIVO_PERDIDA,
            self::MOTIVO_DEVOLUCION,
            self::MOTIVO_INGRESO_MANUAL,
            self::MOTIVO_EGRESO_MANUAL,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers de tipo
    |--------------------------------------------------------------------------
    */

    public function esEntrada(): bool
    {
        return $this->tipo === self::TIPO_ENTRADA;
    }

    public function esSalida(): bool
    {
        return $this->tipo === self::TIPO_SALIDA;
    }

    public function esTraspaso(): bool
    {
        return $this->tipo === self::TIPO_TRASPASO;
    }

    public function esAjustePositivo(): bool
    {
        return $this->tipo === self::TIPO_AJUSTE_POSITIVO;
    }

    public function esAjusteNegativo(): bool
    {
        return $this->tipo === self::TIPO_AJUSTE_NEGATIVO;
    }

    public function esMerma(): bool
    {
        return $this->motivo === self::MOTIVO_MERMA;
    }

    public function aumentaStock(): bool
    {
        return in_array($this->tipo, [
            self::TIPO_ENTRADA,
            self::TIPO_AJUSTE_POSITIVO,
        ], true);
    }

    public function disminuyeStock(): bool
    {
        return in_array($this->tipo, [
            self::TIPO_SALIDA,
            self::TIPO_AJUSTE_NEGATIVO,
        ], true);
    }

    public function mueveEntreBodegas(): bool
    {
        return $this->tipo === self::TIPO_TRASPASO;
    }

    public function tieneDetalleLote(): bool
    {
        return $this->lotes()->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers para Kardex
    |--------------------------------------------------------------------------
    */

    public function stockAntesPrincipal(): ?string
    {
        if ($this->stock_origen_antes !== null) {
            return $this->stock_origen_antes;
        }

        return $this->stock_destino_antes;
    }

    public function stockDespuesPrincipal(): ?string
    {
        if ($this->stock_origen_despues !== null) {
            return $this->stock_origen_despues;
        }

        return $this->stock_destino_despues;
    }

    public function bodegaPrincipalId(): ?int
    {
        if ($this->bodega_origen_id !== null) {
            return $this->bodega_origen_id;
        }

        return $this->bodega_destino_id;
    }
}