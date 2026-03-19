<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\EmpresaService;
use App\Middlewares\AuthMiddleware;
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

    public function onboarding()
    {
        try {
            $payload = AuthMiddleware::authenticate();
            $usuarioId = (int) $payload->id;
            $data = $this->getJsonInput();
            $resultado = $this->service->procesarOnboarding($usuarioId, $data);
            return $this->jsonResponse($resultado, 201);

        } catch (Exception $e) {
            return $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        } catch (\Throwable $t) {
            error_log($t->getMessage());
            return $this->jsonResponse(['success' => false, 'error' => 'Error interno del servidor.'], 500);
        }
    }

    public function verificarRut()
    {
        try {
            $rut = $_GET['rut'] ?? '';
            if (empty($rut)) {
                throw new Exception("RUT no proporcionado.");
            }

            $existe = $this->service->verificarExistenciaRut($rut);

            return $this->jsonResponse([
                'success' => true,
                'existe' => $existe
            ]);
        } catch (Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
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
                throw new Exception("No se recibió ningún archivo o hubo un error en la transmisión.");
            }

            $archivo = $_FILES['logo'];
            $maxSize = 2 * 1024 * 1024;
            if ($archivo['size'] > $maxSize) {
                throw new Exception("El archivo excede el límite permitido de 2MB.");
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeReal = finfo_file($finfo, $archivo['tmp_name']);
            finfo_close($finfo);

            $mimesPermitidos = ['image/jpeg', 'image/png', 'image/webp'];
            if (!in_array($mimeReal, $mimesPermitidos)) {
                throw new Exception("Tipo de archivo no permitido o corrupto. Solo JPG, PNG y WEBP.");
            }

            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            $nombreSeguro = 'logo_' . bin2hex(random_bytes(8)) . '.' . $extension;
            $directorioDestino = dirname(__DIR__, 2) . '/Public/uploads/logos/';
            if (!is_dir($directorioDestino)) {
                mkdir($directorioDestino, 0755, true);
            }

            $rutaFinal = $directorioDestino . $nombreSeguro;

            if (move_uploaded_file($archivo['tmp_name'], $rutaFinal)) {
                // Aquí iría tu lógica para guardar $nombreSeguro en la base de datos de la empresa
                // $this->service->actualizarLogoEmpresa($nombreSeguro);

                $this->jsonResponse([
                    'success' => true,
                    'mensaje' => 'Logo subido y procesado con máxima seguridad.',
                    'archivo' => $nombreSeguro
                ]);
            } else {
                throw new Exception("Error interno al mover el archivo al almacén seguro.");
            }

        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'mensaje' => $e->getMessage()], 400);
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

    public function listarCentrosCosto()
    {
        try {
            $centros = $this->service->listarCentrosCosto();
            $this->jsonResponse(['success' => true, 'data' => $centros]);
        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    public function guardarCentroCosto()
    {
        try {
            $data = $this->getJsonInput();
            $this->service->agregarCentroCosto($data);
            $this->jsonResponse(['success' => true, 'mensaje' => 'Centro de costo agregado.']);
        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    public function eliminarCentroCosto($id)
    {
        try {
            $this->service->eliminarCentroCosto($id);
            $this->jsonResponse(['success' => true, 'mensaje' => 'Centro de costo eliminado.']);
        } catch (Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 400);
        }
    }
}