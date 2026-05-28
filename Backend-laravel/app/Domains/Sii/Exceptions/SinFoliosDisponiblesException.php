<?php

namespace App\Domains\Sii\Exceptions;

use RuntimeException;

class SinFoliosDisponiblesException extends RuntimeException
{
    public readonly int $tipoDte;
    public readonly int $empresaId;

    public function __construct(string $message, int $tipoDte, int $empresaId)
    {
        parent::__construct($message);
        $this->tipoDte   = $tipoDte;
        $this->empresaId = $empresaId;
    }

    public static function paraTipo(int $tipoDte, int $empresaId): self
    {
        return new self(
            "No hay folios CAF disponibles para el tipo DTE {$tipoDte} en la empresa {$empresaId}. Cargue un nuevo CAF.",
            $tipoDte,
            $empresaId
        );
    }
}
