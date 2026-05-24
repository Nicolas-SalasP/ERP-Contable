<?php

namespace App\Domains\Sii\Exceptions;

use RuntimeException;

class CafInvalidoException extends RuntimeException
{
    public const MOTIVO_XML_MALFORMADO     = 'xml_malformado';
    public const MOTIVO_ESTRUCTURA_INVALIDA = 'estructura_invalida';
    public const MOTIVO_RUT_NO_COINCIDE    = 'rut_no_coincide';
    public const MOTIVO_YA_EXISTE          = 'ya_existe';

    public readonly string $motivo;

    public function __construct(string $message, string $motivo)
    {
        parent::__construct($message);
        $this->motivo = $motivo;
    }

    public static function xmlMalformado(string $detalle): self
    {
        return new self(
            'El archivo CAF no es XML valido. Detalle: ' . $detalle,
            self::MOTIVO_XML_MALFORMADO
        );
    }

    public static function estructuraInvalida(string $nodoFaltante): self
    {
        return new self(
            'El CAF no tiene la estructura esperada. Nodo faltante o invalido: ' . $nodoFaltante,
            self::MOTIVO_ESTRUCTURA_INVALIDA
        );
    }

    public static function rutNoCoincide(string $rutCaf, string $rutEmpresa): self
    {
        return new self(
            "El RUT del CAF ({$rutCaf}) no coincide con el RUT de la empresa ({$rutEmpresa}).",
            self::MOTIVO_RUT_NO_COINCIDE
        );
    }

    public static function yaExiste(string $siiIdk, int $empresaId): self
    {
        return new self(
            "Ya existe un CAF cargado con sii_idk={$siiIdk} para la empresa {$empresaId}.",
            self::MOTIVO_YA_EXISTE
        );
    }
}
