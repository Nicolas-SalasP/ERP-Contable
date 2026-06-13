<?php

namespace App\Domains\Comercial\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class ComercialException extends RuntimeException
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
