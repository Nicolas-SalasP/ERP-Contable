<?php
namespace App\Controllers;

use App\Services\ProveedorService;
use Exception;

class ProveedorController {
    
    private $service;

    public function __construct() {
        $this->service = new ProveedorService();
    }
    
    public function getByCodigo($codigo) {
        try {
            $proveedor = $this->service->buscarPorCodigo($codigo);

            if ($proveedor) {
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'id' => $proveedor['id'],
                        'codigo_interno' => $proveedor['codigo_interno'],
                        'rut' => $proveedor['rut'],
                        'razonSocial' => $proveedor['razon_social'],
                        'pais' => $this->formatPais($proveedor['pais_iso']),
                        'pais_iso' => $proveedor['pais_iso'],
                        'moneda' => $proveedor['moneda_defecto'],
                        'ubicacion' => ($proveedor['comuna'] ?? '') . ', ' . ($proveedor['region'] ?? ''),
                        'contacto' => $proveedor['nombre_contacto']
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Proveedor no encontrado']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function getAll() {
        try {
            $proveedores = $this->service->obtenerTodos();
            echo json_encode([
                'success' => true, 
                'count' => count($proveedores),
                'data' => $proveedores
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function create() {
        $input = json_decode(file_get_contents("php://input"), true);
        
        try {
            $resultado = $this->service->crearProveedor($input);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Proveedor creado exitosamente', 
                'id' => $resultado['id'],
                'codigo_generado' => $resultado['codigo']
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function formatPais($iso) {
        $paises = ['CL' => 'Chile', 'DK' => 'Dinamarca', 'US' => 'Estados Unidos', 'PE' => 'Perú'];
        return $paises[$iso] ?? 'Extranjero';
    }
}