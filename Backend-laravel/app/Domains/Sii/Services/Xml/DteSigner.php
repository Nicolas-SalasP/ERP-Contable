<?php

namespace App\Domains\Sii\Services\Xml;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Exceptions\DteXmlInvalidException;
use App\Domains\Sii\Services\Certificado\CertificadoService;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RobRichards\XMLSecLibs\XMLSecurityDSig;

/**
 * Firma el <Documento ID="D{folio}"> de un XML de DTE producido por
 * DteXmlBuilder (F4.1 placeholder estructural, o F4.2 con TED real).
 *
 * Estrategia:
 *  1) Elimina el <ds:Signature> placeholder que F4.1 dejo como hijo directo
 *     de <DTE> — su presencia contaminaria el digest del <Documento> dado
 *     que el firmante calcula enveloped sobre todo el contenido restante.
 *  2) Aplica XmlDsigSigner sobre <Documento>, dejando la <ds:Signature> real
 *     como hermano de <Documento> dentro de <DTE>.
 */
class DteSigner
{
    public function __construct(
        private readonly XmlDsigSigner $xmlDsigSigner,
        private readonly CertificadoService $certificadoService
    ) {
    }

    /**
     * @param string $xmlDteConTed XML del DTE (output de DteXmlBuilder::build,
     *                              con o sin TED firmado de F4.2).
     *
     * @return string XML con <Documento> firmado XMLDSig en bytes ISO-8859-1.
     *
     * @throws DteXmlInvalidException si el parseo o la verificacion fallan.
     * @throws \App\Domains\Sii\Exceptions\CertificadoInvalidoException
     */
    public function firmar(string $xmlDteConTed, Empresa $empresa): string
    {
        $par = $this->certificadoService->extraerParPemDeEmpresa($empresa);

        $dom = $this->cargarXmlPreservandoBytes($xmlDteConTed);

        $documento = $this->localizarDocumento($dom);
        $idDocumento = $documento->getAttribute('ID');
        if ($idDocumento === '') {
            throw DteXmlInvalidException::estructuraIncoherente(
                '<Documento> sin atributo ID (esperado: ID="D{folio}").'
            );
        }

        $dte = $documento->parentNode;
        if (! $dte instanceof DOMElement || $dte->localName !== 'DTE') {
            throw DteXmlInvalidException::estructuraIncoherente(
                'El padre de <Documento> no es <DTE>.'
            );
        }

        $this->eliminarSignaturePlaceholderHijoDeDTE($dom, $dte);

        $this->xmlDsigSigner->firmarNodo(
            $dom,
            $idDocumento,
            'Documento',
            $par['cert'],
            $par['privKey'],
            $dte,
            null
        );

        $xmlFirmado = $dom->saveXML();
        if ($xmlFirmado === false) {
            throw DteXmlInvalidException::estructuraIncoherente('saveXML retorno false tras firmar Documento.');
        }

        return $xmlFirmado;
    }

    private function cargarXmlPreservandoBytes(string $xml): DOMDocument
    {
        $dom = new DOMDocument('1.0', 'ISO-8859-1');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput       = false;

        if (! @$dom->loadXML($xml)) {
            throw DteXmlInvalidException::estructuraIncoherente('XML del DTE no se pudo parsear.');
        }

        return $dom;
    }

    private function localizarDocumento(DOMDocument $dom): DOMElement
    {
        $documentos = $dom->getElementsByTagName('Documento');
        if ($documentos->length === 0) {
            throw DteXmlInvalidException::estructuraIncoherente('No se encontro nodo <Documento> en el XML.');
        }

        $nodo = $documentos->item(0);
        if (! $nodo instanceof DOMElement) {
            throw DteXmlInvalidException::estructuraIncoherente('<Documento> no es DOMElement.');
        }

        return $nodo;
    }

    /**
     * Quita los <ds:Signature> que son hijos directos de <DTE> (placeholder de
     * F4.1). Conserva firmas anidadas en otros bloques (no las hay en F4.x,
     * pero esta defensividad evita borrar firmas legitimas futuras).
     */
    private function eliminarSignaturePlaceholderHijoDeDTE(DOMDocument $dom, DOMElement $dte): void
    {
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ds', XMLSecurityDSig::XMLDSIGNS);

        // Solo hijos DIRECTOS de <DTE> con local-name='Signature' en namespace ds.
        $nodos = $xpath->query('./ds:Signature', $dte);
        if ($nodos === false) {
            return;
        }

        // Iterar a una lista materializada para poder remover durante el loop.
        $aRemover = [];
        foreach ($nodos as $n) {
            $aRemover[] = $n;
        }
        foreach ($aRemover as $n) {
            $dte->removeChild($n);
        }
    }
}
