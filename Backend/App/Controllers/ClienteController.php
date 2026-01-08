<?php
namespace App\Controllers;

use App\Services\ClienteService;

class ClienteController {
    private $servicio;
    public function __construct() { $this->servicio = new ClienteService(); }

    private function responder($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public function index() {
        $filtro = $_GET['search'] ?? '';
        return $this->responder(['success' => true, 'data' => $this->servicio->listar($filtro)]);
    }

    public function store() {
        $data = json_decode(file_get_contents("php://input"), true);
        $id = $this->servicio->registrar($data);
        return $this->responder(['success' => true, 'id' => $id], 201);
    }

    public function update($id) {
        $data = json_decode(file_get_contents("php://input"), true);
        $res = $this->servicio->editar($id, $data);
        return $this->responder(['success' => $res]);
    }

    public function delete($id) {
        $res = $this->servicio->bloquear($id);
        return $this->responder(['success' => $res]);
    }
}