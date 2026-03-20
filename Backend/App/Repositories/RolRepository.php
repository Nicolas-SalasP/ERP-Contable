<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use App\Middlewares\AuthMiddleware;
use PDO;
use Exception;

class RolRepository
{
    private PDO $db;
    private ?int $empresaId;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $auth = AuthMiddleware::authenticate();
        $this->empresaId = $auth->empresa_id ?? null;
    }

    public function listarRoles()
    {
        if (!$this->empresaId)
            return [];
        $stmt = $this->db->prepare("SELECT * FROM roles WHERE empresa_id IS NULL OR empresa_id = ? ORDER BY id ASC");
        $stmt->execute([$this->empresaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crearRol(string $nombre, string $descripcion)
    {
        $stmt = $this->db->prepare("INSERT INTO roles (empresa_id, nombre, descripcion) VALUES (?, ?, ?)");
        $stmt->execute([$this->empresaId, $nombre, $descripcion]);
        return (int) $this->db->lastInsertId();
    }

    public function actualizarRol(int $id, string $nombre, string $descripcion)
    {
        $stmt = $this->db->prepare("UPDATE roles SET nombre = ?, descripcion = ? WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$nombre, $descripcion, $id, $this->empresaId]);
        return $stmt->rowCount() > 0;
    }

    public function eliminarRol(int $id)
    {
        $stmtCheck = $this->db->prepare("SELECT COUNT(*) FROM usuarios WHERE rol_id = ? AND empresa_id = ?");
        $stmtCheck->execute([$id, $this->empresaId]);
        if ($stmtCheck->fetchColumn() > 0) {
            throw new Exception("No puedes eliminar este rol porque hay usuarios asignados a él.");
        }

        $stmt = $this->db->prepare("DELETE FROM roles WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$id, $this->empresaId]);
        return $stmt->rowCount() > 0;
    }

    public function listarTodosLosPermisos()
    {
        $stmt = $this->db->query("SELECT * FROM permisos ORDER BY modulo ASC, id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPermisosDeRol(int $rolId)
    {
        $stmt = $this->db->prepare("SELECT permiso_id FROM rol_permisos WHERE rol_id = ?");
        $stmt->execute([$rolId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function sincronizarPermisosDeRol(int $rolId, array $permisosIds)
    {
        $this->db->prepare("DELETE FROM rol_permisos WHERE rol_id = ?")->execute([$rolId]);
        if (!empty($permisosIds)) {
            $sql = "INSERT INTO rol_permisos (rol_id, permiso_id) VALUES (?, ?)";
            $stmt = $this->db->prepare($sql);
            foreach ($permisosIds as $permisoId) {
                $stmt->execute([$rolId, (int) $permisoId]);
            }
        }
    }
}