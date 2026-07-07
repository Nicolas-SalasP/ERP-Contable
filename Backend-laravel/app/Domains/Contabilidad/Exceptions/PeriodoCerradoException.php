<?php

namespace App\Domains\Contabilidad\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Se lanza al crear, modificar, reclasificar o eliminar un hecho contable con fecha dentro de un periodo cerrado. */
class PeriodoCerradoException extends RuntimeException
{
    public function __construct(
        public readonly int $anio,
        public readonly int $mes,
        string $message = ''
    ) {
        $periodo = str_pad((string) $mes, 2, '0', STR_PAD_LEFT) . '/' . $anio;
        parent::__construct(
            $message !== ''
                ? $message
                : "El periodo {$periodo} esta cerrado. Solicita su reapertura para modificar movimientos de esa fecha."
        );
    }

    /** Laravel invoca esto automaticamente si la excepcion no es capturada: responde 409 con codigo estable para el frontend. */
    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'code' => 'PERIODO_CERRADO',
        ], 409);
    }
}
