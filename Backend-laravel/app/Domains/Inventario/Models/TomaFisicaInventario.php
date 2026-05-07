<?php

namespace App\Domains\Inventario\Models;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TomaFisicaInventario extends Model
{
    protected $table = 'inventario_tomas_fisicas';

    public const ESTADO_BORRADOR = 'BORRADOR';
    public const ESTADO_EN_CONTEO = 'EN_CONTEO';
    public const ESTADO_CERRADA = 'CERRADA';
    public const ESTADO_AJUSTADA = 'AJUSTADA';
    public const ESTADO_CANCELADA = 'CANCELADA';

    public const TIPO_GENERAL = 'GENERAL';
    public const TIPO_BODEGA = 'BODEGA';
    public const TIPO_CICLICA = 'CICLICA';

    protected $fillable = [
        'empresa_id',
        'codigo_toma',
        'estado',
        'tipo',
        'bodega_id',
        'referencia',
        'motivo',
        'observacion',
        'origen_modulo',
        'origen_id',
        'creado_por',
        'cerrado_por',
        'ajustado_por',
        'cancelado_por',
        'fecha_inicio',
        'fecha_cierre',
        'fecha_ajuste',
        'fecha_cancelacion',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'bodega_id' => 'integer',
        'origen_id' => 'integer',
        'creado_por' => 'integer',
        'cerrado_por' => 'integer',
        'ajustado_por' => 'integer',
        'cancelado_por' => 'integer',

        'fecha_inicio' => 'datetime',
        'fecha_cierre' => 'datetime',
        'fecha_ajuste' => 'datetime',
        'fecha_cancelacion' => 'datetime',
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

    public function bodega(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }

    public function ajustadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ajustado_por');
    }

    public function canceladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelado_por');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(TomaFisicaDetalleInventario::class, 'toma_fisica_id');
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

    public function scopeCodigo(Builder $query, string $codigoToma): Builder
    {
        return $query->where('codigo_toma', $codigoToma);
    }

    public function scopeEstado(Builder $query, string $estado): Builder
    {
        return $query->where('estado', $estado);
    }

    public function scopeEstados(Builder $query, array $estados): Builder
    {
        return $query->whereIn('estado', $estados);
    }

    public function scopeTipo(Builder $query, string $tipo): Builder
    {
        return $query->where('tipo', $tipo);
    }

    public function scopeBodega(Builder $query, int $bodegaId): Builder
    {
        return $query->where('bodega_id', $bodegaId);
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
        return $query->whereDate('created_at', '>=', $fecha);
    }

    public function scopeHasta(Builder $query, string $fecha): Builder
    {
        return $query->whereDate('created_at', '<=', $fecha);
    }

    public function scopeIniciadasDesde(Builder $query, string $fecha): Builder
    {
        return $query->whereDate('fecha_inicio', '>=', $fecha);
    }

    public function scopeCerradasDesde(Builder $query, string $fecha): Builder
    {
        return $query->whereDate('fecha_cierre', '>=', $fecha);
    }

    public function scopeAjustadasDesde(Builder $query, string $fecha): Builder
    {
        return $query->whereDate('fecha_ajuste', '>=', $fecha);
    }

    public function scopeMasRecientes(Builder $query): Builder
    {
        return $query
            ->orderByDesc('created_at')
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
            self::ESTADO_BORRADOR,
            self::ESTADO_EN_CONTEO,
            self::ESTADO_CERRADA,
            self::ESTADO_AJUSTADA,
            self::ESTADO_CANCELADA,
        ];
    }

    public static function tiposPermitidos(): array
    {
        return [
            self::TIPO_GENERAL,
            self::TIPO_BODEGA,
            self::TIPO_CICLICA,
        ];
    }

    public static function estadosFinales(): array
    {
        return [
            self::ESTADO_AJUSTADA,
            self::ESTADO_CANCELADA,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers de estado
    |--------------------------------------------------------------------------
    */

    public function estaBorrador(): bool
    {
        return $this->estado === self::ESTADO_BORRADOR;
    }

    public function estaEnConteo(): bool
    {
        return $this->estado === self::ESTADO_EN_CONTEO;
    }

    public function estaCerrada(): bool
    {
        return $this->estado === self::ESTADO_CERRADA;
    }

    public function estaAjustada(): bool
    {
        return $this->estado === self::ESTADO_AJUSTADA;
    }

    public function estaCancelada(): bool
    {
        return $this->estado === self::ESTADO_CANCELADA;
    }

    public function estaFinalizada(): bool
    {
        return in_array($this->estado, self::estadosFinales(), true);
    }

    public function puedeIniciarse(): bool
    {
        return $this->estaBorrador();
    }

    public function puedeContarse(): bool
    {
        return $this->estaEnConteo();
    }

    public function puedeCerrarse(): bool
    {
        return $this->estaEnConteo();
    }

    public function puedeAjustarse(): bool
    {
        return $this->estaCerrada();
    }

    public function puedeCancelarse(): bool
    {
        return in_array($this->estado, [
            self::ESTADO_BORRADOR,
            self::ESTADO_EN_CONTEO,
        ], true);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers de tipo
    |--------------------------------------------------------------------------
    */

    public function esGeneral(): bool
    {
        return $this->tipo === self::TIPO_GENERAL;
    }

    public function esPorBodega(): bool
    {
        return $this->tipo === self::TIPO_BODEGA;
    }

    public function esCiclica(): bool
    {
        return $this->tipo === self::TIPO_CICLICA;
    }

    public function requiereBodegaCabecera(): bool
    {
        return in_array($this->tipo, [
            self::TIPO_BODEGA,
            self::TIPO_CICLICA,
        ], true);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers de dominio
    |--------------------------------------------------------------------------
    */

    public function perteneceAEmpresa(int $empresaId): bool
    {
        return (int) $this->empresa_id === $empresaId;
    }

    public function tieneBodega(): bool
    {
        return $this->bodega_id !== null;
    }

    public function tieneOrigen(): bool
    {
        return $this->origen_modulo !== null || $this->origen_id !== null;
    }

    public function tieneDetalles(): bool
    {
        return $this->detalles()->exists();
    }

    public function tieneDetallesPendientesDeConteo(): bool
    {
        return $this->detalles()
            ->whereNull('stock_contado')
            ->exists();
    }

    public function tieneDiferencias(): bool
    {
        return $this->detalles()
            ->where('diferencia', '!=', 0)
            ->exists();
    }

    public function tieneDetallesAjustados(): bool
    {
        return $this->detalles()
            ->whereNotNull('movimiento_ajuste_id')
            ->exists();
    }
}