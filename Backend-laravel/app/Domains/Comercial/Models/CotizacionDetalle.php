<?php

namespace App\Domains\Comercial\Models;

use App\Domains\Inventario\Models\MovimientoInventario;
use App\Domains\Inventario\Models\Producto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CotizacionDetalle extends Model
{
    use SoftDeletes;

    protected $table = 'cotizacion_detalles';

    public $timestamps = false;

    protected $fillable = [
        'cotizacion_id',
        'producto_id',
        'movimiento_inventario_id',
        'producto_nombre',
        'descripcion',
        'cantidad',
        'precio',
        'precio_unitario',
        'subtotal',
    ];

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class);
    }

    /** Producto de Inventario vinculado (nullable): solo presente si la línea representa un bien con stock, no un servicio. */
    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    /** Movimiento de salida de Inventario generado al facturar esta línea (ver CotizacionService::convertirEnFactura). */
    public function movimientoInventario()
    {
        return $this->belongsTo(MovimientoInventario::class, 'movimiento_inventario_id');
    }
}
