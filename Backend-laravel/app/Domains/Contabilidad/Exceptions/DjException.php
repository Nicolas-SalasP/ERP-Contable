<?php

namespace App\Domains\Contabilidad\Exceptions;

use Exception;

class DjException extends Exception
{
    public static function regla(string $mensaje): self
    {
        return new self($mensaje);
    }

    public static function noEncontrado(string $mensaje): self
    {
        return new self($mensaje, 404);
    }
}
