<?php

namespace App\Domains\Sii\Services\Xml\SetDte;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Exceptions\DteXmlInvalidException;
use App\Domains\Sii\Services\Certificado\CertificadoService;
use App\Domains\Sii\Services\Xml\XmlDsigSigner;
use DOMDocument;
use DOMElement;

/**
 * Firma el <SetDTE ID="SetDocDTE"> dentro del <EnvioDTE> producido por
 * SetDteBuilder. La <ds:Signature> resultante se inserta como hermano de
 * <SetDTE> dentro de <EnvioDTE>, cumpliendo el XSD oficial EnvioDTE_v10.xsd
 * (sequence: SetDTE, ds:Signature).
 *
 * Estructuralmente analogo a DteSigner pero opera sobre SetDTE y NO necesita
 * eliminar placeholders previos (SetDteBuilder no inserta firmas placeholder).
 */
class SetDteSigner
{
    public function __construct(
        private readonly XmlDsigSigner $xmlDsigSigner,
        private readonly CertificadoService $certificadoService
    ) {
    }

    /**
     * @throws DteXmlInvalidException si el parseo o la verificacion fallan.
     * @throws \App\Domains\Sii\Exceptions\CertificadoInvalidoException
     */
    public function firmar(string $xmlSetDte, Empresa $empresa): string
    {
        $par = $this->certificadoService->extraerParPemDeEmpresa($empresa);

        $dom = new DOMDocument('1.0', 'ISO-8859-1');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput       = false;

        if (! @$dom->loadXML($xmlSetDte)) {
            throw DteXmlInvalidException::estructuraIncoherente('XML del SetDTE no se pudo parsear.');
        }

        $setDte = $this->localizarSetDte($dom);
        $idSetDte = $setDte->getAttribute('ID');
        if ($idSetDte === '') {
            throw DteXmlInvalidException::estructuraIncoherente(
                '<SetDTE> sin atributo ID (esperado: ID="SetDocDTE").'
            );
        }

        $envio = $setDte->parentNode;
        if (! $envio instanceof DOMElement || $envio->localName !== 'EnvioDTE') {
            throw DteXmlInvalidException::estructuraIncoherente(
                'El padre de <SetDTE> no es <EnvioDTE>.'
            );
        }

        $this->xmlDsigSigner->firmarNodo(
            $dom,
            $idSetDte,
            'SetDTE',
            $par['cert'],
            $par['privKey'],
            $envio,
            null
        );

        $xmlFirmado = $dom->saveXML();
        if ($xmlFirmado === false) {
            throw DteXmlInvalidException::estructuraIncoherente('saveXML retorno false tras firmar SetDTE.');
        }

        return $xmlFirmado;
    }

    private function localizarSetDte(DOMDocument $dom): DOMElement
    {
        $sets = $dom->getElementsByTagName('SetDTE');
        if ($sets->length === 0) {
            throw DteXmlInvalidException::estructuraIncoherente('No se encontro nodo <SetDTE> en el XML.');
        }

        $nodo = $sets->item(0);
        if (! $nodo instanceof DOMElement) {
            throw DteXmlInvalidException::estructuraIncoherente('<SetDTE> no es DOMElement.');
        }

        return $nodo;
    }
}
