<?php

namespace App\Domains\Sii\Support;

use App\Domains\Sii\Exceptions\DteIncompletoException;

/** Conversor UTF-8 <-> ISO-8859-1 con validacion estricta de roundtrip; el SII exige XML del DTE en ISO-8859-1, y esta clase lanza excepcion explicita ante caracteres no convertibles (emoji, kanji, etc.) en vez de sustituir con "?" o transliterar (que rompe la firma). */
class Iso88591Helper
{
    /**
     * Convierte UTF-8 a ISO-8859-1.
     *
     * @throws DteIncompletoException si la cadena contiene caracteres fuera del rango Latin-1.
     */
    public static function convertToIso(string $utf8): string
    {
        if ($utf8 === '') {
            return '';
        }

        $resultado = mb_convert_encoding($utf8, 'ISO-8859-1', 'UTF-8');

        // Roundtrip ISO -> UTF-8 debe devolver el original; si difiere, hubo caracteres no representables que mb_convert mapeo a "?".
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

    /** Trim + collapse de espacios + truncado opcional + validacion de convertibilidad a ISO-8859-1; IMPORTANTE: retorna el string en UTF-8 (no en ISO-8859-1) porque DOMDocument/libxml trabaja en UTF-8 y la conversion final la hace DOMDocument::saveXML() al serializar (bytes ISO-8859-1 directos en createTextNode rompen la generacion del XML); igual invoca convertToIso() internamente solo para validar, descartando el resultado. */
    public static function sanitize(string $utf8, ?int $maxLength = null): string
    {
        $colapsado = trim(preg_replace('/\s+/u', ' ', $utf8) ?? '');

        if ($maxLength !== null && mb_strlen($colapsado, 'UTF-8') > $maxLength) {
            $colapsado = mb_substr($colapsado, 0, $maxLength, 'UTF-8');
        }

        // Validacion de convertibilidad (lanza DteIncompletoException si hay chars no representables en ISO-8859-1, ej. emojis).
        self::convertToIso($colapsado);

        return $colapsado;
    }
}
