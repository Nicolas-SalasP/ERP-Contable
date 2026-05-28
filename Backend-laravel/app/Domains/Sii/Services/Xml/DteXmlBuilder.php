<?php

namespace App\Domains\Sii\Services\Xml;

use App\Domains\Sii\Exceptions\DteIncompletoException;
use App\Domains\Sii\Models\SiiCaf;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoDetalle;
use App\Domains\Sii\Models\SiiDteEmitidoReferencia;
use App\Domains\Sii\Models\SiiDteEmitidoTraslado;
use App\Domains\Sii\Services\Xml\Ted\TedBuilder;
use App\Domains\Sii\Support\Iso88591Helper;
use DOMDocument;
use DOMElement;
use LogicException;

/**
 * Construye el XML del DTE (bloque <DTE>) conforme al XSD oficial DTE_v10.xsd.
 *
 * - Encoding ISO-8859-1 (decision arquitectonica + Iso88591Helper).
 * - DOMDocument nativo (no SimpleXML, no Blade) para control total de
 *   namespaces, encoding y order de xs:sequence.
 * - Validacion XSD SIEMPRE al final del build() (decision arquitectonica).
 *
 * SOBRE EL TED:
 * El XSD oficial requiere DD + FRMT con estructura completa; un TED vacio
 * NO pasa validacion. Por eso esta clase produce un TED con datos reales
 * (RE, TD, F, FE, RR, RSR, MNT, IT1, TSTED) + CAF y firmas como
 * PLACEHOLDERS base64-validos que F4.2 reemplazara con el CAF real del
 * folio y las firmas SHA1+RSA reales. El XML resultante valida XSD pero
 * NO esta firmado criptograficamente todavia.
 */
class DteXmlBuilder
{
    private const NS_SII   = 'http://www.sii.cl/SiiDte';
    private const NS_DSIG  = 'http://www.w3.org/2000/09/xmldsig#';

    /** Valor base64-valido para campos de firma que F4.2 reemplazara. */
    private const PLACEHOLDER_BASE64 = 'UExBQ0VIT0xERVJfRjRfMl9GSVJNQQ=='; // "PLACEHOLDER_F4_2_FIRMA"

    /**
     * @param ?TedBuilder $tedBuilder requerido solo cuando se invoca build()
     *        con un CAF (modo F4.2 firmado). Default null preserva la API de
     *        F4.1 (TED placeholder estructural) y mantiene los tests previos.
     */
    public function __construct(
        private readonly DteXsdValidator $validator,
        private readonly ?TedBuilder $tedBuilder = null
    ) {
    }

    /**
     * Construye el XML del DTE. Si $caf se provee, el TED se firma con
     * RSA-SHA1 usando la clave privada del CAF (F4.2). Sin $caf, se genera
     * un TED placeholder estructural valido contra XSD pero sin firma real
     * (F4.1, util para debug).
     *
     * @throws DteIncompletoException  precondiciones por tipo no satisfechas
     * @throws \App\Domains\Sii\Exceptions\DteXmlInvalidException si el XML no valida contra XSD
     * @throws LogicException si $caf provisto pero TedBuilder no inyectado
     */
    public function build(SiiDteEmitido $dte, ?SiiCaf $caf = null): string
    {
        $this->validarPrecondiciones($dte);

        if ($caf !== null && $this->tedBuilder === null) {
            throw new LogicException(
                'DteXmlBuilder fue construido sin TedBuilder; no puede generar TED firmado. '
                . 'Use app(DteXmlBuilder::class) o inyecte TedBuilder manualmente.'
            );
        }

        $dom = new DOMDocument('1.0', 'ISO-8859-1');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput       = false;

        $root = $dom->createElementNS(self::NS_SII, 'DTE');
        $root->setAttribute('version', '1.0');
        // NO declaramos xmlns:ds aqui: la <ds:Signature> (placeholder F4.1 o
        // real F4.3) lo declara en su propio elemento, evitando que la
        // canonicalizacion inclusiva la incluya como atributo heredado y
        // rompa la verificacion round-trip de xmlseclibs.
        $dom->appendChild($root);

        $documento = $dom->createElement('Documento');
        $documento->setAttribute('ID', 'D' . $dte->folio);
        $root->appendChild($documento);

        $documento->appendChild($this->buildEncabezado($dom, $dte));

        foreach ($dte->detalles as $i => $detalle) {
            $documento->appendChild($this->buildDetalle($dom, $detalle, $i + 1));
        }

        if (((float) $dte->descuento_global_monto) > 0.0) {
            $documento->appendChild($this->buildDscRcgGlobal($dom, $dte));
        }

        foreach ($dte->referencias as $referencia) {
            $documento->appendChild($this->buildReferencia($dom, $referencia));
        }

        // Estrategia para preservar bit-exactitud del TED firmado:
        //   - Sin $caf: insertamos el TED estructural completo (F4.1) con DOM.
        //   - Con $caf: insertamos un placeholder con marcador unico, luego
        //     reemplazamos a nivel string para que el TED real (construido por
        //     TedBuilder como bytes ISO-8859-1) llegue inalterado al XML final.
        $placeholderMarker = null;
        if ($caf !== null) {
            $placeholderMarker = '__TED_PLACEHOLDER_' . bin2hex(random_bytes(8)) . '__';
            $tedNode = $dom->createElement('TED', $placeholderMarker);
            $documento->appendChild($tedNode);
        } else {
            $documento->appendChild($this->buildTed($dom, $dte));
        }

        $tmstFirma = $dom->createElement('TmstFirma', now()->format('Y-m-d\TH:i:s'));
        $documento->appendChild($tmstFirma);

        // <ds:Signature> placeholder al final de <DTE> (NO de <Documento>).
        // F4.3 reemplaza el contenido con la firma real sobre <Documento ID="...">.
        $root->appendChild($this->buildDsSignaturePlaceholder($dom, $dte));

        $xml = $dom->saveXML();
        if ($xml === false) {
            throw DteIncompletoException::campoFaltante('DOMDocument::saveXML retorno false');
        }

        // Reemplazo del TED placeholder por el TED real firmado, a nivel de
        // bytes. Esto preserva exactamente lo que TedBuilder firmo.
        if ($caf !== null) {
            $tedReal  = $this->tedBuilder->buildFirmado($dte, $caf);
            $busqueda = '<TED>' . $placeholderMarker . '</TED>';
            $xml      = str_replace($busqueda, $tedReal, $xml);
        }

        $this->validator->validar($xml);

        return $xml;
    }

    // -----------------------------------------------------------------
    // Precondiciones de negocio
    // -----------------------------------------------------------------

    private function validarPrecondiciones(SiiDteEmitido $dte): void
    {
        if ($dte->detalles->isEmpty()) {
            throw DteIncompletoException::campoFaltante('detalles (al menos 1 linea)');
        }

        if ($dte->tipo_dte === SiiDteEmitido::TIPO_GUIA_DESPACHO && $dte->traslado === null) {
            throw DteIncompletoException::tipoIncompatible(
                $dte->tipo_dte,
                'Guia de Despacho (52) requiere bloque Traslado'
            );
        }

        if (
            in_array($dte->tipo_dte, [SiiDteEmitido::TIPO_NOTA_DEBITO, SiiDteEmitido::TIPO_NOTA_CREDITO], true)
            && $dte->referencias->isEmpty()
        ) {
            throw DteIncompletoException::tipoIncompatible(
                $dte->tipo_dte,
                'NC/ND requieren al menos una referencia al documento original'
            );
        }

        if ($dte->emisor_rut === null || $dte->emisor_razon_social === null) {
            throw DteIncompletoException::campoFaltante('emisor_rut o emisor_razon_social');
        }
        if ($dte->receptor_rut === null || $dte->receptor_razon_social === null) {
            throw DteIncompletoException::campoFaltante('receptor_rut o receptor_razon_social');
        }
    }

    // -----------------------------------------------------------------
    // Encabezado
    // -----------------------------------------------------------------

    private function buildEncabezado(DOMDocument $dom, SiiDteEmitido $dte): DOMElement
    {
        $enc = $dom->createElement('Encabezado');
        $enc->appendChild($this->buildIdDoc($dom, $dte));
        $enc->appendChild($this->buildEmisor($dom, $dte));

        if ($dte->tipo_dte === SiiDteEmitido::TIPO_GUIA_DESPACHO && $dte->traslado) {
            $enc->appendChild($this->buildReceptor($dom, $dte));
            $enc->appendChild($this->buildTransporte($dom, $dte->traslado));
        } else {
            $enc->appendChild($this->buildReceptor($dom, $dte));
        }

        $enc->appendChild($this->buildTotales($dom, $dte));

        return $enc;
    }

    private function buildIdDoc(DOMDocument $dom, SiiDteEmitido $dte): DOMElement
    {
        $idDoc = $dom->createElement('IdDoc');

        // Orden estricto de xs:sequence
        $idDoc->appendChild($dom->createElement('TipoDTE', (string) $dte->tipo_dte));
        $idDoc->appendChild($dom->createElement('Folio', (string) $dte->folio));
        $idDoc->appendChild($dom->createElement('FchEmis', $dte->fecha_emision->format('Y-m-d')));

        // Guia: IndTraslado (luego de FchEmis)
        if ($dte->tipo_dte === SiiDteEmitido::TIPO_GUIA_DESPACHO && $dte->traslado) {
            $idDoc->appendChild($dom->createElement('IndTraslado', (string) $dte->traslado->indicador_traslado));
        }

        // Boleta: IndServicio
        if ($dte->indicador_servicio !== null && in_array($dte->tipo_dte, [SiiDteEmitido::TIPO_BOLETA, SiiDteEmitido::TIPO_BOLETA_EXENTA], true)) {
            $idDoc->appendChild($dom->createElement('IndServicio', (string) $dte->indicador_servicio));
        }

        // Forma de pago
        if ($dte->forma_pago_codigo !== null) {
            $idDoc->appendChild($dom->createElement('FmaPago', (string) $dte->forma_pago_codigo));
        }

        // Fecha vencimiento (al final del sequence)
        if ($dte->fecha_vencimiento !== null) {
            $idDoc->appendChild($dom->createElement('FchVenc', $dte->fecha_vencimiento->format('Y-m-d')));
        }

        return $idDoc;
    }

    private function buildEmisor(DOMDocument $dom, SiiDteEmitido $dte): DOMElement
    {
        $em = $dom->createElement('Emisor');

        $em->appendChild($dom->createElement('RUTEmisor', $dte->emisor_rut));
        $em->appendChild($this->createSanitizedElement($dom, 'RznSoc', $dte->emisor_razon_social, 100));
        $em->appendChild($this->createSanitizedElement($dom, 'GiroEmis', $dte->emisor_giro ?? 'No declarado', 80));

        if ($dte->emisor_acteco !== null) {
            $em->appendChild($dom->createElement('Acteco', (string) $dte->emisor_acteco));
        } else {
            // Acteco es REQUIRED en XSD. Si el snapshot no lo tiene, fallar duro.
            throw DteIncompletoException::campoFaltante('emisor_acteco (Acteco) es requerido por XSD');
        }

        if ($dte->emisor_cdg_sii_sucursal !== null && $dte->emisor_cdg_sii_sucursal !== '') {
            $em->appendChild($dom->createElement('CdgSIISucur', $dte->emisor_cdg_sii_sucursal));
        }

        if ($dte->emisor_direccion !== null) {
            $em->appendChild($this->createSanitizedElement($dom, 'DirOrigen', $dte->emisor_direccion, 70));
        }
        if ($dte->emisor_comuna !== null) {
            $em->appendChild($this->createSanitizedElement($dom, 'CmnaOrigen', $dte->emisor_comuna, 20));
        }
        if ($dte->emisor_ciudad !== null) {
            $em->appendChild($this->createSanitizedElement($dom, 'CiudadOrigen', $dte->emisor_ciudad, 20));
        }

        return $em;
    }

    private function buildReceptor(DOMDocument $dom, SiiDteEmitido $dte): DOMElement
    {
        $rec = $dom->createElement('Receptor');

        $rec->appendChild($dom->createElement('RUTRecep', $dte->receptor_rut));
        $rec->appendChild($this->createSanitizedElement($dom, 'RznSocRecep', $dte->receptor_razon_social, 100));

        // Boletas (39/41): giro y direccion del receptor son opcionales segun normativa.
        if ($dte->receptor_giro !== null && $dte->receptor_giro !== '') {
            $rec->appendChild($this->createSanitizedElement($dom, 'GiroRecep', $dte->receptor_giro, 40));
        }
        if ($dte->receptor_contacto !== null && $dte->receptor_contacto !== '') {
            $rec->appendChild($this->createSanitizedElement($dom, 'Contacto', $dte->receptor_contacto, 80));
        }
        if ($dte->receptor_correo !== null && $dte->receptor_correo !== '') {
            $rec->appendChild($this->createSanitizedElement($dom, 'CorreoRecep', $dte->receptor_correo, 80));
        }
        if ($dte->receptor_direccion !== null && $dte->receptor_direccion !== '') {
            $rec->appendChild($this->createSanitizedElement($dom, 'DirRecep', $dte->receptor_direccion, 70));
        }
        if ($dte->receptor_comuna !== null && $dte->receptor_comuna !== '') {
            $rec->appendChild($this->createSanitizedElement($dom, 'CmnaRecep', $dte->receptor_comuna, 20));
        }
        if ($dte->receptor_ciudad !== null && $dte->receptor_ciudad !== '') {
            $rec->appendChild($this->createSanitizedElement($dom, 'CiudadRecep', $dte->receptor_ciudad, 20));
        }

        return $rec;
    }

    private function buildTransporte(DOMDocument $dom, SiiDteEmitidoTraslado $traslado): DOMElement
    {
        $tr = $dom->createElement('Transporte');

        if ($traslado->patente !== null && $traslado->patente !== '') {
            $tr->appendChild($this->createSanitizedElement($dom, 'Patente', $traslado->patente, 8));
        }
        if ($traslado->rut_transportista !== null && $traslado->rut_transportista !== '') {
            $tr->appendChild($dom->createElement('RUTTrans', $traslado->rut_transportista));
        }
        if ($traslado->rut_chofer !== null || $traslado->nombre_chofer !== null) {
            $ch = $dom->createElement('Chofer');
            if ($traslado->rut_chofer) {
                $ch->appendChild($dom->createElement('RUTChofer', $traslado->rut_chofer));
            }
            if ($traslado->nombre_chofer) {
                $ch->appendChild($this->createSanitizedElement($dom, 'NombreChofer', $traslado->nombre_chofer, 80));
            }
            $tr->appendChild($ch);
        }
        if ($traslado->direccion_destino !== null && $traslado->direccion_destino !== '') {
            $tr->appendChild($this->createSanitizedElement($dom, 'DirDest', $traslado->direccion_destino, 70));
        }
        if ($traslado->comuna_destino !== null && $traslado->comuna_destino !== '') {
            $tr->appendChild($this->createSanitizedElement($dom, 'CmnaDest', $traslado->comuna_destino, 20));
        }
        if ($traslado->ciudad_destino !== null && $traslado->ciudad_destino !== '') {
            $tr->appendChild($this->createSanitizedElement($dom, 'CiudadDest', $traslado->ciudad_destino, 20));
        }

        return $tr;
    }

    private function buildTotales(DOMDocument $dom, SiiDteEmitido $dte): DOMElement
    {
        $tot = $dom->createElement('Totales');

        $esExenta = in_array($dte->tipo_dte, [SiiDteEmitido::TIPO_FACTURA_EXENTA, SiiDteEmitido::TIPO_BOLETA_EXENTA], true);

        if (! $esExenta && ((float) $dte->monto_neto) > 0.0) {
            $tot->appendChild($dom->createElement('MntNeto', (string) (int) round((float) $dte->monto_neto)));
        }

        if (((float) $dte->monto_exento) > 0.0 || $esExenta) {
            $tot->appendChild($dom->createElement('MntExe', (string) (int) round((float) $dte->monto_exento)));
        }

        if (! $esExenta) {
            $tot->appendChild($dom->createElement('TasaIVA', number_format((float) $dte->tasa_iva, 2, '.', '')));
            $tot->appendChild($dom->createElement('IVA', (string) (int) round((float) $dte->iva)));
        }

        $tot->appendChild($dom->createElement('MntTotal', (string) (int) round((float) $dte->monto_total)));

        return $tot;
    }

    // -----------------------------------------------------------------
    // Detalle
    // -----------------------------------------------------------------

    private function buildDetalle(DOMDocument $dom, SiiDteEmitidoDetalle $det, int $linea): DOMElement
    {
        $d = $dom->createElement('Detalle');

        $d->appendChild($dom->createElement('NroLinDet', (string) ($det->numero_linea ?? $linea)));

        if ($det->codigo_item !== null && $det->codigo_item !== '') {
            $cdg = $dom->createElement('CdgItem');
            $cdg->appendChild($this->createSanitizedElement($dom, 'TpoCodigo', $det->tipo_codigo ?? 'INT1', 10));
            $cdg->appendChild($this->createSanitizedElement($dom, 'VlrCodigo', $det->codigo_item, 35));
            $d->appendChild($cdg);
        }

        if ($det->exento) {
            $d->appendChild($dom->createElement('IndExe', '1'));
        }

        $d->appendChild($this->createSanitizedElement($dom, 'NmbItem', $det->nombre_item, 80));

        if ($det->descripcion !== null && $det->descripcion !== '') {
            $d->appendChild($this->createSanitizedElement($dom, 'DscItem', $det->descripcion, 1000));
        }

        if ($det->cantidad !== null) {
            $d->appendChild($dom->createElement('QtyItem', number_format((float) $det->cantidad, 6, '.', '')));
        }

        if ($det->unidad_medida !== null && $det->unidad_medida !== '') {
            $d->appendChild($this->createSanitizedElement($dom, 'UnmdItem', $det->unidad_medida, 4));
        }

        if ($det->precio_unitario !== null) {
            $d->appendChild($dom->createElement('PrcItem', number_format((float) $det->precio_unitario, 6, '.', '')));
        }

        if (((float) $det->descuento_pct) > 0.0) {
            $d->appendChild($dom->createElement('DescuentoPct', number_format((float) $det->descuento_pct, 2, '.', '')));
        }
        if (((float) $det->descuento_monto) > 0.0) {
            $d->appendChild($dom->createElement('DescuentoMonto', (string) (int) round((float) $det->descuento_monto)));
        }
        if (((float) $det->recargo_pct) > 0.0) {
            $d->appendChild($dom->createElement('RecargoPct', number_format((float) $det->recargo_pct, 2, '.', '')));
        }
        if (((float) $det->recargo_monto) > 0.0) {
            $d->appendChild($dom->createElement('RecargoMonto', (string) (int) round((float) $det->recargo_monto)));
        }

        $d->appendChild($dom->createElement('MontoItem', (string) (int) round((float) $det->monto_item)));

        return $d;
    }

    private function buildReferencia(DOMDocument $dom, SiiDteEmitidoReferencia $ref): DOMElement
    {
        $r = $dom->createElement('Referencia');
        $r->appendChild($dom->createElement('NroLinRef', (string) $ref->numero_linea));
        $r->appendChild($this->createSanitizedElement($dom, 'TpoDocRef', $ref->tipo_documento_referencia, 3));
        $r->appendChild($this->createSanitizedElement($dom, 'FolioRef', $ref->folio_referencia, 18));

        if ($ref->rut_otro_contribuyente !== null && $ref->rut_otro_contribuyente !== '') {
            $r->appendChild($dom->createElement('RUTOtr', $ref->rut_otro_contribuyente));
        }

        $r->appendChild($dom->createElement('FchRef', $ref->fecha_referencia->format('Y-m-d')));

        if ($ref->codigo_referencia !== null) {
            $r->appendChild($dom->createElement('CodRef', (string) $ref->codigo_referencia));
        }
        if ($ref->razon_referencia !== null && $ref->razon_referencia !== '') {
            $r->appendChild($this->createSanitizedElement($dom, 'RazonRef', $ref->razon_referencia, 90));
        }

        return $r;
    }

    private function buildDscRcgGlobal(DOMDocument $dom, SiiDteEmitido $dte): DOMElement
    {
        $dr = $dom->createElement('DscRcgGlobal');
        $dr->appendChild($dom->createElement('NroLinDR', '1'));
        $dr->appendChild($dom->createElement('TpoMov', 'D'));
        $dr->appendChild($dom->createElement('TpoValor', '$'));
        $dr->appendChild($dom->createElement('ValorDR', (string) (int) round((float) $dte->descuento_global_monto)));

        return $dr;
    }

    // -----------------------------------------------------------------
    // TED — estructura completa con firmas placeholder (F4.2 reemplaza)
    // -----------------------------------------------------------------

    private function buildTed(DOMDocument $dom, SiiDteEmitido $dte): DOMElement
    {
        $ted = $dom->createElement('TED');
        $ted->setAttribute('version', '1.0');

        $dd = $dom->createElement('DD');
        $dd->appendChild($dom->createElement('RE', $dte->emisor_rut));
        $dd->appendChild($dom->createElement('TD', (string) $dte->tipo_dte));
        $dd->appendChild($dom->createElement('F', (string) $dte->folio));
        $dd->appendChild($dom->createElement('FE', $dte->fecha_emision->format('Y-m-d')));
        $dd->appendChild($dom->createElement('RR', $dte->receptor_rut));
        $dd->appendChild($this->createSanitizedElement($dom, 'RSR', $dte->receptor_razon_social, 40));
        $dd->appendChild($dom->createElement('MNT', (string) (int) round((float) $dte->monto_total)));

        $primerItem = $dte->detalles->first();
        $it1 = $primerItem instanceof SiiDteEmitidoDetalle ? $primerItem->nombre_item : 'ITEM';
        $dd->appendChild($this->createSanitizedElement($dom, 'IT1', $it1, 40));

        $dd->appendChild($this->buildCafPlaceholder($dom, $dte));
        $dd->appendChild($dom->createElement('TSTED', now()->format('Y-m-d\TH:i:s')));

        $ted->appendChild($dd);

        $frmt = $dom->createElement('FRMT', self::PLACEHOLDER_BASE64);
        $frmt->setAttribute('algoritmo', 'SHA1withRSA');
        $ted->appendChild($frmt);

        return $ted;
    }

    /**
     * CAF placeholder con estructura valida XSD. F4.2 reemplaza este nodo
     * con el CAF real cargado desde sii_caf (rsa_pubk + firma_caf descifrada).
     */
    private function buildCafPlaceholder(DOMDocument $dom, SiiDteEmitido $dte): DOMElement
    {
        $caf = $dom->createElement('CAF');
        $caf->setAttribute('version', '1.0');

        $da = $dom->createElement('DA');
        $da->appendChild($dom->createElement('RE', $dte->emisor_rut));
        $da->appendChild($this->createSanitizedElement($dom, 'RS', $dte->emisor_razon_social, 40));
        $da->appendChild($dom->createElement('TD', (string) $dte->tipo_dte));

        $rng = $dom->createElement('RNG');
        $rng->appendChild($dom->createElement('D', (string) $dte->folio));
        $rng->appendChild($dom->createElement('H', (string) $dte->folio));
        $da->appendChild($rng);

        $da->appendChild($dom->createElement('FA', $dte->fecha_emision->format('Y-m-d')));

        $rsapk = $dom->createElement('RSAPK');
        $rsapk->appendChild($dom->createElement('M', self::PLACEHOLDER_BASE64));
        $rsapk->appendChild($dom->createElement('E', 'Aw=='));
        $da->appendChild($rsapk);

        $da->appendChild($dom->createElement('IDK', '0'));
        $caf->appendChild($da);

        $frma = $dom->createElement('FRMA', self::PLACEHOLDER_BASE64);
        $frma->setAttribute('algoritmo', 'SHA1withRSA');
        $caf->appendChild($frma);

        return $caf;
    }

    // -----------------------------------------------------------------
    // ds:Signature placeholder (F4.3 reemplaza con firma RSA-SHA1 real)
    // -----------------------------------------------------------------

    private function buildDsSignaturePlaceholder(DOMDocument $dom, SiiDteEmitido $dte): DOMElement
    {
        $sig = $dom->createElementNS(self::NS_DSIG, 'ds:Signature');

        $signedInfo = $dom->createElementNS(self::NS_DSIG, 'ds:SignedInfo');

        $cano = $dom->createElementNS(self::NS_DSIG, 'ds:CanonicalizationMethod');
        $cano->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $signedInfo->appendChild($cano);

        $sigMethod = $dom->createElementNS(self::NS_DSIG, 'ds:SignatureMethod');
        $sigMethod->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#rsa-sha1');
        $signedInfo->appendChild($sigMethod);

        $reference = $dom->createElementNS(self::NS_DSIG, 'ds:Reference');
        $reference->setAttribute('URI', '#D' . $dte->folio);

        $digestMethod = $dom->createElementNS(self::NS_DSIG, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
        $reference->appendChild($digestMethod);

        $digestValue = $dom->createElementNS(self::NS_DSIG, 'ds:DigestValue', self::PLACEHOLDER_BASE64);
        $reference->appendChild($digestValue);

        $signedInfo->appendChild($reference);
        $sig->appendChild($signedInfo);

        $sigValue = $dom->createElementNS(self::NS_DSIG, 'ds:SignatureValue', self::PLACEHOLDER_BASE64);
        $sig->appendChild($sigValue);

        $keyInfo = $dom->createElementNS(self::NS_DSIG, 'ds:KeyInfo');

        $keyValue = $dom->createElementNS(self::NS_DSIG, 'ds:KeyValue');
        $rsaKeyValue = $dom->createElementNS(self::NS_DSIG, 'ds:RSAKeyValue');
        $modulus = $dom->createElementNS(self::NS_DSIG, 'ds:Modulus', self::PLACEHOLDER_BASE64);
        $exponent = $dom->createElementNS(self::NS_DSIG, 'ds:Exponent', 'Aw==');
        $rsaKeyValue->appendChild($modulus);
        $rsaKeyValue->appendChild($exponent);
        $keyValue->appendChild($rsaKeyValue);
        $keyInfo->appendChild($keyValue);

        $x509Data = $dom->createElementNS(self::NS_DSIG, 'ds:X509Data');
        $x509Cert = $dom->createElementNS(self::NS_DSIG, 'ds:X509Certificate', self::PLACEHOLDER_BASE64);
        $x509Data->appendChild($x509Cert);
        $keyInfo->appendChild($x509Data);

        $sig->appendChild($keyInfo);

        return $sig;
    }

    // -----------------------------------------------------------------
    // Helper de creacion de elemento con encoding correcto
    // -----------------------------------------------------------------

    private function createSanitizedElement(DOMDocument $dom, string $tagName, string $rawValue, ?int $maxLength = null): DOMElement
    {
        $element = $dom->createElement($tagName);
        $element->appendChild($dom->createTextNode(Iso88591Helper::sanitize($rawValue, $maxLength)));

        return $element;
    }
}
