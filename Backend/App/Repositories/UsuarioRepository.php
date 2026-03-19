<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use App\Middlewares\AuthMiddleware;
use PDO;
use Exception;

class UsuarioRepository
{
    private PDO $db;
    private ?int $empresaId;

    public function __construct()
    {
        $this->db = Database::getConnection();
        try {
            $auth = AuthMiddleware::authenticate();
            $this->empresaId = $auth->empresa_id ?? null;
        } catch (Exception $e) {
            $this->empresaId = null;
        }
    }

    public function listarUsuariosEmpresa(): array
    {
        if (!$this->empresaId)
            return [];

        $sql = "SELECT 
                    id, nombre, email, rol_id, estado_suscripcion_id, created_at, ultimo_acceso
                FROM usuarios 
                WHERE empresa_id = ? 
                ORDER BY created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->empresaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerRoles(): array
    {
        try {
            $stmt = $this->db->query("SELECT id, nombre FROM roles ORDER BY id ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    public function invitarUsuario(string $email, int $rolId): void
    {
        if (!$this->empresaId)
            throw new Exception("No tienes una empresa asignada.");
        $stmt = $this->db->prepare("SELECT id, empresa_id FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $existe = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existe) {
            if ($existe['empresa_id'] === $this->empresaId) {
                throw new Exception("El usuario ya pertenece a tu empresa.");
            } elseif ($existe['empresa_id'] !== null) {
                throw new Exception("El usuario ya está registrado en otra empresa del sistema.");
            } else {
                $sql = "UPDATE usuarios SET empresa_id = ?, rol_id = ?, nombre = 'Usuario Invitado' WHERE email = ?";
                $this->db->prepare($sql)->execute([$this->empresaId, $rolId, $email]);
            }
        } else {
            $sql = "INSERT INTO usuarios (email, nombre, rol_id, empresa_id, estado_suscripcion_id, created_at) 
                    VALUES (?, 'Usuario Invitado', ?, ?, 1, NOW())";
            $this->db->prepare($sql)->execute([$email, $rolId, $this->empresaId]);
        }
    }

    public function cambiarRolUsuario(int $usuarioId, int $nuevoRolId): bool
    {
        $sql = "UPDATE usuarios SET rol_id = ? WHERE id = ? AND empresa_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$nuevoRolId, $usuarioId, $this->empresaId]);
    }

    public function desvincularUsuario(int $usuarioId): bool
    {
        $sql = "UPDATE usuarios SET empresa_id = NULL, rol_id = 1 WHERE id = ? AND empresa_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$usuarioId, $this->empresaId]);
    }
}