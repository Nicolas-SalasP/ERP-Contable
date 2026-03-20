<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\RolService;
use Exception;

class RolController
{
    private RolService $service;

    public function __construct()
    {
        $this->service = new RolService();
    }

    private function jsonResponse($data, int $code = 200)
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    private function getJsonInput()
    {
        return json_decode(file_get_contents('php://input'), true) ?: [];
    }

    public function listar()
    {
        try {
            $this->jsonResponse(['success' => true, 'data' => $this->service->obtenerRoles()]);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function crear()
    {
        try {
            $this->jsonResponse($this->service->crear($this->getJsonInput()), 201);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function actualizar(int $id)
    {
        try {
            $this->jsonResponse($this->service->actualizar($id, $this->getJsonInput()));
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function eliminar(int $id)
    {
        try {
            $this->jsonResponse($this->service->eliminar($id));
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function permisos()
    {
        try {
            $this->jsonResponse(['success' => true, 'data' => $this->service->listarPermisos()]);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function permisosDeRol(int $id)
    {
        try {
            $this->jsonResponse(['success' => true, 'data' => $this->service->obtenerPermisosDeRol($id)]);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
}