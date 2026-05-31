<?php

namespace App\Domains\Sii\Support;

use App\Domains\Sii\Exceptions\DteIncompletoException;

/**
 * Conversor UTF-8 <-> ISO-8859-1 con validacion estricta de roundtrip.
 *
 * El SII exige que el XML del DTE este codificado en ISO-8859-1 (no UTF-8).
 * Esta clase asegura que los strings que vienen del modelo (UTF-8) puedan
 * representarse 100% en Latin-1; si encuentra cualquier caracter no
 * convertible (emoji, kanji, etc.) lanza una excepcion explicita en lugar
 * de sustituir silenciosamente con "?" o transliterar (que rompe la firma).
 */
class Iso88591Helper
{
    /**
     * Convierte UTF-8 a ISO-8859-1.
     *
     * @throws DteIncompletoException si la cadena contiene caracteres
     *         fuera del rango Latin-1.
     */
    public static function convertToIso(string $utf8): string
    {
        if ($utf8 === '') {
            return '';
        }

        $resultado = mb_convert_encoding($utf8, 'ISO-8859-1', 'UTF-8');

        // Roundtrip ISO -> UTF-8 debe devolver el original.
        // Si difiere, hubo caracteres no representables que mb_convert mapeo a "?".
        $roundTrip = mb_convert_encoding($resultado, 'UTF-8', 'ISO-8859-1');
        if ($roundTrip !== $utf8) {
            throw DteIncompletoException::caracterNoConvertible(
                'string',
                mb_substr($utf8, 0, 80, 'UTF-8')
            );
        }

        return $resultado;
    }

    public static function convertToUtf8(string $iso): string
    {
        return mb_convert_encoding($iso, 'UTF-8', 'ISO-8859-1');
    }

    /**
     * Trim + collapse de espacios + truncado opcional + VALIDACION de
     * convertibilidad a ISO-8859-1.
     *
     * IMPORTANTE: retorna el string en UTF-8 (NO en ISO-8859-1). Esto es
     * porque DOMDocument/libxml trabaja internamente en UTF-8; la conversion
     * final a ISO-8859-1 la hace DOMDocument::saveXML() al serializar.
     * Pasarle bytes ISO-8859-1 directos a createTextNode causa secuencias
     * UTF-8 mal formadas y rompe la generacion del XML.
     *
     * El helper SI invoca convertToIso() internamente para validar que el
     * string es 100% representable en Latin-1 (y lanza excepcion si no),
     * pero descarta el resultado.
     */
    public static function sanitize(string $utf8, ?int $maxLength = null): string
    {
        $colapsado = trim(preg_replace('/\s+/u', ' ', $utf8) ?? '');

        if ($maxLength !== null && mb_strlen($colapsado, 'UTF-8') > $maxLength) {
            $colapsado = mb_substr($colapsado, 0, $maxLength, 'UTF-8');
        }

        // Validacion de convertibilidad (lanza DteIncompletoException si hay
        // chars no representables en ISO-8859-1, ej. emojis).
        self::convertToIso($colapsado);

        return $colapsado;
    }
}
