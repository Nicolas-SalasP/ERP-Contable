<?php

namespace App\Domains\Sii\Exceptions;

use DomainException;

/**
 * F6.4 — El estado actual del DTE/envio no permite reintento manual.
 *
 * Casos:
 *   - estado_terminal: DTE en ACEPTADO/ACEPTADO_CON_REPAROS/RECHAZADO.
 *   - ya_en_proceso:   ultimo envio en PENDIENTE/ENVIANDO/ENVIADO (el polling
 *                      de F5.3 esta trabajandolo).
 *   - dte_no_reintentable: estado no contemplado (defensa).
 *
 * El controller la traduce a HTTP 422 con shape estructurado.
 */
class ReintentoNoAplicableException extends DomainException
{
    public const RAZON_ESTADO_TERMINAL      = 'estado_terminal';
    public const RAZON_YA_EN_PROCESO        = 'ya_en_proceso';
    public const RAZON_DTE_NO_REINTENTABLE  = 'dte_no_reintentable';

    public readonly string $razon;
    public readonly int $facturaId;
    public readonly ?string $estadoActual;

    public function __construct(
        string $message,
        string $razon,
        int $facturaId,
        ?string $estadoActual = null
    ) {
        parent::__construct($message);
        $this->razon        = $razon;
        $this->facturaId    = $facturaId;
        $this->estadoActual = $estadoActual;
    }

    public static function estadoTerminal(int $facturaId, string $estadoActual): self
    {
        return new self(
            "Factura {$facturaId}: DTE en estado terminal '{$estadoActual}' no se puede reintentar.",
            self::RAZON_ESTADO_TERMINAL,
            $facturaId,
            $estadoActual
        );
    }

    public static function yaEnProceso(int $facturaId, string $estadoActual): self
    {
        return new self(
            "Factura {$facturaId}: ultimo envio en estado '{$estadoActual}'; el SII todavia esta procesando.",
            self::RAZON_YA_EN_PROCESO,
            $facturaId,
            $estadoActual
        );
    }

    public static function dteNoReintentable(int $facturaId, ?string $estadoActual): self
    {
        return new self(
            "Factura {$facturaId}: DTE en estado '" . ($estadoActual ?? 'desconocido')
                . "' no es elegible para reintento manual.",
            self::RAZON_DTE_NO_REINTENTABLE,
            $facturaId,
            $estadoActual
        );
    }
}
