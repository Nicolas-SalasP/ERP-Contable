<?php

namespace App\Domains\Inventario\Models;

use App\Domains\Core\Models\Empresa;
use Illuminate\Database\Eloquent\Model;

class Bodega extends Model
{
    protected $table = 'inventario_bodegas';

    protected $fillable = [
        'empresa_id',
        'codigo',
        'nombre',
        'direccion',
        'estado',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function stocks()
    {
        return $this->hasMany(StockProducto::class, 'bodega_id');
    }

    public function stockLotes()
    {
        return $this->hasMany(StockLoteInventario::class, 'bodega_id');
    }

    public function movimientosOrigen()
    {
        return $this->hasMany(MovimientoInventario::class, 'bodega_origen_id');
    }

    public function movimientosDestino()
    {
        return $this->hasMany(MovimientoInventario::class, 'bodega_destino_id');
    }

    public function estaActiva(): bool
    {
        return $this->estado === 'ACTIVA';
    }

    public function perteneceAEmpresa(int $empresaId): bool
    {
        return (int) $this->empresa_id === $empresaId;
    }
}