<?php
namespace App\Repositories;

use App\Config\Database;
use PDO;

class CuentaBancariaRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function getAllByProveedor($proveedorId) {
        $sql = "SELECT * FROM cuentas_bancarias_proveedores WHERE proveedor_id = ? AND activo = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$proveedorId]);
        return $stmt->fetchAll();
    }

    public function create(array $data) {
        $sql = "INSERT INTO cuentas_bancarias_proveedores (
                    proveedor_id, banco, numero_cuenta, tipo_cuenta, pais_iso, swift_bic, activo
                ) VALUES (?, ?, ?, ?, ?, ?, 1)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['proveedorId'],
            $data['banco'],
            $data['numeroCuenta'],
            $data['tipoCuenta'] ?? 'Vista',
            $data['paisIso'] ?? 'CL',
            $data['swift'] ?? null
        ]);
        
        return $this->db->lastInsertId();
    }

    public function delete($id) {
        $sql = "UPDATE cuentas_bancarias_proveedores SET activo = 0 WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }
}