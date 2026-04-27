<?php

namespace App\Domains\Comercial\Models;

use Illuminate\Database\Eloquent\Model;
use App\Domains\Core\Models\Empresa;
use App\Domains\Tesoreria\Models\CuentaBancariaProveedor;

class Factura extends Model
{
    protected $table = 'facturas';
    const UPDATED_AT = null;

    protected $fillable = [
        'empresa_id',
        'codigo_interno',
        'codigo_unico',
        'proveedor_id',
        'cuenta_bancaria_id',
        'numero_factura',
        'fecha_emision',
        'fecha_vencimiento',
        'monto_bruto',
        'monto_neto',
        'monto_iva',
        'motivo_correccion_iva',
        'autorizador_id',
        'estado',
        'archivo_pdf',
        'comprobante_contable',
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'fecha_vencimiento' => 'date',
        'monto_bruto' => 'decimal:2',
        'monto_neto' => 'decimal:2',
        'monto_iva' => 'decimal:2',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function cuentaBancaria()
    {
        return $this->belongsTo(CuentaBancariaProveedor::class, 'cuenta_bancaria_id');
    }
}