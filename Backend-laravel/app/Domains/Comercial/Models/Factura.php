<?php

namespace App\Domains\Comercial\Models;

use Illuminate\Database\Eloquent\Model;
use App\Domains\Core\Models\Empresa;
use App\Domains\Tesoreria\Models\CuentaBancariaProveedor;

class Factura extends Model
{
    use \App\Domains\Sii\Concerns\HasSiiAttributesFactura;

    protected $table = 'facturas';
    const UPDATED_AT = null;

    protected $fillable = [
        'empresa_id',
        'proyecto_activo_id',
        'codigo_interno',
        'codigo_unico',
        'proveedor_id',
        'cuenta_bancaria_id',
        'numero_factura',
        'tipo',
        'tipo_documento',
        'fecha_emision',
        'fecha_vencimiento',
        'monto_bruto',
        'monto_neto',
        'monto_iva',
        'motivo_correccion_iva',
        'autorizador_id',
        'fecha_pago',
        'medio_pago',
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

    protected $appends = ['nombre_proveedor'];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function getNombreProveedorAttribute(): string
    {
        if (!$this->relationLoaded('proveedor') && $this->proveedor_id) {
            $this->load('proveedor');
        }

        return trim((string) ($this->proveedor?->razon_social ?? ''));
    }

    public function cuentaBancaria()
    {
        return $this->belongsTo(CuentaBancariaProveedor::class, 'cuenta_bancaria_id');
    }

    public static function generarCodigoUnico(): int
    {
        for ($intento = 0; $intento < 5; $intento++) {
            $codigo = (int) (intval(microtime(true) * 10000) . random_int(1000, 9999));
            $existeFactura = self::where('codigo_unico', $codigo)->exists();
            if (!$existeFactura) {
                return $codigo;
            }

            usleep(1000);
        }

        throw new \RuntimeException(
            'No se pudo generar un codigo_unico despues de 5 intentos. ' .
            'Esto indica un problema grave en la generacion de codigos.'
        );
    }
}