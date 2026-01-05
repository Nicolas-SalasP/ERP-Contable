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
}