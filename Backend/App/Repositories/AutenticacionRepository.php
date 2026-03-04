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
}