<?php
namespace App\Repositories;

use App\Config\Database;
use App\Middlewares\AuthMiddleware;
use PDO;

class ClienteRepository {
    private $db;
    private $empresaId;

    public function __construct() {
        $this->db = Database::getConnection();
        $auth = AuthMiddleware::authenticate();
        $this->empresaId = $auth->empresa_id ?? 1;
    }

    public function buscarFiltrado($termino) {
        $sql = "SELECT * FROM clientes 
                WHERE empresa_id = :empresaId 
                AND estado = 'ACTIVO' 
                AND (rut LIKE :term OR razon_social LIKE :term)
                ORDER BY razon_social ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':empresaId' => $this->empresaId,
            ':term' => "%$termino%"
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($data) {
        $sql = "INSERT INTO clientes (rut, razon_social, contacto_nombre, contacto_email, contacto_telefono, direccion, email, empresa_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['rut'], 
            $data['razon_social'], 
            $data['contacto_nombre'], 
            $data['contacto_email'], 
            $data['contacto_telefono'],
            $data['direccion'], 
            $data['email'], 
            $this->empresaId
        ]);
        return $this->db->lastInsertId();
    }

    public function update($id, $data) {
        $sql = "UPDATE clientes SET 
                rut = ?, 
                razon_social = ?, 
                contacto_nombre = ?, 
                contacto_email = ?, 
                contacto_telefono = ?, 
                direccion = ?, 
                email = ?
                WHERE id = ? AND empresa_id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['rut'], 
            $data['razon_social'], 
            $data['contacto_nombre'], 
            $data['contacto_email'], 
            $data['contacto_telefono'], 
            $data['direccion'], 
            $data['email'], 
            $id, 
            $this->empresaId
        ]);
    }

    public function softDelete($id) {
        $sql = "UPDATE clientes SET estado = 'BLOQUEADO' WHERE id = ? AND empresa_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id, $this->empresaId]);
    }
}