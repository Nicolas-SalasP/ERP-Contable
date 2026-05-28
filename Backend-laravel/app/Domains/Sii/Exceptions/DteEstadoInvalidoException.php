<?php

namespace App\Domains\Sii\Exceptions;

use App\Domains\Sii\Models\SiiDteEmitido;
use RuntimeException;

/**
 * Se lanza cuando una operacion (emision, anulacion, reenvio) se intenta
 * sobre un DTE cuyo estado no la permite.
 *
 * F4.4: solo DTE en BORRADOR pueden emitirse. Llamar emitir() sobre un
 * DTE ya FIRMADO o ENVIADO_SII lanza esto, garantizando idempotencia.
 */
class DteEstadoInvalidoException extends RuntimeException
{
    public const MOTIVO_NO_ES_BORRADOR = 'no_es_borrador';

    public readonly string $motivo;
    public readonly int $dteId;
    public readonly string $estadoActual;

    public function __construct(string $message, string $motivo, int $dteId, string $estadoActual)
    {
        parent::__construct($message);
        $this->motivo       = $motivo;
        $this->dteId        = $dteId;
        $this->estadoActual = $estadoActual;
    }

    public static function noEsBorrador(int $dteId, string $estadoActual): self
    {
        return new self(
            sprintf(
                'DTE %d esta en estado "%s"; solo DTE en "%s" pueden emitirse.',
                $dteId,
                $estadoActual,
                SiiDteEmitido::ESTADO_BORRADOR
            ),
            self::MOTIVO_NO_ES_BORRADOR,
            $dteId,
            $estadoActual
        );
    }
}
