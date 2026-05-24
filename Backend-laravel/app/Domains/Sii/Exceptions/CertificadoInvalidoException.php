<?php

namespace App\Domains\Sii\Exceptions;

use Carbon\CarbonInterface;
use RuntimeException;

class CertificadoInvalidoException extends RuntimeException
{
    public const MOTIVO_PASSWORD_INCORRECTA = 'password_incorrecta';
    public const MOTIVO_PFX_CORRUPTO        = 'pfx_corrupto';
    public const MOTIVO_RUT_NO_ENCONTRADO   = 'rut_no_encontrado';
    public const MOTIVO_VENCIDO             = 'vencido';

    public readonly string $motivo;

    public function __construct(string $message, string $motivo)
    {
        parent::__construct($message);
        $this->motivo = $motivo;
    }

    public static function passwordIncorrecta(): self
    {
        return new self(
            'La contrasena del certificado es incorrecta o el archivo esta protegido por una passphrase distinta.',
            self::MOTIVO_PASSWORD_INCORRECTA
        );
    }

    public static function pfxCorrupto(string $reason = ''): self
    {
        $msg = 'El archivo .pfx esta corrupto o no es un PKCS#12 valido.';
        if ($reason !== '') {
            $msg .= ' Detalle: ' . $reason;
        }

        return new self($msg, self::MOTIVO_PFX_CORRUPTO);
    }

    public static function rutNoEncontrado(): self
    {
        return new self(
            'No se pudo determinar el RUT del titular en el certificado.',
            self::MOTIVO_RUT_NO_ENCONTRADO
        );
    }

    public static function vencido(CarbonInterface $validoHasta): self
    {
        return new self(
            'El certificado vencio el ' . $validoHasta->toDateString() . '. Cargue uno vigente.',
            self::MOTIVO_VENCIDO
        );
    }
}
