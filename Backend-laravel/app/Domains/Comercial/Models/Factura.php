<?php

namespace App\Domains\Comercial\Models;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Traits\HasEmpresaScope;
use App\Domains\Sii\Concerns\HasSiiAttributesFactura;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Tesoreria\Models\CuentaBancariaProveedor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int|null $sii_dte_emitido_id
 * @property int|null $tipo_dte
 * @property int|null $cliente_id
 * @property Carbon $fecha_emision
 * @property-read SiiDteEmitido|null $dteEmitido
 * @property-read Cliente|null $cliente
 * @property-read Proveedor|null $proveedor
 * @property-read Collection<int, FacturaDetalle> $detalles
 */
class Factura extends Model
{
    use HasEmpresaScope;
    use HasSiiAttributesFactura;
    use SoftDeletes;

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
        'asiento_pago_id',
        'cotizacion_id',
        'moneda',
        'tipo_cambio',
        'monto_bruto_origen',
        'es_documento_exterior',
        'tipo_gasto_art59',
        'retencion_art59',
        'factura_referencia_id',
        'razon_nota_credito',
        'razon_nota_debito',
        'notificada_at',
        'usuario_notificacion_id',
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'fecha_vencimiento' => 'date',
        'notificada_at' => 'datetime',
        'monto_bruto' => 'decimal:2',
        'monto_neto' => 'decimal:2',
        'monto_iva' => 'decimal:2',
        'tipo_cambio' => 'decimal:4',
        'monto_bruto_origen' => 'decimal:2',
        'es_documento_exterior' => 'boolean',
        'retencion_art59' => 'decimal:2',
    ];

    protected $appends = ['nombre_proveedor'];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    /** @return BelongsTo<Cliente, $this> */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /** @return BelongsTo<Cotizacion, $this> */
    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function getNombreProveedorAttribute(): string
    {
        if (! $this->relationLoaded('proveedor') && $this->proveedor_id) {
            $this->load('proveedor');
        }

        return trim((string) ($this->proveedor->razon_social ?? ''));
    }

    public function cuentaBancaria(): BelongsTo
    {
        return $this->belongsTo(CuentaBancariaProveedor::class, 'cuenta_bancaria_id');
    }

    public function facturaOrigen(): BelongsTo
    {
        return $this->belongsTo(self::class, 'factura_referencia_id');
    }

    public function notasCredito(): HasMany
    {
        return $this->hasMany(self::class, 'factura_referencia_id');
    }

    public function documentosAdjuntos(): HasMany
    {
        return $this->hasMany(DocumentoAdjunto::class);
    }

    public static function generarCodigoUnico(): int
    {
        for ($intento = 0; $intento < 5; $intento++) {
            $codigo = (int) (time().str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT));
            $existeFactura = self::where('codigo_unico', $codigo)->exists();
            if (! $existeFactura) {
                return $codigo;
            }

            usleep(1000);
        }

        throw new \RuntimeException(
            'No se pudo generar un codigo_unico despues de 5 intentos. '.
            'Esto indica un problema grave en la generacion de codigos.'
        );
    }
}
