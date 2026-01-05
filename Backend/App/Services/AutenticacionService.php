<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AutenticacionRepository;
use App\Helpers\JwtHelper;
use App\Services\AuditoriaService;
use Exception;

class AutenticacionService {

    private AutenticacionRepository $repositorio;

    public function __construct() {
        $this->repositorio = new AutenticacionRepository();
    }

    public function iniciarSesion(string $email, string $password): array {
        $usuario = $this->repositorio->buscarUsuarioPorEmail($email);

        if (!$usuario || !password_verify($password, $usuario['password'])) {
            AuditoriaService::registrar(
                'LOGIN_FALLIDO', 
                'usuarios', 
                null, 
                null, 
                ['email_intentado' => $email]
            );
            throw new Exception('CREDENCIALES_INCORRECTAS');
        }

        if ((int)$usuario['estado_suscripcion_id'] !== 1) {
            throw new Exception('CUENTA_SUSPENDIDA');
        }

        if ($usuario['fecha_fin_suscripcion'] < date('Y-m-d')) {
            throw new Exception('PLAN_VENCIDO');
        }

        $token = JwtHelper::generate([
            'id' => $usuario['id'],
            'rol' => $usuario['rol_id'],
            'empresa_id' => $usuario['empresa_id'],
            'nombre' => $usuario['nombre']
        ]);

        AuditoriaService::registrar(
            'LOGIN_EXITOSO', 
            'usuarios', 
            (int)$usuario['id'],
            null, 
            ['empresa_id' => $usuario['empresa_id']]
        );

        unset($usuario['password']);

        return [
            'token' => $token,
            'usuario' => $usuario
        ];
    }
}