<?php

namespace App\Domains\Inventario\Models;

use App\Domains\Core\Models\Empresa;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
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
        'codigo_dte',
        'codigo_barra',
        'stock_minimo',
        'bodega_defecto_id',
        'permite_merma',
        'activo',
    ];

    protected $casts = [
        'costo_promedio' => 'decimal:4',
        'precio_venta_neto' => 'decimal:4',
        'stock_minimo' => 'decimal:4',
        'afecto_iva' => 'boolean',
        'permite_merma' => 'boolean',
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
}