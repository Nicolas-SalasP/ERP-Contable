<?php

namespace App\Domains\Inventario\Models;

use App\Domains\Core\Models\Empresa;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    use \App\Domains\Sii\Concerns\HasSiiAttributesProducto;

    protected $table = 'inventario_productos';

    protected $fillable = [
        'empresa_id',
        'sku',
        'nombre',
        'descripcion',
        'tipo_producto',
        'unidad_medida_id',
        'metodo_valorizacion',
        'costo_promedio',
        'precio_venta_neto',
        'afecto_iva',
        'codigo_barra',
        'stock_minimo',
        'bodega_defecto_id',
        'permite_merma',
        'maneja_lotes',
        'requiere_fecha_vencimiento',
        'activo',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'unidad_medida_id' => 'integer',
        'bodega_defecto_id' => 'integer',

        'costo_promedio' => 'decimal:4',
        'precio_venta_neto' => 'decimal:4',
        'stock_minimo' => 'decimal:4',

        'afecto_iva' => 'boolean',
        'permite_merma' => 'boolean',
        'maneja_lotes' => 'boolean',
        'requiere_fecha_vencimiento' => 'boolean',
        'activo' => 'boolean',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function unidadMedida()
    {
        return $this->belongsTo(UnidadMedida::class, 'unidad_medida_id');
    }

    public function bodegaDefecto()
    {
        return $this->belongsTo(Bodega::class, 'bodega_defecto_id');
    }

    public function stocks()
    {
        return $this->hasMany(StockProducto::class, 'producto_id');
    }

    public function lotes()
    {
        return $this->hasMany(LoteInventario::class, 'producto_id');
    }

    public function stockLotes()
    {
        return $this->hasMany(StockLoteInventario::class, 'producto_id');
    }

    public function movimientosLotes()
    {
        return $this->hasMany(MovimientoLoteInventario::class, 'producto_id');
    }

    public function ajustesCriticos()
    {
        return $this->hasMany(AjusteCriticoInventario::class, 'producto_id');
    }

    public function reservaDetalles()
    {
        return $this->hasMany(ReservaDetalleInventario::class, 'producto_id');
    }

    public function reservaConsumos()
    {
        return $this->hasMany(ReservaConsumoInventario::class, 'producto_id');
    }
    public function tomaFisicaDetalles()
    {
    
    return $this->hasMany(TomaFisicaDetalleInventario::class, 'producto_id');
    }
    public function estaActivo(): bool
    {
        return $this->activo === true;
    }

    public function manejaLotes(): bool
    {
        return $this->maneja_lotes === true;
    }

    public function requiereFechaVencimiento(): bool
    {
        return $this->requiere_fecha_vencimiento === true;
    }

    public function perteneceAEmpresa(int $empresaId): bool
    {
        return (int) $this->empresa_id === $empresaId;
    }
}