<?php
namespace App\Controllers;

use App\Services\AccountingService;
use App\Repositories\FacturaRepository; // Necesario para acceder directo
use Exception;

class FacturaController {
    private $service;
    private $repository;

    public function __construct() {
        $this->service = new AccountingService();
        $this->repository = new FacturaRepository();
    }
    
    public function create() {
        $input = file_get_contents("php://input");
        $data = json_decode($input, true);
        
        if (!$data) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'JSON inválido']);
            return;
        }

        try {
            $resultado = $this->service->registrarFacturaCompra($data);

            http_response_code(201);
            echo json_encode([
                'success' => true, 
                'id' => $resultado['id'],
                'codigo_sistema' => $resultado['codigo'],
                'message' => 'Documento contabilizado correctamente'
            ]);

        } catch (Exception $e) {
            if ($e->getMessage() === "DUPLICATE_INVOICE") {
                http_response_code(409);
                echo json_encode([
                    'success' => false, 
                    'error_code' => 'DUPLICATE',
                    'message' => 'El número de factura ya existe.'
                ]);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        }
    }

    // NUEVO: Endpoint ligero para validar existencia antes de guardar
    public function checkExistence() {
        // Obtenemos parámetros de la URL (GET)
        $proveedorId = $_GET['proveedor_id'] ?? null;
        $numeroFactura = $_GET['numero_factura'] ?? null;

        if (!$proveedorId || !$numeroFactura) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Faltan parámetros']);
            return;
        }

        // Usamos el repositorio directamente para una consulta rápida de lectura
        $existe = $this->repository->existeFactura($proveedorId, $numeroFactura);

        echo json_encode([
            'success' => true,
            'exists' => (bool)$existe
        ]);
    }

    public function anular() {
        $input = json_decode(file_get_contents("php://input"), true);
        
        if (empty($input['codigo']) || empty($input['motivo'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Falta código o motivo']);
            return;
        }

        try {
            $resultado = $this->service->anularDocumento($input['codigo'], $input['motivo']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Documento reversado correctamente',
                'nuevo_codigo_reverso' => $resultado['codigo']
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}