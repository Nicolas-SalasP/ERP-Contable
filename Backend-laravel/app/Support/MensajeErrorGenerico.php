<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Throwable;

/**
 * Traduce excepciones a mensajes seguros para el cliente HTTP.
 *
 * QueryException expone SQL crudo (tabla, columnas, a veces valores) en getMessage();
 * nunca debe reenviarse tal cual a una respuesta JSON. Cualquier otra excepción se
 * asume ya curada por quien la lanzó (reglas de negocio) y su mensaje pasa sin cambios.
 */
class MensajeErrorGenerico
{
    public static function desde(Throwable $e): string
    {
        if ($e instanceof QueryException) {
            return self::desdeSqlState($e);
        }

        $mensaje = $e->getMessage();

        return $mensaje !== '' ? $mensaje : 'Ocurrió un error al procesar la solicitud.';
    }

    private static function desdeSqlState(QueryException $e): string
    {
        $sqlState = $e->errorInfo[0] ?? null;

        return match ($sqlState) {
            '23000' => 'Ya existe un registro con esos datos, o está relacionado con otra información del sistema.',
            default => 'Ocurrió un error al procesar la solicitud en la base de datos. Si el problema persiste, contacta a soporte.',
        };
    }
}
