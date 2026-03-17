<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AutenticacionService;
use App\Middlewares\AuthMiddleware;
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
            $mensaje = 'Credenciales incorrectas o acceso denegado.';
            $codigoError = $e->getMessage();

            if (strpos(strtolower($codigoError), 'suscripción') !== false) {
                $codigoHttp = 403;
                $mensaje = $codigoError;
            } elseif ($codigoError === 'CREDENCIALES_INCORRECTAS') {
                $mensaje = 'El correo o la contraseña no coinciden.';
            } else {
                $mensaje = $codigoError; 
            }

            $this->responderConError($codigoHttp, 'ERROR_AUTH', $mensaje);
        }
    }

    public function logoutGlobal(): void 
    {
        try {
            $payload = AuthMiddleware::authenticate();
            $usuarioId = (int) $payload->id; 

            $this->servicioAutenticacion->cerrarSesionGlobal($usuarioId);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Sesiones cerradas en todos los dispositivos.']);
        } catch (Exception $e) {
            $this->responderConError(500, 'LOGOUT_ERROR', 'No se pudo cerrar la sesión global.');
        }
    }

    public function solicitarRecuperacion(): void 
    {
        $this->responderConError(400, 'REDIRECCION_ATLAS', 'Para recuperar tu contraseña, por favor dirígete al portal principal: atlasdigitaltech.cl');
    }

    public function restablecerPassword(): void 
    {
        $this->responderConError(400, 'REDIRECCION_ATLAS', 'La gestión de contraseñas se realiza desde el portal principal.');
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
}