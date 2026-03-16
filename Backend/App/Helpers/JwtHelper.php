<?php
declare(strict_types=1);

namespace App\Helpers;

use Exception;

class JwtHelper {
    private static string $algorithm = 'sha512';

    private static function getSecretKey(): string {
        $secret = \App\Config\Env::get('JWT_SECRET');
        if (!$secret) {
            throw new Exception("CRITICAL ERROR: JWT_SECRET no está configurado en el archivo .env.");
        }
        return $secret;
    }

    public static function generate(array $data): string {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS512'], JSON_THROW_ON_ERROR);
        $payload = json_encode(array_merge($data, [
            'iat' => time(),
            'nbf' => time(),
            'exp' => time() + 28800
        ]), JSON_THROW_ON_ERROR);

        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode($payload);

        $signature = hash_hmac(self::$algorithm, $base64UrlHeader . "." . $base64UrlPayload, self::getSecretKey(), true);
        $base64UrlSignature = self::base64UrlEncode($signature);

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    public static function validate(string $token): ?object {
        $tokenParts = explode('.', $token);
        if (count($tokenParts) !== 3) return null;

        [$headerB64, $payloadB64, $signatureB64] = $tokenParts;

        $header = self::base64UrlDecode($headerB64);
        $payload = self::base64UrlDecode($payloadB64);
        $signatureProvided = self::base64UrlDecode($signatureB64);

        if (!$header || !$payload || !$signatureProvided) return null;

        $signatureExpected = hash_hmac(self::$algorithm, $headerB64 . "." . $payloadB64, self::getSecretKey(), true);

        if (!hash_equals($signatureExpected, $signatureProvided)) return null;

        try {
            $payloadObj = json_decode($payload, false, 512, JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            return null;
        }

        if (!isset($payloadObj->exp) || $payloadObj->exp < time()) return null;
        if (isset($payloadObj->nbf) && $payloadObj->nbf > time()) return null;

        return $payloadObj;
    }

    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}