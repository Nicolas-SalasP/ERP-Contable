<?php
namespace App\Controllers;

use App\Config\Database;
use PDO;

class CuentaBancariaController {
    
    public function getByProveedor($proveedorId) {
        $db = Database::getConnection();
        
        $sql = "SELECT * FROM cuentas_bancarias_proveedores WHERE proveedor_id = ? AND activo = 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$proveedorId]);
        
        $cuentas = $stmt->fetchAll();
        echo json_encode([
            'success' => true, 
            'count' => count($cuentas),
            'data' => $cuentas
        ]);
    }
}