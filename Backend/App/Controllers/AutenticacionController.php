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
            $codigoError = $e->getMessage();
            $mensaje = 'Credenciales incorrectas o acceso denegado.';

            if ($codigoError === 'ACCOUNT_INACTIVE') {
                $codigoHttp = 403;
                $codigoErrorInterno = 'ACCOUNT_INACTIVE';
                $mensaje = 'Account is suspended or inactive.';
            } elseif ($codigoError === 'CREDENCIALES_INCORRECTAS') {
                $codigoErrorInterno = 'ERROR_AUTH';
                $mensaje = 'El correo o la contraseña no coinciden.';
            } else {
                $codigoErrorInterno = 'ERROR_AUTH';
                $mensaje = $codigoError; 
            }

            $this->responderConError($codigoHttp, $codigoErrorInterno, $mensaje);
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
        $this->responderConError(400, 'REDIRECCION_ATLAS', 'Para recuperar tu contraseña, dirígete al portal principal: atlasdigitaltech.cl');
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