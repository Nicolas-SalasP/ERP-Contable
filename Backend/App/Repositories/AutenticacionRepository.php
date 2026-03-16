<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use PDO;

class AutenticacionRepository {
    
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }
    
    public function buscarUsuarioPorEmail(string $email): ?array {
        $sql = "SELECT 
                    u.id, 
                    u.password, 
                    u.nombre, 
                    u.rol_id, 
                    u.estado_suscripcion_id, 
                    u.fecha_fin_suscripcion, 
                    u.empresa_id, 
                    u.intentos_fallidos, 
                    u.nivel_bloqueo, 
                    u.bloqueado_hasta,
                    u.version_token,
                    e.razon_social as nombre_empresa
                FROM usuarios u
                INNER JOIN empresas e ON u.empresa_id = e.id
                WHERE u.email = ? 
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$email]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        return $resultado ?: null;
    }

    public function guardarTokenRecuperacion(string $email, string $token): void 
    {
        $sql = "UPDATE usuarios SET reset_token = ?, reset_expires_at = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE email = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$token, $email]);
    }

    public function verificarTokenValido(string $email, string $token): bool 
    {
        $sql = "SELECT id FROM usuarios WHERE email = ? AND reset_token = ? AND reset_expires_at > NOW()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$email, $token]);
        return (bool)$stmt->fetch();
    }

    public function actualizarPasswordYLimpiarToken(string $email, string $nuevoHash): void 
    {
        $sql = "UPDATE usuarios SET password = ?, reset_token = NULL, reset_expires_at = NULL WHERE email = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$nuevoHash, $email]);
    }

    public function registrarIntentoFallido(int $usuarioId, int $intentos, int $nivel, ?string $bloqueadoHasta): void 
    {
        $sql = "UPDATE usuarios SET intentos_fallidos = ?, nivel_bloqueo = ?, bloqueado_hasta = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$intentos, $nivel, $bloqueadoHasta, $usuarioId]);
    }

    public function limpiarIntentosFallidos(int $usuarioId): void 
    {
        $sql = "UPDATE usuarios SET intentos_fallidos = 0, nivel_bloqueo = 0, bloqueado_hasta = NULL WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$usuarioId]);
    }

    public function rotarVersionToken(int $usuarioId): int 
    {
        $sql = "UPDATE usuarios SET version_token = COALESCE(version_token, 0) + 1 WHERE id = ?";
        $this->db->prepare($sql)->execute([$usuarioId]);
        
        $stmt = $this->db->prepare("SELECT version_token FROM usuarios WHERE id = ?");
        $stmt->execute([$usuarioId]);
        return (int)$stmt->fetchColumn();
    }
}