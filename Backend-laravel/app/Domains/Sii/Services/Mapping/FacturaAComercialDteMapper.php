<?php

namespace App\Domains\Sii\Services\Mapping;

use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Exceptions\DteIncompletoException;
use App\Domains\Sii\Exceptions\FacturaIncompletaParaSii;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoDetalle;
use App\Domains\Sii\Models\SiiDteEmitidoReferencia;
use App\Domains\Sii\Models\SiiDteEmitidoTraslado;
use App\Domains\Sii\Services\Validators\CuadraturaMontosValidator;
use App\Domains\Sii\Support\Iso88591Helper;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

/** Mapper Factura (Comercial) → SiiDteEmitido (Sii) en BORRADOR; snapshot inmutable, lockForUpdate sobre la factura previene doble emision concurrente. */
class FacturaAComercialDteMapper
{
    /** Tipos DTE nacionales soportados. */
    private const TIPOS_DTE_VALIDOS = [33, 34, 39, 41, 46, 52, 56, 61];

    /** Tipos que requieren al menos 1 referencia (NC/ND). */
    private const TIPOS_DTE_REQUIEREN_REFERENCIAS = [56, 61];

    /** Tipos exentos: monto_neto=iva=0; el total va en monto_exento. */
    private const TIPOS_DTE_EXENTOS = [34, 41];

    /**
     * Factura de Compra (46): autofacturacion -- el emisor es SIEMPRE el comprador (esta
     * empresa); el receptor del DTE es el vendedor/proveedor, no un Cliente. Unico tipo de
     * este set cuyo receptor se resuelve desde Proveedor en vez de Cliente.
     */
    private const TIPOS_DTE_RECEPTOR_PROVEEDOR = [46];

    /** Guia de Despacho (52): requiere el bloque Transporte/IndTraslado (SiiDteEmitidoTraslado). */
    private const TIPOS_DTE_REQUIEREN_TRASLADO = [52];

    /** Coherencia tipo_documento (Comercial) ↔ tipo_dte (SII); el default del modelo Factura es 'FACTURA' (migracion 130005). */
    private const COHERENCIA_TIPO_DOCUMENTO_DTE = [
        'FACTURA' => [33, 34],
        'BOLETA' => [39, 41],
        'FACTURA_COMPRA' => [46],
        'GUIA_DESPACHO' => [52],
        'NOTA_CREDITO' => [61],
        'NOTA_DEBITO' => [56],
    ];

    public function __construct(
        private readonly CuadraturaMontosValidator $cuadraturaValidator
    ) {}

    /** Tasa IVA en PORCENTAJE (19.00), leida de config/fiscal.php que la almacena como fraccion (0.19). */
    private function tasaIvaPorcentaje(): float
    {
        return round((float) config('fiscal.tasa_iva') * 100, 2);
    }

    /**
     * @param  array<int, array{tipo_doc: int, folio_ref: string, fecha_ref: string, cod_ref?: int|null, razon_ref?: string|null, rut_otro?: string|null}>  $referencias
     *
     * @throws FacturaIncompletaParaSii si alguna validacion falla.
     */
    public function mapear(Factura $factura, array $referencias = []): SiiDteEmitido
    {
        return DB::transaction(function () use ($factura, $referencias) {
            /** @var Factura $facturaLock */
            $facturaLock = Factura::query()
                ->lockForUpdate()
                ->findOrFail($factura->id);
            $facturaLock->load(['cliente', 'proveedor', 'empresa', 'detalles']);

            $this->validarFactura($facturaLock);
            $this->validarReferencias($facturaLock, $referencias);
            $this->validarCuadratura($facturaLock);

            $dte = $this->construirDte($facturaLock);
            $this->construirDetalles($dte, $facturaLock);

            if ($referencias !== []) {
                $this->construirReferencias($dte, $referencias);
            }

            if (in_array((int) $facturaLock->tipo_dte, self::TIPOS_DTE_REQUIEREN_TRASLADO, true)) {
                $this->construirTraslado($dte);
            }

            // Vincular la factura al DTE recien creado (cierra el ciclo F6.0).
            $facturaLock->sii_dte_emitido_id = $dte->id;
            $facturaLock->save();

            return $dte->fresh(['detalles', 'referencias', 'traslado']);
        });
    }

    private function validarFactura(Factura $factura): void
    {
        if (! $factura->tipo_dte) {
            throw FacturaIncompletaParaSii::tipoDteFaltante((int) $factura->id);
        }

        $tipoDte = (int) $factura->tipo_dte;
        if (! in_array($tipoDte, self::TIPOS_DTE_VALIDOS, true)) {
            throw FacturaIncompletaParaSii::tipoDteInvalido(
                (int) $factura->id,
                $tipoDte,
                self::TIPOS_DTE_VALIDOS
            );
        }

        if (in_array($tipoDte, self::TIPOS_DTE_RECEPTOR_PROVEEDOR, true)) {
            if (! $factura->proveedor_id) {
                throw FacturaIncompletaParaSii::proveedorFaltante((int) $factura->id);
            }
        } elseif (! $factura->cliente_id) {
            throw FacturaIncompletaParaSii::clienteFaltante((int) $factura->id);
        }

        if ($factura->estado === 'ANULADA') {
            throw FacturaIncompletaParaSii::estadoInvalido((int) $factura->id, (string) $factura->estado);
        }

        if ($factura->sii_dte_emitido_id !== null) {
            throw FacturaIncompletaParaSii::yaEmitida(
                (int) $factura->id,
                (int) $factura->sii_dte_emitido_id
            );
        }

        if ($factura->detalles->isEmpty()) {
            throw FacturaIncompletaParaSii::sinDetalles((int) $factura->id);
        }

        $tipoDoc = $factura->tipo_documento ?? 'FACTURA';
        $tiposPermitidos = self::COHERENCIA_TIPO_DOCUMENTO_DTE[$tipoDoc] ?? [];
        if (! in_array($tipoDte, $tiposPermitidos, true)) {
            throw FacturaIncompletaParaSii::tipoDocumentoInconsistente(
                (int) $factura->id,
                (string) $tipoDoc,
                $tipoDte
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $referencias
     */
    private function validarReferencias(Factura $factura, array $referencias): void
    {
        $tipoDte = (int) $factura->tipo_dte;
        if (in_array($tipoDte, self::TIPOS_DTE_REQUIEREN_REFERENCIAS, true) && $referencias === []) {
            throw FacturaIncompletaParaSii::referenciasFaltantes((int) $factura->id, $tipoDte);
        }
    }

    /** Construye un SiiDteEmitido + detalles in-memory (no persistido) y delega a CuadraturaMontosValidator; si lanza, traducimos a FacturaIncompletaParaSii. */
    private function validarCuadratura(Factura $factura): void
    {
        $dteVirtual = new SiiDteEmitido([
            'tipo_dte' => (int) $factura->tipo_dte,
            'monto_neto' => (float) $factura->monto_neto,
            'monto_exento' => (float) ($factura->monto_exento ?? 0),
            'tasa_iva' => $this->tasaIvaPorcentaje(),
            'iva' => (float) $factura->monto_iva,
            'monto_total' => (float) $factura->monto_bruto,
        ]);

        $detallesVirtuales = $factura->detalles->map(function ($det) {
            return new SiiDteEmitidoDetalle([
                'monto_item' => (float) $det->monto_item,
                'exento' => (bool) $det->exento,
            ]);
        });
        $dteVirtual->setRelation('detalles', new EloquentCollection($detallesVirtuales->all()));

        try {
            $this->cuadraturaValidator->validar($dteVirtual);
        } catch (DteIncompletoException $e) {
            throw FacturaIncompletaParaSii::montosNoCuadran((int) $factura->id, $e->getMessage());
        }
    }

    private function construirDte(Factura $factura): SiiDteEmitido
    {
        /** @var Empresa $empresa */
        $empresa = $factura->empresa;
        $receptor = $this->resolverReceptorSnapshot($factura);
        $tipoDte = (int) $factura->tipo_dte;
        $esExento = in_array($tipoDte, self::TIPOS_DTE_EXENTOS, true);
        $esBoleta = in_array($tipoDte, [SiiDteEmitido::TIPO_BOLETA, SiiDteEmitido::TIPO_BOLETA_EXENTA], true);

        return SiiDteEmitido::create([
            'empresa_id' => $factura->empresa_id,
            'factura_id' => $factura->id,
            'estado' => SiiDteEmitido::ESTADO_BORRADOR,
            'tipo_dte' => $tipoDte,
            // IndServicio es requerido por BOLETADefType (ver DteXmlBuilder::buildIdDoc); el
            // Comercial no modela este campo aun, asi que se usa 3 (Otros - venta y otros
            // servicios), el valor correcto para el unico flujo que este ERP emite (VENTA).
            'indicador_servicio' => $esBoleta ? 3 : null,
            // folio se asigna en F4.4 (EmitirDteService->reservarSiguienteFolio).
            'folio' => 0,
            'fecha_emision' => $factura->fecha_emision,
            'fecha_vencimiento' => $factura->fecha_vencimiento,
            'forma_pago_codigo' => $factura->forma_pago_codigo,
            'condicion_pago' => $factura->condicion_pago
                ? Iso88591Helper::sanitize((string) $factura->condicion_pago, 100)
                : null,
            'moneda' => $factura->moneda ?? 'CLP',

            // EMISOR — snapshot completo desde Empresa.
            'emisor_rut' => Iso88591Helper::sanitize((string) $empresa->rut, 20),
            'emisor_razon_social' => Iso88591Helper::sanitize((string) $empresa->razon_social, 100),
            'emisor_giro' => $empresa->giro_emisor
                ? Iso88591Helper::sanitize((string) $empresa->giro_emisor, 80)
                : null,
            'emisor_acteco' => $empresa->codigo_actividad_sii,
            'emisor_direccion' => $empresa->direccion
                ? Iso88591Helper::sanitize((string) $empresa->direccion, 70)
                : null,
            'emisor_comuna' => $empresa->comuna
                ? Iso88591Helper::sanitize((string) $empresa->comuna, 20)
                : null,
            'emisor_ciudad' => $empresa->ciudad
                ? Iso88591Helper::sanitize((string) $empresa->ciudad, 20)
                : null,

            // RECEPTOR — snapshot desde Cliente, o desde Proveedor si es Factura de Compra (46).
            'receptor_rut' => Iso88591Helper::sanitize($receptor['rut'], 20),
            'receptor_razon_social' => Iso88591Helper::sanitize($receptor['razon_social'], 100),
            'receptor_giro' => $receptor['giro'] !== null
                ? Iso88591Helper::sanitize($receptor['giro'], 40)
                : null,
            'receptor_direccion' => $receptor['direccion'] !== null
                ? Iso88591Helper::sanitize($receptor['direccion'], 70)
                : null,
            'receptor_comuna' => $receptor['comuna'] !== null
                ? Iso88591Helper::sanitize($receptor['comuna'], 20)
                : null,
            'receptor_ciudad' => $receptor['ciudad'] !== null
                ? Iso88591Helper::sanitize($receptor['ciudad'], 20)
                : null,
            'receptor_contacto' => $receptor['contacto'] !== null
                ? Iso88591Helper::sanitize($receptor['contacto'], 80)
                : null,
            'receptor_correo' => $receptor['correo'] !== null
                ? Iso88591Helper::sanitize($receptor['correo'], 80)
                : null,

            // TOTALES — para tipos exentos, neto/iva quedan en 0 y monto_exento=total.
            'monto_neto' => $esExento ? 0 : (float) $factura->monto_neto,
            'monto_exento' => $esExento
                ? (float) $factura->monto_bruto
                : (float) ($factura->monto_exento ?? 0),
            // tasa_iva en DTE exentos (tipos 34/41): confirmado contra el formato DTE del SII
            // (docs/sii-normativa/formato_dte_202602.pdf, pag.10 leyenda de obligatoriedad +
            // pag.30 campo 111 <TasaIVA>): el codigo de obligatoriedad para Factura Exenta es
            // "2 = condicional" (obligatorio solo si el documento tiene una porcion afecta).
            // Este ERP no modela documentos mixtos exento+afecto: $esExento es binario por
            // factura completa y monto_neto ya se fuerza a 0 en ese caso (ver arriba). La
            // condicion que activaria la obligatoriedad de TasaIVA no puede ocurrir en este
            // modelo de datos, por lo que 0 es correcto.
            'tasa_iva' => $esExento ? 0 : $this->tasaIvaPorcentaje(),
            'iva' => $esExento ? 0 : (float) $factura->monto_iva,
            'monto_total' => (float) $factura->monto_bruto,

            // Descuento global (encabezado, no satelite); solo monto: el porcentaje del Comercial es informativo y el SII espera el monto absoluto en DR.
            'descuento_global_monto' => (float) ($factura->descuento_global_monto ?? 0),

            'es_cedible' => true,
        ]);
    }

    private function construirDetalles(SiiDteEmitido $dte, Factura $factura): void
    {
        $linea = 1;
        $detallesOrdenados = $factura->detalles->sortBy('numero_linea')->values();

        foreach ($detallesOrdenados as $det) {
            SiiDteEmitidoDetalle::create([
                'dte_emitido_id' => $dte->id,
                'numero_linea' => (int) ($det->numero_linea ?? $linea),
                'factura_detalle_id' => $det->id,
                'codigo_item' => $det->codigo_item,
                'tipo_codigo' => $det->tipo_codigo,
                'nombre_item' => Iso88591Helper::sanitize((string) $det->nombre_item, 80),
                'descripcion' => $det->descripcion
                    ? Iso88591Helper::sanitize((string) $det->descripcion, 1000)
                    : null,
                'cantidad' => (float) $det->cantidad,
                'unidad_medida' => $det->unidad_medida
                    ? Iso88591Helper::sanitize((string) $det->unidad_medida, 4)
                    : null,
                'precio_unitario' => (float) $det->precio_unitario,
                'descuento_pct' => (float) ($det->descuento_pct ?? 0),
                'descuento_monto' => (float) ($det->descuento_monto ?? 0),
                'recargo_pct' => (float) ($det->recargo_pct ?? 0),
                'recargo_monto' => (float) ($det->recargo_monto ?? 0),
                'exento' => (bool) ($det->exento ?? false),
                'monto_item' => (float) $det->monto_item,
            ]);
            $linea++;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $referencias
     */
    private function construirReferencias(SiiDteEmitido $dte, array $referencias): void
    {
        foreach (array_values($referencias) as $i => $ref) {
            SiiDteEmitidoReferencia::create([
                'dte_emitido_id' => $dte->id,
                'numero_linea' => $i + 1,
                'tipo_documento_referencia' => (string) $ref['tipo_doc'],
                'folio_referencia' => (string) $ref['folio_ref'],
                'fecha_referencia' => (string) $ref['fecha_ref'],
                'codigo_referencia' => $ref['cod_ref'] ?? null,
                'razon_referencia' => isset($ref['razon_ref'])
                    ? Iso88591Helper::sanitize((string) $ref['razon_ref'], 90)
                    : null,
                'rut_otro_contribuyente' => $ref['rut_otro'] ?? null,
            ]);
        }
    }

    /**
     * Guia de Despacho (52): crea el bloque Transporte/IndTraslado. Este ERP solo modela guias
     * emitidas junto a una venta real (no traslados internos/consignacion/otros), por eso el
     * indicador se fija en 1 (Operacion constituye venta) -- mismo patron que
     * indicador_servicio=3 para boleta: valor correcto para el unico flujo que el ERP emite,
     * datos de transporte (chofer/patente/destino) quedan sin capturar por ahora.
     */
    private function construirTraslado(SiiDteEmitido $dte): void
    {
        SiiDteEmitidoTraslado::create([
            'dte_emitido_id' => $dte->id,
            'indicador_traslado' => 1,
        ]);
    }

    /** Resolucion del correo del receptor: prioriza contacto_email, fallback a email general, null si el cliente no tiene ninguno. */
    private function resolverCorreoReceptor(Cliente $cliente): ?string
    {
        $correo = $cliente->contacto_email ?? $cliente->email ?? null;
        if ($correo === null || $correo === '') {
            return null;
        }

        return Iso88591Helper::sanitize((string) $correo, 80);
    }

    /**
     * Snapshot del receptor del DTE: viene de Cliente en todos los tipos, salvo Factura de
     * Compra (46) -- autofacturacion, donde el receptor del DTE es el Proveedor (el vendedor
     * real de la operacion). Proveedor no modela giro/ciudad/contacto_nombre, por eso esos
     * campos quedan null para 46 (son opcionales en el XSD).
     *
     * @return array{rut: string, razon_social: string, giro: ?string, direccion: ?string, comuna: ?string, ciudad: ?string, contacto: ?string, correo: ?string}
     */
    private function resolverReceptorSnapshot(Factura $factura): array
    {
        if (in_array((int) $factura->tipo_dte, self::TIPOS_DTE_RECEPTOR_PROVEEDOR, true)) {
            /** @var Proveedor $proveedor */
            $proveedor = $factura->proveedor;

            return [
                'rut' => (string) $proveedor->rut,
                'razon_social' => (string) $proveedor->razon_social,
                'giro' => null,
                'direccion' => $proveedor->direccion ? (string) $proveedor->direccion : null,
                'comuna' => $proveedor->comuna ? (string) $proveedor->comuna : null,
                'ciudad' => null,
                'contacto' => $proveedor->nombre_contacto ? (string) $proveedor->nombre_contacto : null,
                'correo' => $proveedor->email_contacto ? (string) $proveedor->email_contacto : null,
            ];
        }

        /** @var Cliente $cliente */
        $cliente = $factura->cliente;

        return [
            'rut' => (string) $cliente->rut,
            'razon_social' => (string) $cliente->razon_social,
            'giro' => $cliente->giro ? (string) $cliente->giro : null,
            'direccion' => $cliente->direccion ? (string) $cliente->direccion : null,
            'comuna' => $cliente->comuna ? (string) $cliente->comuna : null,
            'ciudad' => $cliente->ciudad ? (string) $cliente->ciudad : null,
            'contacto' => $cliente->contacto_nombre ? (string) $cliente->contacto_nombre : null,
            'correo' => $this->resolverCorreoReceptor($cliente),
        ];
    }
}
