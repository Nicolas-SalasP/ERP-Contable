<?php

namespace App\Domains\Sii\Services\Xml;

use App\Domains\Sii\Exceptions\DteXmlInvalidException;
use DOMDocument;

/** Valida el sobre <EnvioDTE> (Caratula + SetDTE firmado) contra el XSD oficial EnvioDTE_v10.xsd -- el payload completo que realmente se envia al SII; DteXsdValidator valida solo el <Documento> interno antes de envolverlo, este validador cubre el sobre final, incluyendo boletas (39/41), que SetDteBuilder construye igualmente como <EnvioDTE> (mismo elemento raiz para todos los tipos). */
class EnvioDteXsdValidator
{
    private const XSD_PATH = __DIR__ . '/../../Resources/xsd/EnvioDTE_v10.xsd';

    /**
     * @throws DteXmlInvalidException si el XML no valida contra el XSD oficial.
     */
    public function validar(string $xmlString): void
    {
        $prev = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $dom = new DOMDocument();
            $cargo = @$dom->loadXML($xmlString);
            if (! $cargo) {
                $errores = libxml_get_errors();
                libxml_clear_errors();
                throw DteXmlInvalidException::contraXsd($errores);
            }

            if (! @$dom->schemaValidate(self::XSD_PATH)) {
                $errores = libxml_get_errors();
                libxml_clear_errors();
                throw DteXmlInvalidException::contraXsd($errores);
            }
        } finally {
            libxml_use_internal_errors($prev);
        }
    }
}
