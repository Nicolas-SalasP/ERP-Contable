<?php

namespace App\Domains\Rrhh\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Excepción de negocio del dominio RRHH. Lleva un status HTTP y se renderiza
 * como JSON estable para el frontend (mismo patrón que PeriodoCerradoException).
 *
 *   - 404: recurso no encontrado o de otra empresa (no se filtra existencia).
 *   - 422: violación de regla de negocio (validación de dominio).
 */
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
