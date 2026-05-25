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
        'xml_path', 'xml_hash_sha256', 'xml_completo_cifrado', 'fecha_firma',
        'pdf_path', 'ted_xml',
        // indicadores
        'es_cedible', 'indicador_servicio',
        // auditoria
        'usuario_emisor_id',
    ];

    /**
     * SEGURIDAD: xml_completo_cifrado contiene el EnvioDTE firmado cifrado con
     * APP_KEY. No debe aparecer en respuestas JSON (defensa en profundidad,
     * aunque ya esta cifrado el contenido).
     */
    protected $hidden = [
        'xml_completo_cifrado',
    ];

    protected $casts = [
        'fecha_emision'            => 'date',
        'fecha_vencimiento'        => 'date',
        'fecha_envio_sii'          => 'datetime',
        'fecha_aceptacion_sii'     => 'datetime',
        'fecha_rechazo_sii'        => 'datetime',
        'fecha_firma'              => 'datetime',
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

    /**
     * HARDENING-1 R1 — Inmutabilidad tecnica del snapshot DTE post-firma.
     *
     * Solo estos campos pueden modificarse cuando el DTE esta en un estado
     * "post-firma" (FIRMADO, ENVIADO_SII, ACEPTADO, etc.). Cualquier intento
     * de modificar otros campos lanza LogicException, preservando el snapshot
     * legal del documento (exigencia SII: el DTE emitido es inmutable).
     */
    private const CAMPOS_PERMITIDOS_POST_FIRMADO = [
        'estado',
        'fecha_firma',
        'fecha_envio_sii',
        'fecha_aceptacion_sii',
        'fecha_rechazo_sii',
        'track_id',
        'codigo_respuesta_sii',
        'glosa_sii',
        'xml_path',
        'xml_hash_sha256',
        'xml_completo_cifrado',
        'pdf_path',
        'updated_at',
    ];

    /** Estados en los que el DTE se considera firmado y persistido (R1). */
    private const ESTADOS_INMUTABLES = [
        self::ESTADO_FIRMADO,
        self::ESTADO_ENVIADO_SII,
        self::ESTADO_EN_PROCESO_SII,
        self::ESTADO_ACEPTADO,
        self::ESTADO_ACEPTADO_CON_REPAROS,
        self::ESTADO_RECHAZADO,
        self::ESTADO_REEMITIDO,
        self::ESTADO_ANULADO_CON_NC,
        self::ESTADO_ANULADO_FALLO_INTERNO,
    ];

    protected static function booted(): void
    {
        static::updating(function (SiiDteEmitido $dte) {
            $estadoOriginal = $dte->getOriginal('estado');

            // BORRADOR (y nulls) son libremente editables.
            if (! in_array($estadoOriginal, self::ESTADOS_INMUTABLES, true)) {
                return;
            }

            $camposModificados  = array_keys($dte->getDirty());
            $camposNoPermitidos = array_diff($camposModificados, self::CAMPOS_PERMITIDOS_POST_FIRMADO);

            if ($camposNoPermitidos !== []) {
                throw new \LogicException(sprintf(
                    'DTE %d en estado "%s" es inmutable; campos no permitidos: %s.',
                    $dte->id ?? 0,
                    $estadoOriginal,
                    implode(', ', $camposNoPermitidos)
                ));
            }
        });
    }

    /**
     * Mapea el codigo numerico de tipo DTE a su nombre humano.
     * Centralizado aqui para no duplicar en controllers, vistas y reportes.
     */
    public static function nombreTipo(int $tipo): string
    {
        return match ($tipo) {
            self::TIPO_FACTURA                  => 'Factura Electronica',
            self::TIPO_FACTURA_EXENTA           => 'Factura Exenta',
            self::TIPO_BOLETA                   => 'Boleta Electronica',
            self::TIPO_BOLETA_EXENTA            => 'Boleta Exenta',
            self::TIPO_LIQUIDACION_FACTURA      => 'Liquidacion Factura',
            self::TIPO_FACTURA_COMPRA           => 'Factura de Compra',
            self::TIPO_GUIA_DESPACHO            => 'Guia de Despacho',
            self::TIPO_NOTA_DEBITO              => 'Nota de Debito',
            self::TIPO_NOTA_CREDITO             => 'Nota de Credito',
            self::TIPO_FACTURA_EXPORTACION      => 'Factura de Exportacion',
            self::TIPO_NOTA_DEBITO_EXPORTACION  => 'Nota de Debito Exportacion',
            self::TIPO_NOTA_CREDITO_EXPORTACION => 'Nota de Credito Exportacion',
            default                              => "Tipo {$tipo}",
        };
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

    /**
     * HARDENING-1 R4: audit log de transiciones de estado.
     */
    public function eventos(): HasMany
    {
        return $this->hasMany(SiiDteEmitidoEvento::class, 'dte_emitido_id')
            ->orderBy('created_at');
    }

    /**
     * F6.3: envios al WS DTEUpload. Un DTE puede tener varios envios si
     * hubo reintentos manuales (uno por intento). El mas reciente se
     * obtiene con ->envios->last() o ->envios()->latest()->first().
     */
    public function envios(): HasMany
    {
        return $this->hasMany(SiiEnvioDte::class, 'dte_emitido_id')
            ->orderBy('created_at');
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
