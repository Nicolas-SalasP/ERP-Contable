<?php

namespace App\Domains\Rrhh\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Excepción de negocio del dominio RRHH: lleva un status HTTP (404 no encontrado/otra empresa, 422 regla de negocio) y se renderiza como JSON estable (mismo patrón que PeriodoCerradoException). */
class RrhhException extends RuntimeException
{
    public function __construct(string $message, private readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public static function noEncontrado(string $message = 'El recurso no existe o no pertenece a la empresa.'): self
    {
        return new self($message, 404);
    }

    public static function regla(string $message): self
    {
        return new self($message, 422);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], $this->status);
    }
}
