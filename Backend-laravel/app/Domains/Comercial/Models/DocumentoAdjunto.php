<?php

namespace App\Domains\Comercial\Models;

use App\Domains\Core\Traits\HasEmpresaScope;
use Illuminate\Database\Eloquent\Model;

class DocumentoAdjunto extends Model
{
    use HasEmpresaScope;

    protected $table = 'documentos_adjuntos';

    protected $fillable = [
        'empresa_id',
        'factura_id',
        'cotizacion_id',
        'orden_compra_id',
        'tipo_documento',
        'nombre_original',
        'ruta',
        'mime_type',
        'tamano_bytes',
        'usuario_id',
    ];

    public function factura()
    {
        return $this->belongsTo(Factura::class);
    }

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function ordenCompra()
    {
        return $this->belongsTo(OrdenCompra::class, 'orden_compra_id');
    }
}
