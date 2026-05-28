<?php

namespace App\Domains\Sii\Support;

use InvalidArgumentException;

/**
 * Utilidades para manejo de RUT chileno.
 *
 * Las entradas se normalizan al formato canonico "12345678-K":
 * sin puntos, con guion, DV en mayuscula. La parte numerica admite
 * desde 1 digito (ej "1-9") y no impone limite superior (suficiente
 * para personas naturales y juridicas en Chile).
 */
class RutHelper
{
    /**
     * Normaliza un RUT a "12345678-K" (sin puntos, con guion, DV en mayuscula).
     *
     * @throws InvalidArgumentException si la entrada no contiene digitos suficientes.
     */
    public static function normalizar(string $rut): string
    {
        $rut = strtoupper(trim($rut));

        // Eliminar puntos, espacios, guiones y cualquier separador no alfanumerico.
        $limpio = preg_replace('/[^0-9K]/', '', $rut) ?? '';

        if (strlen($limpio) < 2) {
            throw new InvalidArgumentException(
                'RUT invalido: requiere al menos 1 digito mas el DV. Recibido: "' . $rut . '".'
            );
        }

        $dv     = substr($limpio, -1);
        $numero = substr($limpio, 0, -1);

        // El numero no puede contener una K (solo el DV).
        if (! ctype_digit($numero)) {
            throw new InvalidArgumentException(
                'RUT invalido: la parte numerica contiene caracteres no validos. Recibido: "' . $rut . '".'
            );
        }

        // Quitar ceros a la izquierda preservando al menos un digito.
        $numero = ltrim($numero, '0');
        if ($numero === '') {
            $numero = '0';
        }

        return $numero . '-' . $dv;
    }

    /**
     * Valida un RUT completo (formato libre): normaliza y compara DV calculado.
     *
     * Retorna false ante cualquier entrada no parseable, no lanza excepcion.
     */
    public static function validar(string $rut): bool
    {
        try {
            $normalizado = self::normalizar($rut);
        } catch (InvalidArgumentException $e) {
            return false;
        }

        $numero = self::extraerNumero($normalizado);
        $dv     = self::extraerDv($normalizado);

        // "0-0" pasa el algoritmo mod 11 pero no es un RUT real.
        if ($numero <= 0) {
            return false;
        }

        return self::calcularDv($numero) === $dv;
    }

    /**
     * Calcula el DV de un RUT (modulo 11 chileno).
     *
     * @return string "0"-"9" o "K"
     */
    public static function calcularDv(int $rutSinDv): string
    {
        $suma   = 0;
        $factor = 2;

        // Recorre los digitos de derecha a izquierda.
        $digitos = (string) $rutSinDv;
        for ($i = strlen($digitos) - 1; $i >= 0; $i--) {
            $suma  += ((int) $digitos[$i]) * $factor;
            $factor = ($factor === 7) ? 2 : $factor + 1;
        }

        $resto       = $suma % 11;
        $dvCalculado = 11 - $resto;

        if ($dvCalculado === 11) {
            return '0';
        }

        if ($dvCalculado === 10) {
            return 'K';
        }

        return (string) $dvCalculado;
    }

    /**
     * Retorna la parte numerica del RUT (sin DV).
     */
    public static function extraerNumero(string $rut): int
    {
        $normalizado = self::normalizar($rut);
        $numero      = substr($normalizado, 0, strrpos($normalizado, '-'));

        return (int) $numero;
    }

    /**
     * Retorna el DV del RUT en mayuscula.
     */
    public static function extraerDv(string $rut): string
    {
        $normalizado = self::normalizar($rut);

        return substr($normalizado, -1);
    }

    /**
     * Formatea un RUT para presentacion: "12.345.678-5" o "12345678-5".
     */
    public static function formatear(string $rut, bool $conPuntos = true): string
    {
        $numero = self::extraerNumero($rut);
        $dv     = self::extraerDv($rut);

        if ($conPuntos) {
            return number_format($numero, 0, ',', '.') . '-' . $dv;
        }

        return $numero . '-' . $dv;
    }
}
