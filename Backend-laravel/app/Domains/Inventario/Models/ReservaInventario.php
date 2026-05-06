<?php

namespace App\Domains\Inventario\Models;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReservaInventario extends Model
{
    protected $table = 'inventario_reservas';

    public const ESTADO_ACTIVA = 'ACTIVA';
    public const ESTADO_PARCIALMENTE_LIBERADA = 'PARCIALMENTE_LIBERADA';
    public const ESTADO_PARCIALMENTE_CONSUMIDA = 'PARCIALMENTE_CONSUMIDA';
    public const ESTADO_CONSUMIDA = 'CONSUMIDA';
    public const ESTADO_CANCELADA = 'CANCELADA';
    public const ESTADO_EXPIRADA = 'EXPIRADA';

    protected $fillable = [
        'empresa_id',
        'codigo_reserva',
        'estado',
        'referencia',
        'motivo',
        'observacion',
        'origen_modulo',
        'origen_id',
        'reservado_por',
        'fecha_reserva',
        'fecha_expiracion',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'origen_id' => 'integer',
        'reservado_por' => 'integer',
        'fecha_reserva' => 'datetime',
        'fecha_expiracion' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function reservadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reservado_por');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(ReservaDetalleInventario::class, 'reserva_id');
    }

    public function consumos(): HasMany
    {
        return $this->hasMany(ReservaConsumoInventario::class, 'reserva_id');
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

    public function scopeCodigo(Builder $query, string $codigoReserva): Builder
    {
        return $query->where('codigo_reserva', $codigoReserva);
    }

    public function scopeEstado(Builder $query, string $estado): Builder
    {
        return $query->where('estado', $estado);
    }

    public function scopeEstados(Builder $query, array $estados): Builder
    {
        return $query->whereIn('estado', $estados);
    }

    public function scopeComprometenDisponibilidad(Builder $query): Builder
    {
        return $query->whereIn('estado', self::estadosQueComprometenDisponibilidad());
    }

    public function scopeNoComprometenDisponibilidad(Builder $query): Builder
    {
        return $query->whereIn('estado', self::estadosQueNoComprometenDisponibilidad());
    }

    public function scopeReferencia(Builder $query, string $referencia): Builder
    {
        return $query->where('referencia', $referencia);
    }

    public function scopeOrigen(Builder $query, string $origenModulo, ?int $origenId = null): Builder
    {
        $query->where('origen_modulo', $origenModulo);

        if ($origenId !== null) {
            $query->where('origen_id', $origenId);
        }

        return $query;
    }

    public function scopeDesde(Builder $query, string $fecha): Builder
    {
        return $query->whereDate('fecha_reserva', '>=', $fecha);
    }

    public function scopeHasta(Builder $query, string $fecha): Builder
    {
        return $query->whereDate('fecha_reserva', '<=', $fecha);
    }

    public function scopeExpiradas(Builder $query): Builder
    {
        return $query
            ->whereNotNull('fecha_expiracion')
            ->whereDate('fecha_expiracion', '<', now()->toDateString())
            ->whereIn('estado', self::estadosQueComprometenDisponibilidad());
    }

    public function scopeMasRecientes(Builder $query): Builder
    {
        return $query
            ->orderByDesc('fecha_reserva')
            ->orderByDesc('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers estáticos
    |--------------------------------------------------------------------------
    */

    public static function estadosPermitidos(): array
    {
        return [
            self::ESTADO_ACTIVA,
            self::ESTADO_PARCIALMENTE_LIBERADA,
            self::ESTADO_PARCIALMENTE_CONSUMIDA,
            self::ESTADO_CONSUMIDA,
            self::ESTADO_CANCELADA,
            self::ESTADO_EXPIRADA,
        ];
    }

    public static function estadosQueComprometenDisponibilidad(): array
    {
        return [
            self::ESTADO_ACTIVA,
            self::ESTADO_PARCIALMENTE_LIBERADA,
            self::ESTADO_PARCIALMENTE_CONSUMIDA,
        ];
    }

    public static function estadosQueNoComprometenDisponibilidad(): array
    {
        return [
            self::ESTADO_CONSUMIDA,
            self::ESTADO_CANCELADA,
            self::ESTADO_EXPIRADA,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers de estado
    |--------------------------------------------------------------------------
    */

    public function estaActiva(): bool
    {
        return $this->estado === self::ESTADO_ACTIVA;
    }

    public function estaParcialmenteLiberada(): bool
    {
        return $this->estado === self::ESTADO_PARCIALMENTE_LIBERADA;
    }

    public function estaParcialmenteConsumida(): bool
    {
        return $this->estado === self::ESTADO_PARCIALMENTE_CONSUMIDA;
    }

    public function estaConsumida(): bool
    {
        return $this->estado === self::ESTADO_CONSUMIDA;
    }

    public function estaCancelada(): bool
    {
        return $this->estado === self::ESTADO_CANCELADA;
    }

    public function estaExpirada(): bool
    {
        return $this->estado === self::ESTADO_EXPIRADA;
    }

    public function comprometeDisponibilidad(): bool
    {
        return in_array($this->estado, self::estadosQueComprometenDisponibilidad(), true);
    }

    public function puedeCancelarse(): bool
    {
        return $this->comprometeDisponibilidad();
    }

    public function puedeLiberarse(): bool
    {
        return $this->comprometeDisponibilidad();
    }

    public function puedeConsumirse(): bool
    {
        return $this->comprometeDisponibilidad();
    }

    public function fechaExpiracionCumplida(): bool
    {
        if ($this->fecha_expiracion === null) {
            return false;
        }

        return $this->fecha_expiracion->toDateString() < now()->toDateString();
    }

    public function tieneReferenciaExterna(): bool
    {
        return !empty($this->referencia)
            || !empty($this->origen_modulo)
            || !empty($this->origen_id);
    }

    public function perteneceAEmpresa(int $empresaId): bool
    {
        return (int) $this->empresa_id === $empresaId;
    }
}