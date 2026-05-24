<?php

namespace App\Domains\Sii\Models;

use App\Domains\Comercial\Models\Cotizacion;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use Database\Factories\Sii\SiiDteEmitidoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SiiDteEmitido extends Model
{
    use HasFactory;

    protected $table = 'sii_dte_emitido';

    // -------- ESTADOS --------
    public const ESTADO_BORRADOR              = 'BORRADOR';
    public const ESTADO_FOLIO_RESERVADO       = 'FOLIO_RESERVADO';
    public const ESTADO_XML_GENERADO          = 'XML_GENERADO';
    public const ESTADO_FIRMADO               = 'FIRMADO';
    public const ESTADO_ENVIADO_SII           = 'ENVIADO_SII';
    public const ESTADO_EN_PROCESO_SII        = 'EN_PROCESO_SII';
    public const ESTADO_ACEPTADO              = 'ACEPTADO';
    public const ESTADO_ACEPTADO_CON_REPAROS  = 'ACEPTADO_CON_REPAROS';
    public const ESTADO_RECHAZADO             = 'RECHAZADO';
    public const ESTADO_REEMITIDO             = 'REEMITIDO';
    public const ESTADO_ANULADO_CON_NC        = 'ANULADO_CON_NC';
    public const ESTADO_ANULADO_FALLO_INTERNO = 'ANULADO_FALLO_INTERNO';

    // -------- TIPOS DTE (XSD oficial SII) --------
    public const TIPO_FACTURA                  = 33;
    public const TIPO_FACTURA_EXENTA           = 34;
    public const TIPO_BOLETA                   = 39;
    public const TIPO_BOLETA_EXENTA            = 41;
    public const TIPO_LIQUIDACION_FACTURA      = 43;
    public const TIPO_FACTURA_COMPRA           = 46;
    public const TIPO_GUIA_DESPACHO            = 52;
    public const TIPO_NOTA_DEBITO              = 56;
    public const TIPO_NOTA_CREDITO             = 61;
    public const TIPO_FACTURA_EXPORTACION      = 110;
    public const TIPO_NOTA_DEBITO_EXPORTACION  = 111;
    public const TIPO_NOTA_CREDITO_EXPORTACION = 112;

    protected $fillable = [
        // identificacion
        'empresa_id', 'factura_id', 'cotizacion_id', 'origen_externo',
        'tipo_dte', 'folio', 'fecha_emision', 'caf_id',
        // snapshot emisor
        'emisor_rut', 'emisor_razon_social', 'emisor_giro', 'emisor_acteco',
        'emisor_direccion', 'emisor_comuna', 'emisor_ciudad', 'emisor_cdg_sii_sucursal',
        // snapshot receptor
        'receptor_rut', 'receptor_razon_social', 'receptor_giro',
        'receptor_direccion', 'receptor_comuna', 'receptor_ciudad',
        'receptor_contacto', 'receptor_correo',
        // totales
        'moneda', 'monto_neto', 'monto_exento', 'tasa_iva', 'iva',
        'iva_no_retenido', 'monto_impuesto_adicional', 'descuento_global_monto',
        'monto_total',
        // forma pago / vencimiento
        'forma_pago_codigo', 'fecha_vencimiento', 'condicion_pago',
        // estado y tracking
        'estado', 'track_id', 'codigo_respuesta_sii', 'glosa_sii',
        'fecha_envio_sii', 'fecha_aceptacion_sii', 'fecha_rechazo_sii',
        // archivos
        'xml_path', 'xml_hash_sha256', 'pdf_path', 'ted_xml',
        // indicadores
        'es_cedible', 'indicador_servicio',
        // auditoria
        'usuario_emisor_id',
    ];

    protected $casts = [
        'fecha_emision'            => 'date',
        'fecha_vencimiento'        => 'date',
        'fecha_envio_sii'          => 'datetime',
        'fecha_aceptacion_sii'     => 'datetime',
        'fecha_rechazo_sii'        => 'datetime',
        'monto_neto'               => 'decimal:2',
        'monto_exento'             => 'decimal:2',
        'iva'                      => 'decimal:2',
        'iva_no_retenido'          => 'decimal:2',
        'monto_impuesto_adicional' => 'decimal:2',
        'descuento_global_monto'   => 'decimal:2',
        'monto_total'              => 'decimal:2',
        'tasa_iva'                 => 'decimal:2',
        'tipo_dte'                 => 'integer',
        'folio'                    => 'integer',
        'forma_pago_codigo'        => 'integer',
        'indicador_servicio'       => 'integer',
        'emisor_acteco'            => 'integer',
        'es_cedible'               => 'boolean',
    ];

    protected static function newFactory(): SiiDteEmitidoFactory
    {
        return SiiDteEmitidoFactory::new();
    }

    // -------- RELACIONES --------

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function usuarioEmisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_emisor_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(SiiDteEmitidoDetalle::class, 'dte_emitido_id');
    }

    public function referencias(): HasMany
    {
        return $this->hasMany(SiiDteEmitidoReferencia::class, 'dte_emitido_id');
    }

    public function traslado(): HasOne
    {
        return $this->hasOne(SiiDteEmitidoTraslado::class, 'dte_emitido_id');
    }

    public function impuestosAdicionales(): HasMany
    {
        return $this->hasMany(SiiDteEmitidoImpuestoAdicional::class, 'dte_emitido_id');
    }

    // -------- SCOPES --------

    public function scopeAceptados(Builder $query): Builder
    {
        return $query->where('estado', self::ESTADO_ACEPTADO);
    }

    public function scopeRechazados(Builder $query): Builder
    {
        return $query->where('estado', self::ESTADO_RECHAZADO);
    }

    public function scopePendientes(Builder $query): Builder
    {
        return $query->whereIn('estado', [
            self::ESTADO_FIRMADO,
            self::ESTADO_ENVIADO_SII,
            self::ESTADO_EN_PROCESO_SII,
        ]);
    }

    public function scopePorEmpresa(Builder $query, int $empresaId): Builder
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function scopePorTipo(Builder $query, int $tipoDte): Builder
    {
        return $query->where('tipo_dte', $tipoDte);
    }

    public function scopePorFolio(Builder $query, int $folio): Builder
    {
        return $query->where('folio', $folio);
    }
}
