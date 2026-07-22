<?php

namespace App\Domains\Integraciones\Support;

/**
 * Formato del token: tnri_<prefijo>_<secreto>. El prefijo es publico (indexa la key sin
 * exponer el secreto); el secreto solo se conoce en claro al momento de emitir/rotar, en BD
 * solo vive su hash sha256.
 */
class TokenApiKey
{
    private const PREFIJO_LARGO = 12;
    private const SECRETO_LARGO = 40;

    public static function generar(): array
    {
        $prefijo = bin2hex(random_bytes(self::PREFIJO_LARGO / 2));
        $secreto = bin2hex(random_bytes(self::SECRETO_LARGO / 2));

        return [
            'prefijo' => $prefijo,
            'secreto' => $secreto,
            'token' => "tnri_{$prefijo}_{$secreto}",
            'hash' => self::hash($secreto),
        ];
    }

    public static function hash(string $secreto): string
    {
        return hash('sha256', $secreto);
    }

    /** @return array{prefijo: string, secreto: string}|null */
    public static function parsear(string $token): ?array
    {
        $partes = explode('_', $token, 3);

        if (count($partes) !== 3 || $partes[0] !== 'tnri' || $partes[1] === '' || $partes[2] === '') {
            return null;
        }

        return ['prefijo' => $partes[1], 'secreto' => $partes[2]];
    }
}
