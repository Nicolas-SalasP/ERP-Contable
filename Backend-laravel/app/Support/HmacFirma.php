<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Psr\Http\Message\RequestInterface;

/**
 * Firma HMAC-SHA256 para autenticación inter-servicio (ERP <-> tenri.cl).
 *
 * Mensaje firmado: "{timestamp}.{nonce}.{sha256(body)}". El secreto es la llave
 * compartida ya existente por dirección (no se distribuye un secreto nuevo).
 * Protege contra replay por ventana de tiempo + nonce de un solo uso, y compara
 * en tiempo constante (hash_equals).
 */
final class HmacFirma
{
    /** Ventana de validez del timestamp (segundos); tolera desfase de reloj. */
    public const VENTANA_SEGUNDOS = 300;

    /** Headers que adjunta el emisor; el verificador los recomputa. */
    public static function headers(string $secreto, string $cuerpo): array
    {
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));

        return [
            'X-Timestamp' => $timestamp,
            'X-Nonce' => $nonce,
            'X-Signature' => self::calcular($secreto, $timestamp, $nonce, $cuerpo),
        ];
    }

    /** Firma una request saliente PSR-7 (para usar en withRequestMiddleware). */
    public static function firmarPsr(RequestInterface $request, string $secreto): RequestInterface
    {
        foreach (self::headers($secreto, (string) $request->getBody()) as $nombre => $valor) {
            $request = $request->withHeader($nombre, $valor);
        }

        return $request;
    }

    /** Verifica una request entrante. Devuelve true solo si la firma es válida y fresca. */
    public static function verifica(string $secreto, Request $request): bool
    {
        $firma = (string) $request->header('X-Signature', '');
        $timestamp = (string) $request->header('X-Timestamp', '');
        $nonce = (string) $request->header('X-Nonce', '');

        if ($firma === '' || $timestamp === '' || $nonce === '' || !ctype_digit($timestamp)) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > self::VENTANA_SEGUNDOS) {
            return false;
        }

        $esperada = self::calcular($secreto, $timestamp, $nonce, (string) $request->getContent());
        if (!hash_equals($esperada, $firma)) {
            return false;
        }

        // Anti-replay: un nonce válido se acepta una sola vez dentro de la ventana.
        return Cache::add('hmac_nonce_' . hash('sha256', $secreto . ':' . $nonce), true, self::VENTANA_SEGUNDOS);
    }

    private static function calcular(string $secreto, string $timestamp, string $nonce, string $cuerpo): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $nonce . '.' . hash('sha256', $cuerpo), $secreto);
    }
}
