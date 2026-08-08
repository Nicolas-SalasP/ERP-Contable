<?php

namespace App\Domains\Integraciones\Exceptions;

use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class VentaIntegracionException extends RuntimeException
{
    public function __construct(string $message, private readonly int $status = 409, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /** Perdio la carrera del insert unique(empresa_id, clave) de integracion_venta_idempotencias: el caller debe reintentar el mismo POST con el mismo Idempotency-Key para recibir la respuesta ya persistida por quien gano. */
    public static function idempotenciaEnCarrera(QueryException $previous): self
    {
        return new self(
            'Otra solicitud con la misma Idempotency-Key ya está siendo procesada. Reintente la misma solicitud para obtener el resultado.',
            409,
            $previous
        );
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], $this->status);
    }
}
