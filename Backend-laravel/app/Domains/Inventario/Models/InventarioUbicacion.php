<?php

namespace App\Domains\Inventario\Models;

use App\Domains\Core\Models\Empresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventarioUbicacion extends Model
{
    protected $table = 'inventario_ubicaciones';

    public const TIPO_ZONA = 'ZONA';
    public const TIPO_PASILLO = 'PASILLO';
    public const TIPO_ESTANTE = 'ESTANTE';
    public const TIPO_NIVEL = 'NIVEL';
    public const TIPO_POSICION = 'POSICION';
    public const TIPO_UBICACION = 'UBICACION';

    protected $fillable = [
        'empresa_id',
        'bodega_id',
        'ubicacion_padre_id',
        'codigo',
        'nombre',
        'tipo',
        'pasillo',
        'estante',
        'nivel',
        'posicion',
        'capacidad_maxima',
        'activo',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'bodega_id' => 'integer',
        'ubicacion_padre_id' => 'integer',
        'capacidad_maxima' => 'decimal:4',
        'activo' => 'boolean',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function bodega(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_id');
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'ubicacion_padre_id');
    }

    public function hijos(): HasMany
    {
        return $this->hasMany(self::class, 'ubicacion_padre_id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(StockUbicacionInventario::class, 'ubicacion_id');
    }

    public function scopeEmpresa(Builder $query, int $empresaId): Builder
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function scopeBodega(Builder $query, int $bodegaId): Builder
    {
        return $query->where('bodega_id', $bodegaId);
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public static function tiposPermitidos(): array
    {
        return [
            self::TIPO_ZONA,
            self::TIPO_PASILLO,
            self::TIPO_ESTANTE,
            self::TIPO_NIVEL,
            self::TIPO_POSICION,
            self::TIPO_UBICACION,
        ];
    }

    public function estaActiva(): bool
    {
        return $this->activo === true;
    }

    public function perteneceAEmpresa(int $empresaId): bool
    {
        return (int) $this->empresa_id === $empresaId;
    }

    public function perteneceABodega(int $bodegaId): bool
    {
        return (int) $this->bodega_id === $bodegaId;
    }
}
