<?php

namespace App\Domains\Sii\Exceptions;

use RuntimeException;

/**
 * F5.1 — Se lanza cuando la empresa no tiene configuracion suficiente para
 * operar contra el SII en el ambiente solicitado.
 *
 * Caso paradigmatico: empresa.ambiente_sii='produccion' sin resolucion_sii_numero.
 * Bloquearlo upfront previene autenticaciones contra el WS de produccion
 * con DTEs que el SII rechazara por falta de resolucion (decision D6=A).
 */
class SiiConfiguracionIncompletaException extends RuntimeException
{
    public const MOTIVO_PROD_SIN_RESOLUCION = 'prod_sin_resolucion';
    public const MOTIVO_CERT_INACTIVO       = 'cert_inactivo';

    public readonly string $motivo;
    public readonly int $empresaId;

    public function __construct(string $message, string $motivo, int $empresaId)
    {
        parent::__construct($message);
        $this->motivo    = $motivo;
        $this->empresaId = $empresaId;
    }

    public static function ambienteProdSinResolucion(int $empresaId): self
    {
        return new self(
            "Empresa {$empresaId} esta configurada en ambiente='produccion' pero no tiene resolucion_sii_numero. Sin resolucion no se puede operar en produccion.",
            self::MOTIVO_PROD_SIN_RESOLUCION,
            $empresaId
        );
    }

    public static function certificadoInactivo(int $empresaId): self
    {
        return new self(
            "Empresa {$empresaId} no tiene certificado digital en estado 'activo'. Requerido para firmar la semilla del SII.",
            self::MOTIVO_CERT_INACTIVO,
            $empresaId
        );
    }
}
