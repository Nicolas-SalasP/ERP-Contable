<?php

namespace App\Domains\Sii\Exceptions;

use RuntimeException;

/**
 * HARDENING-1 R2 — Se lanza cuando XmlDteIntegrityService no puede recuperar
 * un XML del DTE con integridad verificada (ni desde disco ni desde el backup
 * cifrado en BD).
 */
class IntegridadXmlException extends RuntimeException
{
    public const MOTIVO_DTE_NO_EMITIDO          = 'dte_no_emitido';
    public const MOTIVO_AMBAS_FUENTES_CORRUPTAS = 'ambas_fuentes_corruptas';

    public readonly string $motivo;
    public readonly int $dteId;

    public function __construct(string $message, string $motivo, int $dteId)
    {
        parent::__construct($message);
        $this->motivo = $motivo;
        $this->dteId  = $dteId;
    }

    public static function dteNoEmitido(int $dteId): self
    {
        return new self(
            "DTE {$dteId} no tiene XML persistido (xml_path o xml_hash_sha256 nulos); aun no fue firmado.",
            self::MOTIVO_DTE_NO_EMITIDO,
            $dteId
        );
    }

    public static function ambasFuentesCorruptas(int $dteId): self
    {
        return new self(
            "DTE {$dteId}: tanto el XML en disco como el backup cifrado en BD estan corruptos o ausentes. Recuperacion imposible.",
            self::MOTIVO_AMBAS_FUENTES_CORRUPTAS,
            $dteId
        );
    }
}
