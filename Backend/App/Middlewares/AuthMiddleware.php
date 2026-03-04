<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Helpers\JwtHelper;
use App\Config\Database;
use PDO;
use Exception;

class AuthMiddleware
{
    public static function authenticate(): object
    {
        $authHeader = self::getAuthorizationHeader();
        $token = null;

        if ($authHeader !== null && preg_match('/^Bearer\s(\S+)$/', $authHeader, $matches)) {
            $token = $matches[1];
        }
        else if (isset($_GET['token'])) {
            $token = trim((string)$_GET['token']);
        }
        if ($token === null || empty($token)) {
            self::denyAccess(401, 'AUTH_MISSING', 'Authorization token missing.');
        }
        
        $payload = JwtHelper::validate($token);

        if (!$payload || !isset($payload->id)) {
            self::denyAccess(401, 'TOKEN_INVALID', 'Invalid or expired token.');
        }

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT id, email, rol_id, estado_suscripcion_id, fecha_fin_suscripcion 
                FROM usuarios 
                WHERE id = ? LIMIT 1
            ");
            $stmt->execute([$payload->id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                self::denyAccess(401, 'USER_NOT_FOUND', 'User identity no longer exists.');
            }
            if ((int) $user['estado_suscripcion_id'] !== 1) {
                self::denyAccess(403, 'ACCOUNT_INACTIVE', 'Account is suspended or inactive.');
            }
            $expiryDate = $user['fecha_fin_suscripcion'];
            if ($expiryDate && $expiryDate < date('Y-m-d')) {
                self::denyAccess(403, 'SUBSCRIPTION_EXPIRED', 'Subscription plan has expired.');
            }
            $payload->rol_id = (int) $user['rol_id'];
            $payload->email = $user['email'];

            return $payload;

        } catch (Exception $e) {
            error_log("Auth Error: " . $e->getMessage());
            self::denyAccess(500, 'INTERNAL_ERROR', 'Service temporarily unavailable.');
        }
    }

    private static function getAuthorizationHeader(): ?string
    {
        $headers = null;
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            $requestHeaders = array_change_key_case($requestHeaders, CASE_LOWER);
            if (isset($requestHeaders['authorization'])) {
                $headers = trim($requestHeaders['authorization']);
            }
        }
        return $headers;
    }

    private static function denyAccess(int $httpCode, string $code, string $message): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        echo json_encode([
            'success' => false, 
            'error_code' => $code, 
            'message' => $message
        ]);
        exit;
    }
}