<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AutenticacionService;
use App\Services\EmpresaService;
use Exception;

class AutenticacionController {

    private AutenticacionService $servicioAutenticacion;
    private EmpresaService $servicioEmpresa;

    public function __construct() {
        $this->servicioAutenticacion = new AutenticacionService();
        $this->servicioEmpresa = new EmpresaService();
    }

    public function login(): void {
        $entradaCruda = file_get_contents("php://input");
        $datos = json_decode($entradaCruda, true);

        if (empty($datos['email']) || empty($datos['password'])) {
            $this->responderConError(400, 'DATOS_INCOMPLETOS', 'El correo y la contraseña son obligatorios.');
        }

        try {
            $resultado = $this->servicioAutenticacion->iniciarSesion($datos['email'], $datos['password']);
            echo json_encode([
                'success' => true,
                'token' => $resultado['token'],
                'user' => $resultado['usuario']
            ]);

        } catch (Exception $e) {
            $codigoHttp = 401;
            $mensaje = 'Credenciales incorrectas.';
            $codigoError = $e->getMessage();

            if ($codigoError === 'CUENTA_SUSPENDIDA') {
                $codigoHttp = 403;
                $mensaje = 'Su cuenta está inactiva. Contacte a soporte.';
            } elseif ($codigoError === 'PLAN_VENCIDO') {
                $codigoHttp = 403;
                $mensaje = 'Su suscripción ha vencido. Por favor realice el pago.';
            } elseif ($codigoError === 'CREDENCIALES_INCORRECTAS') {
                $mensaje = 'Correo o contraseña incorrectos.';
            }

            $this->responderConError($codigoHttp, $codigoError, $mensaje);
        }
    }

    public function registro(): void {
        $entradaCruda = file_get_contents("php://input");
        $datos = json_decode($entradaCruda, true);
        $camposRequeridos = ['empresa_rut', 'empresa_razon_social', 'admin_nombre', 'admin_email', 'admin_password'];
        foreach ($camposRequeridos as $campo) {
            if (empty($datos[$campo])) {
                $this->responderConError(400, 'ERROR_VALIDACION', "El campo '$campo' es obligatorio.");
            }
        }

        try {
            $resultado = $this->servicioEmpresa->registrarEmpresaCompleta($datos);
            
            http_response_code(201);
            echo json_encode($resultado);

        } catch (Exception $e) {
            $esConflicto = stripos($e->getMessage(), 'ya existe') !== false || stripos($e->getMessage(), 'duplicado') !== false;
            $codigoHttp = $esConflicto ? 409 : 400;
            
            $this->responderConError($codigoHttp, 'REGISTRO_FALLIDO', $e->getMessage());
        }
    }

    private function responderConError(int $codigoHttp, string $codigoErrorInterno, string $mensajeLegible): void {
        http_response_code($codigoHttp);
        echo json_encode([
            'success' => false,
            'error_code' => $codigoErrorInterno,
            'message' => $mensajeLegible
        ]);
        exit;
    }
}