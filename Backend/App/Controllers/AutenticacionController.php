<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AutenticacionService;
use Exception;

class AutenticacionController {

    private AutenticacionService $servicioAutenticacion;

    public function __construct() {
        $this->servicioAutenticacion = new AutenticacionService();
    }

    public function login(): void {
        $entradaCruda = file_get_contents("php://input");
        $datos = json_decode($entradaCruda, true);

        if (!is_array($datos) || empty($datos['email']) || empty($datos['password'])) {
            $this->responderConError(400, 'DATOS_INCOMPLETOS', 'El correo y la contraseña son obligatorios.');
        }

        try {
            $resultado = $this->servicioAutenticacion->iniciarSesion($datos['email'], $datos['password']);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'token' => $resultado['token'],
                'user' => $resultado['usuario']
            ]);

        } catch (Exception $e) {
            $codigoHttp = 401; 
            $mensaje = 'Credenciales incorrectas.';
            $codigoError = $e->getMessage();

            switch ($codigoError) {
                case 'CUENTA_SUSPENDIDA':
                    $codigoHttp = 403; 
                    $mensaje = 'Su cuenta está inactiva. Contacte a soporte.';
                    break;
                case 'PLAN_VENCIDO':
                    $codigoHttp = 403;
                    $mensaje = 'Su suscripción ha vencido. Por favor realice el pago.';
                    break;
                case 'CREDENCIALES_INCORRECTAS':
                case 'USUARIO_NO_ENCONTRADO':
                    $codigoHttp = 401;
                    $mensaje = 'Correo o contraseña incorrectos.';
                    break;
            }

            $this->responderConError($codigoHttp, $codigoError, $mensaje);
        }
    }

    private function responderConError(int $codigoHttp, string $codigoErrorInterno, string $mensajeLegible): void {
        http_response_code($codigoHttp);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error_code' => $codigoErrorInterno,
            'message' => $mensajeLegible
        ]);
        exit;
    }

    public function solicitarRecuperacion(): void 
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!empty($data['email'])) {
            try {
                $this->servicioAutenticacion->iniciarRecuperacion($data['email']);
            } catch (Exception $e) {
            }
        }
        echo json_encode(['success' => true, 'message' => 'Si el correo existe, se ha enviado un código.']);
    }

    public function restablecerPassword(): void 
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        try {
            if (empty($data['email']) || empty($data['codigo']) || empty($data['password'])) {
                throw new Exception("Faltan datos.");
            }

            $this->servicioAutenticacion->cambiarPasswordConToken($data['email'], $data['codigo'], $data['password']);
            
            echo json_encode(['success' => true, 'message' => 'Contraseña actualizada correctamente.']);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}