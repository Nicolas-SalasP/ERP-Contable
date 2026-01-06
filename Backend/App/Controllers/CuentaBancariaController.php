<?php
namespace App\Controllers;

use App\Services\CuentaBancariaService;
use Exception;

class CuentaBancariaController {
    
    private $service;

    public function __construct() {
        $this->service = new CuentaBancariaService();
    }
    
    // GET: Obtener cuentas de un proveedor
    public function getByProveedor($proveedorId) {
        try {
            $cuentas = $this->service->obtenerPorProveedor($proveedorId);
            echo json_encode([
                'success' => true, 
                'count' => count($cuentas),
                'data' => $cuentas
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // POST: Crear nueva cuenta
    public function create() {
        $input = json_decode(file_get_contents("php://input"), true);
        
        try {
            $id = $this->service->agregarCuenta($input);
            echo json_encode([
                'success' => true, 
                'message' => 'Cuenta bancaria agregada exitosamente', 
                'id' => $id
            ]);
        } catch (Exception $e) {
            http_response_code(400); 
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // DELETE: Eliminar cuenta
    public function delete($id) {
        try {
            $this->service->eliminarCuenta($id);
            echo json_encode([
                'success' => true, 
                'message' => 'Cuenta bancaria eliminada'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}