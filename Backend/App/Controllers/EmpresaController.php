<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\EmpresaService;
use Exception;

class EmpresaController
{
    private EmpresaService $service;

    public function __construct()
    {
        $this->service = new EmpresaService();
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
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            return [];
        }
        return $input;
    }

    // =========================================================================
    // ENDPOINTS PÚBLICOS (Registro / Onboarding)
    // =========================================================================

    public function registrar()
    {
        try {
            $data = $this->getJsonInput();
            if (empty($data['empresa_rut']) || empty($data['admin_email']) || empty($data['admin_password'])) {
                return $this->jsonResponse(['error' => 'Faltan datos obligatorios para el registro.'], 400);
            }
            $resultado = $this->service->registrarEmpresaCompleta($data);
            return $this->jsonResponse($resultado, 201);

        } catch (Exception $e) {
            return $this->jsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $t) {
            error_log($t->getMessage());
            return $this->jsonResponse(['error' => 'Error interno del servidor.'], 500);
        }
    }

    // =========================================================================
    // ENDPOINTS PRIVADOS (Requieren Token/Login previo)
    // =========================================================================

    public function verPerfil()
    {
        try {
            $perfil = $this->service->obtenerPerfil();
            
            return $this->jsonResponse(['success' => true, 'data' => $perfil]);

        } catch (Exception $e) {
            return $this->jsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    public function actualizarPerfil()
    {
        try {
            $data = $this->getJsonInput();
            $exito = $this->service->actualizarDatos($data);

            if ($exito) {
                return $this->jsonResponse(['success' => true, 'mensaje' => 'Datos actualizados.']);
            } else {
                return $this->jsonResponse(['error' => 'No se realizaron cambios.'], 200);
            }

        } catch (Exception $e) {
            return $this->jsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    public function subirLogo()
    {
        try {
            if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("No se ha enviado ningún archivo válido.");
            }

            $file = $_FILES['logo'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            if (!in_array($ext, $allowed)) {
                throw new Exception("Formato no permitido. Solo JPG, PNG o WEBP.");
            }
            $uploadDir = __DIR__ . '/../../public/uploads/logos/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $fileName = 'logo_' . uniqid() . '.' . $ext;
            $targetPath = $uploadDir . $fileName;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $relativePath = 'uploads/logos/' . $fileName;
                $this->service->actualizarLogo($relativePath);
                $fullUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/ERP-Contable/Backend/Public/' . $relativePath;
                
                $this->jsonResponse(['success' => true, 'logo_url' => $fullUrl]);
            } else {
                throw new Exception("Error al mover el archivo al servidor.");
            }

        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    public function guardarBanco()
    {
        try {
            $data = $this->getJsonInput();
            if (empty($data['banco']) || empty($data['numero_cuenta'])) {
                throw new Exception("El nombre del banco y el número de cuenta son obligatorios.");
            }

            $this->service->agregarBanco($data);
            $this->jsonResponse(['success' => true, 'mensaje' => 'Cuenta agregada.']);

        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    public function eliminarBanco($id)
    {
        try {
            $this->service->eliminarCuenta($id);
            $this->jsonResponse(['success' => true, 'mensaje' => 'Cuenta eliminada.']);
        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    public function listarBancosDisponibles()
    {
        try {
            $bancos = $this->service->getListaBancos();
            $this->jsonResponse(['success' => true, 'data' => $bancos]);
        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        }
    }
}