<?php

namespace App\Domains\Sii\Services\Xml;

use App\Domains\Sii\Exceptions\DteXmlInvalidException;
use DOMDocument;

/**
 * Valida un XML del DTE contra el XSD oficial DTE_v10.xsd del SII.
 *
 * Usa libxml internal errors para capturar el detalle estructurado y
 * empaquetarlo en DteXmlInvalidException, en lugar de imprimir warnings
 * por stderr.
 */
class DteXsdValidator
{
    private const XSD_PATH = __DIR__ . '/../../Resources/xsd/DTE_v10.xsd';

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

    /**
     * Validacion "soft": retorna array de errores sin lanzar.
     * Util para debug y reportes de UI.
     *
     * @return array<int, \LibXMLError|array<string, mixed>>
     */
    public function obtenerErrores(string $xmlString): array
    {
        try {
            $this->validar($xmlString);

            return [];
        } catch (DteXmlInvalidException $e) {
            return $e->getErroresLibxml();
        }
    }
}
