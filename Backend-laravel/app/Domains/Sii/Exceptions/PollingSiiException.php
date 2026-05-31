<?php

namespace App\Domains\Sii\Exceptions;

use RuntimeException;

/**
 * F5.3 — Errores del flujo de polling del estado contra QueryEstUp del SII.
 */
class PollingSiiException extends RuntimeException
{
    public const MOTIVO_RESPUESTA_SIN_ESTADO   = 'respuesta_sin_estado';
    public const MOTIVO_CODIGO_DESCONOCIDO     = 'codigo_desconocido';
    public const MOTIVO_TOKEN_EXPIRADO         = 'token_expirado_sin_reintento';

    public readonly string $motivo;
    public readonly array $contexto;

    public function __construct(string $message, string $motivo, array $contexto = [])
    {
        parent::__construct($message);
        $this->motivo   = $motivo;
        $this->contexto = $contexto;
    }

    public static function respuestaSinEstado(string $body): self
    {
        $muestra = substr($body, 0, 200);
        return new self(
            "Respuesta de QueryEstUp no contiene <ESTADO> parseable. Body[0..200]: {$muestra}",
            self::MOTIVO_RESPUESTA_SIN_ESTADO
        );
    }

    public static function codigoSiiDesconocido(string $codigo, string $glosa): self
    {
        return new self(
            "Codigo SII desconocido en respuesta de QueryEstUp: '{$codigo}'. GLOSA: {$glosa}",
            self::MOTIVO_CODIGO_DESCONOCIDO,
            ['codigo' => $codigo, 'glosa' => $glosa]
        );
    }

    public static function tokenExpiradoSinReintento(): self
    {
        return new self(
            'Token SII expirado y ya se intento regenerar la sesion; no se reintenta nuevamente.',
            self::MOTIVO_TOKEN_EXPIRADO
        );
    }
}
