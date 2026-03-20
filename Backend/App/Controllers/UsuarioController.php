<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\UsuarioService;
use Exception;

class UsuarioController
{
    private UsuarioService $service;

    public function __construct()
    {
        $this->service = new UsuarioService();
    }

    private function jsonResponse($data, int $code = 200)
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    private function getJsonInput(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?: [];
    }

    public function listar()
    {
        try {
            $usuarios = $this->service->obtenerUsuarios();
            $this->jsonResponse(['success' => true, 'data' => $usuarios]);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function roles()
    {
        try {
            $roles = $this->service->obtenerRolesDisponibles();
            $this->jsonResponse(['success' => true, 'data' => $roles]);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function invitar()
    {
        try {
            $data = $this->getJsonInput();
            $resultado = $this->service->invitar($data);
            $this->jsonResponse($resultado, 201);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function actualizarRol(int $id)
    {
        try {
            $data = $this->getJsonInput();
            $resultado = $this->service->actualizarRol($id, $data);
            $this->jsonResponse($resultado);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function eliminar(int $id)
    {
        try {
            $resultado = $this->service->eliminarAcceso($id);
            $this->jsonResponse($resultado);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
}