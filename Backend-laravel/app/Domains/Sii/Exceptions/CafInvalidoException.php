<?php

namespace App\Domains\Sii\Exceptions;

use RuntimeException;

class CafInvalidoException extends RuntimeException
{
    public const MOTIVO_XML_MALFORMADO       = 'xml_malformado';
    public const MOTIVO_ESTRUCTURA_INVALIDA  = 'estructura_invalida';
    public const MOTIVO_RUT_NO_COINCIDE      = 'rut_no_coincide';
    public const MOTIVO_YA_EXISTE            = 'ya_existe';
    public const MOTIVO_RSA_SK_NO_LEGIBLE    = 'rsa_sk_no_legible';
    public const MOTIVO_BLOQUE_CAF_AUSENTE   = 'bloque_caf_ausente';

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

    public static function rsaSkNoLegible(string $detalleOpenssl = ''): self
    {
        $msg = 'La clave privada RSA del CAF no se puede cargar (openssl_pkey_get_private fallo).';
        if ($detalleOpenssl !== '') {
            $msg .= ' Detalle: ' . $detalleOpenssl;
        }

        return new self($msg, self::MOTIVO_RSA_SK_NO_LEGIBLE);
    }

    public static function bloqueCafAusente(int $cafId): self
    {
        return new self(
            "El XML cifrado del CAF {$cafId} no contiene el bloque <CAF> esperado.",
            self::MOTIVO_BLOQUE_CAF_AUSENTE
        );
    }
}
