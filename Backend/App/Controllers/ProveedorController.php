<?php
namespace App\Controllers;

use App\Config\Database;
use PDO;

class ProveedorController {
    
    public function getByCodigo($codigo) {
        $db = Database::getConnection();
        
        // Buscamos el proveedor por su código interno único
        $stmt = $db->prepare("SELECT * FROM proveedores WHERE codigo_interno = ? LIMIT 1");
        $stmt->execute([$codigo]);
        $proveedor = $stmt->fetch();

        if ($proveedor) {
            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $proveedor['id'],
                    'rut' => $proveedor['rut'],
                    'razonSocial' => $proveedor['razon_social'],
                    'pais' => $proveedor['pais_iso'] == 'CL' ? 'Chile' : ($proveedor['pais_iso'] == 'DK' ? 'Dinamarca' : 'Extranjero'),
                    'moneda' => $proveedor['moneda_defecto'],
                    'ubicacion' => $proveedor['comuna'] ? $proveedor['comuna'] . ', ' . $proveedor['region'] : 'Internacional',
                    'contacto' => $proveedor['nombre_contacto']
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Proveedor no encontrado']);
        }
    }
}