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

    public function sincronizarUsuarioEspejo(array $datosAtlas): array
    {
        $estado_id = (isset($datosAtlas['is_active']) && $datosAtlas['is_active'] === true) ? 1 : 2;
        $usuario = $this->buscarUsuarioPorEmail($datosAtlas['email']);

        if ($usuario) {
            $sql = "UPDATE usuarios SET nombre = ?, rut = ?, estado_suscripcion_id = ?, ultimo_acceso = NOW() WHERE email = ?";
            $this->db->prepare($sql)->execute([
                $datosAtlas['name'],
                $datosAtlas['rut'] ?? null,
                $estado_id,
                $datosAtlas['email']
            ]);
            return $this->buscarUsuarioPorEmail($datosAtlas['email']);
        } else {
            $sql = "INSERT INTO usuarios (nombre, email, rut, rol_id, empresa_id, estado_suscripcion_id, ultimo_acceso) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $datosAtlas['name'],
                $datosAtlas['email'],
                $datosAtlas['rut'] ?? null,
                1,
                null,
                $estado_id
            ]);
            return $this->buscarUsuarioPorId((int) $this->db->lastInsertId());
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

    public function obtenerPermisosPorRol(int $rolId): array
    {
        if ($rolId === 1) {
            $stmt = $this->db->query("SELECT codigo FROM permisos");
            return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        }
        $sql = "SELECT p.codigo 
                FROM permisos p
                INNER JOIN rol_permisos rp ON p.id = rp.permiso_id
                WHERE rp.rol_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$rolId]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }
}