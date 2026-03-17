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
                    u.rol_id, 
                    u.estado_suscripcion_id, 
                    u.fecha_fin_suscripcion, 
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

    public function buscarUsuarioPorId(int $id): ?array
    {
        $sql = "SELECT 
                    u.id, u.nombre, u.email, u.rol_id, u.empresa_id, e.razon_social as nombre_empresa
                FROM usuarios u
                LEFT JOIN empresas e ON u.empresa_id = e.id
                WHERE u.id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    public function crearUsuarioEspejo(array $datos): int
    {
        $sql = "INSERT INTO usuarios (nombre, email, rut, rol_id, empresa_id) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $datos['nombre'],
            $datos['email'],
            $datos['rut'],
            $datos['rol_id'] ?? 2,
            $datos['empresa_id'] ?? 1
        ]);
        return (int) $this->db->lastInsertId();
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