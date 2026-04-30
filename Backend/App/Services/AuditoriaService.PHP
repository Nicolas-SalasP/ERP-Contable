<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Helpers\JwtHelper;

class AuditoriaService {

    /**
     * Registra un evento en la bitácora del sistema de forma silenciosa.
     */
    public static function registrar(
        string $accion, 
        ?string $tabla = null, 
        ?int $registroId = null, 
        ?array $datosAnteriores = null, 
        ?array $datosNuevos = null
    ): void {
        try {
            // 1. Intentar identificar al usuario y empresa desde el Token
            $usuarioId = null;
            $empresaId = 1; // Default a empresa sistema

            try {
                // Obtenemos headers directamente
                $headers = getallheaders();
                // Normalización de headers (Nginx/Apache)
                $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
                
                if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                    $token = $matches[1];
                    // Validamos token para extraer datos
                    $payload = JwtHelper::validate($token); 
                    if ($payload) {
                        $usuarioId = $payload->id;
                        $empresaId = $payload->empresa_id ?? 1;
                    }
                }
            } catch (\Exception $e) {
                // Si no hay token válido (ej: login fallido), seguimos con nulos
            }

            // 2. Datos Técnicos
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';

            // 3. Serializar JSON con soporte Unicode
            $anteriorJson = $datosAnteriores ? json_encode($datosAnteriores, JSON_UNESCAPED_UNICODE) : null;
            $nuevoJson = $datosNuevos ? json_encode($datosNuevos, JSON_UNESCAPED_UNICODE) : null;

            // 4. Insertar en BD
            $db = Database::getConnection();
            $sql = "INSERT INTO bitacora_sistema 
                    (empresa_id, usuario_id, accion, tabla_afectada, registro_id, detalle_anterior, detalle_nuevo, ip_origen, user_agent, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $empresaId,
                $usuarioId,
                $accion,
                $tabla,
                $registroId,
                $anteriorJson,
                $nuevoJson,
                $ip,
                $userAgent
            ]);

        } catch (\Exception $e) {
            // SILENCIO ABSOLUTO: La auditoría no debe romper el sistema si falla
            error_log("FALLO AUDITORIA: " . $e->getMessage());
        }
    }
}