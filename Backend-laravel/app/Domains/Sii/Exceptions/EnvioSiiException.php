<?php

namespace App\Domains\Sii\Exceptions;

use RuntimeException;

/**
 * F5.2 — Errores del flujo de envio del XML EnvioDTE al WS DTEUpload del SII.
 */
class EnvioSiiException extends RuntimeException
{
    public const MOTIVO_YA_ENVIADO            = 'ya_enviado';
    public const MOTIVO_DTE_NO_FIRMADO        = 'dte_no_firmado';
    public const MOTIVO_RESPUESTA_SIN_TRACKID = 'respuesta_sin_trackid';
    public const MOTIVO_ERROR_PERMANENTE_SII  = 'error_permanente_sii';
    public const MOTIVO_ERROR_TRANSPORTE      = 'error_transporte';

    public readonly string $motivo;

    /** Datos opcionales: trackId previo, codigo SII, http status, etc. */
    public readonly array $contexto;

    public function __construct(string $message, string $motivo, array $contexto = [])
    {
        parent::__construct($message);
        $this->motivo   = $motivo;
        $this->contexto = $contexto;
    }

    public static function yaEnviado(int $dteId, string $trackIdPrevio): self
    {
        return new self(
            "DTE {$dteId} ya fue enviado exitosamente al SII (track_id={$trackIdPrevio}).",
            self::MOTIVO_YA_ENVIADO,
            ['dte_id' => $dteId, 'track_id_previo' => $trackIdPrevio]
        );
    }

    public static function dteNoFirmado(int $dteId, string $estadoActual): self
    {
        return new self(
            "DTE {$dteId} esta en estado '{$estadoActual}'; solo DTE en 'FIRMADO' pueden enviarse al SII.",
            self::MOTIVO_DTE_NO_FIRMADO,
            ['dte_id' => $dteId, 'estado_actual' => $estadoActual]
        );
    }

    public static function respuestaSinTrackId(string $body): self
    {
        $muestra = substr($body, 0, 200);
        return new self(
            "Respuesta del DTEUpload del SII no contiene TRACKID parseable. Body[0..200]: {$muestra}",
            self::MOTIVO_RESPUESTA_SIN_TRACKID
        );
    }

    public static function errorPermanenteSII(int $codigoError, string $glosa): self
    {
        return new self(
            "SII rechazo el envio con ERROR={$codigoError}. GLOSA: {$glosa}",
            self::MOTIVO_ERROR_PERMANENTE_SII,
            ['codigo_error' => $codigoError, 'glosa' => $glosa]
        );
    }

    public static function errorTransporte(int $httpStatus, string $body, int $intentos): self
    {
        $muestra = substr($body, 0, 200);
        return new self(
            "Fallo de transporte al subir DTE tras {$intentos} intentos. HTTP {$httpStatus}. Body[0..200]: {$muestra}",
            self::MOTIVO_ERROR_TRANSPORTE,
            ['http_status' => $httpStatus, 'intentos' => $intentos]
        );
    }
}
