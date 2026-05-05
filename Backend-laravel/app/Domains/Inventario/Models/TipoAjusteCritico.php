<?php

namespace App\Domains\Inventario\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoAjusteCritico extends Model
{
    protected $table = 'inventario_tipos_ajuste_critico';

    public const CODIGO_MERMA_OPERACIONAL = 'MERMA_OPERACIONAL';
    public const CODIGO_DETERIORO = 'DETERIORO';
    public const CODIGO_PERDIDA = 'PERDIDA';
    public const CODIGO_VENCIMIENTO = 'VENCIMIENTO';
    public const CODIGO_AJUSTE_CRITICO_NEGATIVO = 'AJUSTE_CRITICO_NEGATIVO';
    public const CODIGO_AJUSTE_CRITICO_POSITIVO = 'AJUSTE_CRITICO_POSITIVO';

    public const TIPO_MOVIMIENTO_AJUSTE_POSITIVO = 'ajuste_positivo';
    public const TIPO_MOVIMIENTO_AJUSTE_NEGATIVO = 'ajuste_negativo';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'tipo_movimiento',
        'requiere_stock',
        'activo',
    ];

    protected $casts = [
        'requiere_stock' => 'boolean',
        'activo' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    public function ajustesCriticos(): HasMany
    {
        return $this->hasMany(AjusteCriticoInventario::class, 'tipo_ajuste_critico_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActivo(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function scopeCodigo(Builder $query, string $codigo): Builder
    {
        return $query->where('codigo', $codigo);
    }

    public function scopeTipoMovimiento(Builder $query, string $tipoMovimiento): Builder
    {
        return $query->where('tipo_movimiento', $tipoMovimiento);
    }

    public function scopeOrdenado(Builder $query): Builder
    {
        return $query->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function esAjustePositivo(): bool
    {
        return $this->tipo_movimiento === self::TIPO_MOVIMIENTO_AJUSTE_POSITIVO;
    }

    public function esAjusteNegativo(): bool
    {
        return $this->tipo_movimiento === self::TIPO_MOVIMIENTO_AJUSTE_NEGATIVO;
    }

    public function exigeStockSuficiente(): bool
    {
        return $this->requiere_stock === true;
    }

    public static function codigosBase(): array
    {
        return [
            self::CODIGO_MERMA_OPERACIONAL,
            self::CODIGO_DETERIORO,
            self::CODIGO_PERDIDA,
            self::CODIGO_VENCIMIENTO,
            self::CODIGO_AJUSTE_CRITICO_NEGATIVO,
            self::CODIGO_AJUSTE_CRITICO_POSITIVO,
        ];
    }

    public static function tiposMovimientoPermitidos(): array
    {
        return [
            self::TIPO_MOVIMIENTO_AJUSTE_POSITIVO,
            self::TIPO_MOVIMIENTO_AJUSTE_NEGATIVO,
        ];
    }
}