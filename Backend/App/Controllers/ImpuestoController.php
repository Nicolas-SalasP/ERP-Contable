<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\ImpuestoService;
use Exception;

class ImpuestoController {
    private $service;

    public function __construct() {
        $this->service = new ImpuestoService();
    }

    private function jsonResponse($data, int $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public function simularCierre($mes, $anio) {
        try {
            $data = $this->service->simularCierreMensual($mes, $anio);
            $this->jsonResponse(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'mensaje' => $e->getMessage()], 400);
        }
    }

    public function ejecutarCierre() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!isset($input['mes']) || !isset($input['anio'])) throw new Exception("Mes y Año son requeridos");
            
            $res = $this->service->ejecutarCierreMensual($input['mes'], $input['anio']);
            $this->jsonResponse($res);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'mensaje' => $e->getMessage()], 400);
        }
    }
}