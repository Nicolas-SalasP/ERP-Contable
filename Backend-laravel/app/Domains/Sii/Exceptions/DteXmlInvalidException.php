<?php

namespace App\Domains\Sii\Exceptions;

use LibXMLError;
use RuntimeException;

class DteXmlInvalidException extends RuntimeException
{
    public const MOTIVO_XSD                  = 'xsd_invalido';
    public const MOTIVO_ESTRUCTURA_INCOHERENTE = 'estructura_incoherente';

    public readonly string $motivo;

    /** @var array<int, LibXMLError|array<string, mixed>> */
    public readonly array $erroresLibxml;

    /**
     * @param array<int, LibXMLError|array<string, mixed>> $erroresLibxml
     */
    public function __construct(string $message, string $motivo, array $erroresLibxml = [])
    {
        parent::__construct($message);
        $this->motivo        = $motivo;
        $this->erroresLibxml = $erroresLibxml;
    }

    /**
     * @param array<int, LibXMLError> $erroresLibxml
     */
    public static function contraXsd(array $erroresLibxml): self
    {
        $mensajes = array_map(
            fn ($e) => isset($e->line) ? "L{$e->line}: " . trim($e->message) : trim($e->message),
            $erroresLibxml
        );

        $detalle = implode(' | ', $mensajes) ?: 'sin detalle libxml';

        return new self(
            'El XML del DTE no cumple el XSD oficial. ' . $detalle,
            self::MOTIVO_XSD,
            $erroresLibxml
        );
    }

    public static function estructuraIncoherente(string $detalle): self
    {
        return new self(
            'Estructura del XML incoherente: ' . $detalle,
            self::MOTIVO_ESTRUCTURA_INCOHERENTE
        );
    }

    /**
     * @return array<int, LibXMLError|array<string, mixed>>
     */
    public function getErroresLibxml(): array
    {
        return $this->erroresLibxml;
    }
}
