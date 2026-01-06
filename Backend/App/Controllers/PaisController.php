<?php
namespace App\Controllers;

use App\Services\PaisService;
use Exception;

class PaisController {
    private $service;

    public function __construct() {
        $this->service = new PaisService();
    }

    public function index() {
        try {
            $paises = $this->service->listarPaisesActivos();
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $paises
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}