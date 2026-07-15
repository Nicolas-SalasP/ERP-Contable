<?php

namespace App\Domains\Sii\Services\Xml;

use App\Domains\Sii\Exceptions\DteXmlInvalidException;
use DOMDocument;

/**
 * Valida el sobre final (Caratula + SetDTE firmado) contra el XSD oficial del SII -- el payload
 * completo que realmente se envia. DteXsdValidator valida solo el <Documento> interno antes de
 * envolverlo, este validador cubre el sobre. SetDteBuilder construye <EnvioDTE> para
 * factura/NC/ND y <EnvioBOLETA> para boletas (39/41) -- distinto elemento raiz, distinto XSD
 * (EnvioDTE_v10.xsd vs EnvioBOLETA_v11.xsd) -- este validador elige el XSD segun la raiz real
 * del XML recibido, no asume un tipo fijo.
 */
class EnvioDteXsdValidator
{
    private const XSD_PATH_DTE = __DIR__.'/../../Resources/xsd/EnvioDTE_v10.xsd';

    private const XSD_PATH_BOLETA = __DIR__.'/../../Resources/xsd/EnvioBOLETA_v11.xsd';

    /**
     * @throws DteXmlInvalidException si el XML no valida contra el XSD oficial.
     */
    public function validar(string $xmlString): void
    {
        $prev = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $dom = new DOMDocument;
            $cargo = @$dom->loadXML($xmlString);
            if (! $cargo) {
                $errores = libxml_get_errors();
                libxml_clear_errors();
                throw DteXmlInvalidException::contraXsd($errores);
            }

            $raiz = $dom->documentElement?->localName;
            $xsdPath = $raiz === 'EnvioBOLETA' ? self::XSD_PATH_BOLETA : self::XSD_PATH_DTE;

            if (! @$dom->schemaValidate($xsdPath)) {
                $errores = libxml_get_errors();
                libxml_clear_errors();
                throw DteXmlInvalidException::contraXsd($errores);
            }
        } finally {
            libxml_use_internal_errors($prev);
        }
    }
}
