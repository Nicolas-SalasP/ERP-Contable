<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use PDO;

class AutenticacionRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function buscarUsuarioPorEmail(string $email): ?array
    {
        $sql = "SELECT 
                    u.id, 
                    u.nombre, 
                    u.email,
                    u.rut,
                    u.rol_id, 
                    u.estado_suscripcion_id, 
                    u.empresa_id, 
                    u.version_token,
                    e.razon_social as nombre_empresa
                FROM usuarios u
                LEFT JOIN empresas e ON u.empresa_id = e.id
                WHERE u.email = ? 
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$email]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $resultado ?: null;
    }

public function sincronizarUsuarioEspejo(array $datosAtlas): void 
    {
        $estado_id = (isset($datosAtlas['is_active']) && $datosAtlas['is_active'] === true) ? 1 : 2;
        $sql = "SELECT id FROM usuarios WHERE email = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$datosAtlas['email']]);
        $existe = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existe) {
            $sqlUpdate = "UPDATE usuarios SET nombre = ?, rut = ?, estado_suscripcion_id = ? WHERE email = ?";
            $stmtUpdate = $this->db->prepare($sqlUpdate);
            $stmtUpdate->execute([
                $datosAtlas['name'],
                $datosAtlas['rut'] ?? null,
                $estado_id,
                $datosAtlas['email']
            ]);
        } else {
            $sqlInsert = "INSERT INTO usuarios (nombre, email, rut, rol_id, empresa_id, estado_suscripcion_id) 
                          VALUES (?, ?, ?, ?, ?, ?)";
            $stmtInsert = $this->db->prepare($sqlInsert);
            
            $stmtInsert->execute([
                $datosAtlas['name'],
                $datosAtlas['email'],
                $datosAtlas['rut'] ?? null,
                1,
                null, 
                $estado_id
            ]);
        }
    }

    public function buscarUsuarioPorId(int $id): ?array
    {
        $sql = "SELECT 
                    u.id, 
                    u.nombre, 
                    u.email,
                    u.rut,
                    u.rol_id, 
                    u.estado_suscripcion_id, 
                    u.empresa_id, 
                    u.version_token,
                    e.razon_social as nombre_empresa
                FROM usuarios u
                LEFT JOIN empresas e ON u.empresa_id = e.id
                WHERE u.id = ? 
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $resultado ?: null;
    }

    public function rotarVersionToken(int $usuarioId): int
    {
        $sql = "UPDATE usuarios SET version_token = version_token + 1 WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$usuarioId]);

        $sqlSelect = "SELECT version_token FROM usuarios WHERE id = ?";
        $stmtSelect = $this->db->prepare($sqlSelect);
        $stmtSelect->execute([$usuarioId]);
        return (int) $stmtSelect->fetchColumn();
    }
}