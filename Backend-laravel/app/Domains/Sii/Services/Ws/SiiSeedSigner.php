<?php

namespace App\Domains\Sii\Services\Ws;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Services\Certificado\CertificadoService;
use App\Domains\Sii\Services\Xml\XmlDsigSigner;
use DOMDocument;

/**
 * F5.1 — Construye y firma el XML <getToken><item><Semilla>X</Semilla></item></getToken>
 * que se envia al WS GetTokenFromSeed del SII.
 *
 * El SII espera firma XMLDSig SHA1+RSA+C14N1.0+enveloped con Reference URI=""
 * (firma del documento completo). La Signature queda como ultimo hijo de
 * <getToken>, hermana de <item>.
 *
 * El cert + clave privada vienen del CertificadoService activo de la empresa
 * (mismo cert usado para firmar Documento y SetDTE en F4.3).
 */
class SiiSeedSigner
{
    public function __construct(
        private readonly XmlDsigSigner $xmlDsigSigner,
        private readonly CertificadoService $certificadoService
    ) {
    }

    /**
     * @return string XML firmado, listo para enviar al WS getToken del SII.
     *
     * @throws \App\Domains\Sii\Exceptions\CertificadoInvalidoException
     *         si la empresa no tiene cert activo o el .pfx no se puede descifrar.
     */
    public function firmar(string $semilla, Empresa $empresa): string
    {
        $par = $this->certificadoService->extraerParPemDeEmpresa($empresa);

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput       = false;

        // Estructura del payload getToken segun spec SII:
        //   <getToken><item><Semilla>{semilla}</Semilla></item></getToken>
        $root = $dom->createElement('getToken');
        $item = $dom->createElement('item');
        $item->appendChild($dom->createElement('Semilla', $semilla));
        $root->appendChild($item);
        $dom->appendChild($root);

        $this->xmlDsigSigner->firmarDocumentoEnvelope($dom, $par['cert'], $par['privKey']);

        $xml = $dom->saveXML();
        if ($xml === false) {
            // Practicamente imposible — saveXML solo retorna false si el DOM
            // esta corrupto, lo cual lo habriamos detectado en firmarDocumentoEnvelope.
            throw new \RuntimeException('saveXML retorno false al serializar getToken firmado.');
        }

        return $xml;
    }
}
