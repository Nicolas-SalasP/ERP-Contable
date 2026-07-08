<?php

namespace App\Domains\Sii\Exceptions;

use RuntimeException;

/**
 * Se lanza cuando marcarFolioUsado() encuentra, bajo lock, que el folio ya no esta en estado RESERVADO
 * (ej. un admin revoco el CAF y el folio paso a HUERFANO mientras el DTE se estaba firmando).
 * Evita sobreescribir silenciosamente ese estado y emitir un DTE sobre un folio invalido ante el SII.
 */
class FolioUsoEstadoInvalidoException extends RuntimeException
{
    public readonly int $folioUsoId;
    public readonly string $estadoActual;
    public readonly string $estadoEsperado;

    public function __construct(string $message, int $folioUsoId, string $estadoActual, string $estadoEsperado)
    {
        parent::__construct($message);
        $this->folioUsoId     = $folioUsoId;
        $this->estadoActual   = $estadoActual;
        $this->estadoEsperado = $estadoEsperado;
    }

    public static function alMarcarUsado(int $folioUsoId, string $estadoActual, string $estadoEsperado): self
    {
        return new self(
            "No se puede marcar como USADO el folio uso {$folioUsoId}: su estado actual es \"{$estadoActual}\", se esperaba \"{$estadoEsperado}\". El folio pudo haber sido revocado (HUERFANO) mientras el DTE se firmaba.",
            $folioUsoId,
            $estadoActual,
            $estadoEsperado
        );
    }
}
