<?php

namespace App\Domains\Sii\Services\Mapping;

use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Exceptions\DteIncompletoException;
use App\Domains\Sii\Exceptions\FacturaIncompletaParaSii;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoDetalle;
use App\Domains\Sii\Models\SiiDteEmitidoReferencia;
use App\Domains\Sii\Services\Validators\CuadraturaMontosValidator;
use App\Domains\Sii\Support\Iso88591Helper;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

/**
 * F6.1 — Mapper Factura (Comercial) → SiiDteEmitido (Sii) en BORRADOR.
 *
 * Punto de entrada del modulo SII desde el dominio Comercial. Toma una Factura
 * con datos suficientes (cliente_id, tipo_dte, detalles cuadrados) y produce
 * el snapshot SiiDteEmitido + sus satelites en una sola DB::transaction.
 *
 *   - NO reserva folio CAF (eso es F4.4 EmitirDteService).
 *   - NO firma ni envia al SII (F4.4 + F5.x).
 *   - NO dispara eventos ni encola jobs (F6.2).
 *
 * Validaciones EXHAUSTIVAS pre-construccion (D5): si alguna falla se lanza
 * FacturaIncompletaParaSii ANTES de tocar BD, garantizando cero folios huerfanos.
 *
 * Atomicidad: lockForUpdate sobre la factura previene doble emision concurrente.
 * Si el persistir lanza, rollback completo de SiiDteEmitido + satelites + FK.
 *
 * Snapshot inmutable: emisor_* / receptor_* / detalles son COPIADOS (strings y
 * decimals). Cambios posteriores en Empresa o Cliente NO afectan el DTE. R1
 * de HARDENING-1 (booted updating hook) refuerza esto a nivel de modelo.
 */
class FacturaAComercialDteMapper
{
    /** Tipos DTE nacionales soportados en F6.1. */
    private const TIPOS_DTE_VALIDOS = [33, 34, 39, 41, 56, 61];

    /** Tipos que requieren al menos 1 referencia (NC/ND). */
    private const TIPOS_DTE_REQUIEREN_REFERENCIAS = [56, 61];

    /** Tipos exentos: monto_neto=iva=0; el total va en monto_exento. */
    private const TIPOS_DTE_EXENTOS = [34, 41];

    /** Tasa IVA Chile (constante por ley). */
    private const TASA_IVA = 19.00;

    /**
     * Coherencia tipo_documento (Comercial) ↔ tipo_dte (SII).
     * El default del modelo Factura es 'FACTURA' (migracion 130005).
     */
    private const COHERENCIA_TIPO_DOCUMENTO_DTE = [
        'FACTURA'      => [33, 34],
        'BOLETA'       => [39, 41],
        'NOTA_CREDITO' => [61],
        'NOTA_DEBITO'  => [56],
    ];

    public function __construct(
        private readonly CuadraturaMontosValidator $cuadraturaValidator
    ) {
    }

    /**
     * @param array<int, array{tipo_doc: int, folio_ref: string, fecha_ref: string, cod_ref?: int|null, razon_ref?: string|null, rut_otro?: string|null}> $referencias
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
            $facturaLock->load(['cliente', 'empresa', 'detalles']);

            $this->validarFactura($facturaLock);
            $this->validarReferencias($facturaLock, $referencias);
            $this->validarCuadratura($facturaLock);

            $dte = $this->construirDte($facturaLock);
            $this->construirDetalles($dte, $facturaLock);

            if ($referencias !== []) {
                $this->construirReferencias($dte, $referencias);
            }

            // Vincular la factura al DTE recien creado (cierra el ciclo F6.0).
            $facturaLock->sii_dte_emitido_id = $dte->id;
            $facturaLock->save();

            return $dte->fresh(['detalles', 'referencias']);
        });
    }

    private function validarFactura(Factura $factura): void
    {
        if ($factura->tipo_dte === null) {
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

        if ($factura->cliente_id === null) {
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
     * @param array<int, array<string, mixed>> $referencias
     */
    private function validarReferencias(Factura $factura, array $referencias): void
    {
        $tipoDte = (int) $factura->tipo_dte;
        if (in_array($tipoDte, self::TIPOS_DTE_REQUIEREN_REFERENCIAS, true) && $referencias === []) {
            throw FacturaIncompletaParaSii::referenciasFaltantes((int) $factura->id, $tipoDte);
        }
    }

    /**
     * Construye un SiiDteEmitido + detalles in-memory (NO persistido) y delega
     * a CuadraturaMontosValidator. Si lanza, traducimos a FacturaIncompletaParaSii.
     */
    private function validarCuadratura(Factura $factura): void
    {
        $dteVirtual = new SiiDteEmitido([
            'tipo_dte'     => (int) $factura->tipo_dte,
            'monto_neto'   => (float) $factura->monto_neto,
            'monto_exento' => (float) ($factura->monto_exento ?? 0),
            'tasa_iva'     => self::TASA_IVA,
            'iva'          => (float) $factura->monto_iva,
            'monto_total'  => (float) $factura->monto_bruto,
        ]);

        $detallesVirtuales = $factura->detalles->map(function ($det) {
            return new SiiDteEmitidoDetalle([
                'monto_item' => (float) $det->monto_item,
                'exento'     => (bool) $det->exento,
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
        /** @var Cliente $cliente */
        $cliente = $factura->cliente;
        $tipoDte = (int) $factura->tipo_dte;
        $esExento = in_array($tipoDte, self::TIPOS_DTE_EXENTOS, true);

        return SiiDteEmitido::create([
            'empresa_id'           => $factura->empresa_id,
            'factura_id'           => $factura->id,
            'estado'               => SiiDteEmitido::ESTADO_BORRADOR,
            'tipo_dte'             => $tipoDte,
            // folio se asigna en F4.4 (EmitirDteService->reservarSiguienteFolio).
            'folio'                => 0,
            'fecha_emision'        => $factura->fecha_emision,
            'fecha_vencimiento'    => $factura->fecha_vencimiento,
            'forma_pago_codigo'    => $factura->forma_pago_codigo,
            'condicion_pago'       => $factura->condicion_pago
                ? Iso88591Helper::sanitize((string) $factura->condicion_pago, 100)
                : null,
            'moneda'               => $factura->moneda ?? 'CLP',

            // EMISOR — snapshot completo desde Empresa.
            'emisor_rut'              => Iso88591Helper::sanitize((string) $empresa->rut, 12),
            'emisor_razon_social'     => Iso88591Helper::sanitize((string) $empresa->razon_social, 100),
            'emisor_giro'             => $empresa->giro_emisor
                ? Iso88591Helper::sanitize((string) $empresa->giro_emisor, 80)
                : null,
            'emisor_acteco'           => $empresa->codigo_actividad_sii,
            'emisor_direccion'        => $empresa->direccion
                ? Iso88591Helper::sanitize((string) $empresa->direccion, 70)
                : null,
            'emisor_comuna'           => $empresa->comuna
                ? Iso88591Helper::sanitize((string) $empresa->comuna, 20)
                : null,
            'emisor_ciudad'           => $empresa->ciudad
                ? Iso88591Helper::sanitize((string) $empresa->ciudad, 20)
                : null,

            // RECEPTOR — snapshot desde Cliente.
            'receptor_rut'            => Iso88591Helper::sanitize((string) $cliente->rut, 12),
            'receptor_razon_social'   => Iso88591Helper::sanitize((string) $cliente->razon_social, 100),
            'receptor_giro'           => $cliente->giro
                ? Iso88591Helper::sanitize((string) $cliente->giro, 40)
                : null,
            'receptor_direccion'      => $cliente->direccion
                ? Iso88591Helper::sanitize((string) $cliente->direccion, 70)
                : null,
            'receptor_comuna'         => $cliente->comuna
                ? Iso88591Helper::sanitize((string) $cliente->comuna, 20)
                : null,
            'receptor_ciudad'         => $cliente->ciudad
                ? Iso88591Helper::sanitize((string) $cliente->ciudad, 20)
                : null,
            'receptor_contacto'       => $cliente->contacto_nombre
                ? Iso88591Helper::sanitize((string) $cliente->contacto_nombre, 80)
                : null,
            'receptor_correo'         => $this->resolverCorreoReceptor($cliente),

            // TOTALES — para tipos exentos, neto/iva quedan en 0 y monto_exento=total.
            'monto_neto'              => $esExento ? 0 : (float) $factura->monto_neto,
            'monto_exento'            => $esExento
                ? (float) $factura->monto_bruto
                : (float) ($factura->monto_exento ?? 0),
            'tasa_iva'                => self::TASA_IVA,
            'iva'                     => $esExento ? 0 : (float) $factura->monto_iva,
            'monto_total'             => (float) $factura->monto_bruto,

            // Descuento global (encabezado, no satelite). Solo monto: el porcentaje
            // del Comercial es informativo y el SII espera el monto absoluto en DR.
            'descuento_global_monto'  => (float) ($factura->descuento_global_monto ?? 0),

            'es_cedible'              => true,
        ]);
    }

    private function construirDetalles(SiiDteEmitido $dte, Factura $factura): void
    {
        $linea = 1;
        $detallesOrdenados = $factura->detalles->sortBy('numero_linea')->values();

        foreach ($detallesOrdenados as $det) {
            SiiDteEmitidoDetalle::create([
                'dte_emitido_id'      => $dte->id,
                'numero_linea'        => (int) ($det->numero_linea ?? $linea),
                'factura_detalle_id'  => $det->id,
                'codigo_item'         => $det->codigo_item,
                'tipo_codigo'         => $det->tipo_codigo,
                'nombre_item'         => Iso88591Helper::sanitize((string) $det->nombre_item, 80),
                'descripcion'         => $det->descripcion
                    ? Iso88591Helper::sanitize((string) $det->descripcion, 1000)
                    : null,
                'cantidad'            => (float) $det->cantidad,
                'unidad_medida'       => $det->unidad_medida
                    ? Iso88591Helper::sanitize((string) $det->unidad_medida, 4)
                    : null,
                'precio_unitario'     => (float) $det->precio_unitario,
                'descuento_pct'       => (float) ($det->descuento_pct ?? 0),
                'descuento_monto'     => (float) ($det->descuento_monto ?? 0),
                'recargo_pct'         => (float) ($det->recargo_pct ?? 0),
                'recargo_monto'       => (float) ($det->recargo_monto ?? 0),
                'exento'              => (bool) ($det->exento ?? false),
                'monto_item'          => (float) $det->monto_item,
            ]);
            $linea++;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $referencias
     */
    private function construirReferencias(SiiDteEmitido $dte, array $referencias): void
    {
        foreach (array_values($referencias) as $i => $ref) {
            SiiDteEmitidoReferencia::create([
                'dte_emitido_id'             => $dte->id,
                'numero_linea'               => $i + 1,
                'tipo_documento_referencia'  => (string) $ref['tipo_doc'],
                'folio_referencia'           => (string) $ref['folio_ref'],
                'fecha_referencia'           => (string) $ref['fecha_ref'],
                'codigo_referencia'          => $ref['cod_ref'] ?? null,
                'razon_referencia'           => isset($ref['razon_ref']) && $ref['razon_ref'] !== null
                    ? Iso88591Helper::sanitize((string) $ref['razon_ref'], 90)
                    : null,
                'rut_otro_contribuyente'     => $ref['rut_otro'] ?? null,
            ]);
        }
    }

    /**
     * Resolucion del correo del receptor: prioriza contacto_email; fallback a email
     * general; null si el cliente no tiene ninguno.
     */
    private function resolverCorreoReceptor(Cliente $cliente): ?string
    {
        $correo = $cliente->contacto_email ?? $cliente->email ?? null;
        if ($correo === null || $correo === '') {
            return null;
        }
        return Iso88591Helper::sanitize((string) $correo, 80);
    }
}
