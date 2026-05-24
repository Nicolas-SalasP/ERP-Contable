<?php

namespace App\Domains\Sii\Exceptions;

use RuntimeException;

/**
 * F5.1 — Fallos del flujo de autenticacion con el SII (getSeed / getToken).
 */
class SiiAutenticacionException extends RuntimeException
{
    public const MOTIVO_SEMILLA_NO_OBTENIDA = 'semilla_no_obtenida';
    public const MOTIVO_SEMILLA_INVALIDA    = 'semilla_invalida';
    public const MOTIVO_TOKEN_NO_OBTENIDO   = 'token_no_obtenido';
    public const MOTIVO_TOKEN_INVALIDO      = 'token_invalido';
    public const MOTIVO_TIMEOUT_RED         = 'timeout_red';

    public readonly string $motivo;

    /** @var int|null HTTP status del WS SII si aplica. */
    public readonly ?int $httpStatus;

    public function __construct(string $message, string $motivo, ?int $httpStatus = null)
    {
        parent::__construct($message);
        $this->motivo     = $motivo;
        $this->httpStatus = $httpStatus;
    }

    public static function semillaNoObtenida(int $httpStatus, string $body): self
    {
        $muestra = substr($body, 0, 200);
        return new self(
            "Fallo HTTP {$httpStatus} al obtener semilla del SII. Body[0..200]: {$muestra}",
            self::MOTIVO_SEMILLA_NO_OBTENIDA,
            $httpStatus
        );
    }

    public static function semillaInvalida(string $detalle): self
    {
        return new self(
            'Respuesta del SII al obtener semilla es invalida: ' . $detalle,
            self::MOTIVO_SEMILLA_INVALIDA
        );
    }

    public static function tokenNoObtenido(int $httpStatus, string $body): self
    {
        $muestra = substr($body, 0, 200);
        return new self(
            "Fallo HTTP {$httpStatus} al obtener token del SII. Body[0..200]: {$muestra}",
            self::MOTIVO_TOKEN_NO_OBTENIDO,
            $httpStatus
        );
    }

    public static function tokenInvalido(string $detalle): self
    {
        return new self(
            'Respuesta del SII al obtener token es invalida: ' . $detalle,
            self::MOTIVO_TOKEN_INVALIDO
        );
    }

    public static function timeoutRed(int $intentos): self
    {
        return new self(
            "Timeout de red tras {$intentos} intentos contra el WS SII.",
            self::MOTIVO_TIMEOUT_RED
        );
    }
}
