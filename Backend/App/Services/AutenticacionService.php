<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AutenticacionRepository;
use App\Helpers\JwtHelper;
use App\Config\Env;
use Exception;

class AutenticacionService {

    private AutenticacionRepository $repository;

    public function __construct() {
        $this->repository = new AutenticacionRepository();
    }

    // =========================================================================
    // 1. INICIAR SESIÓN (Integración con AtlasWeb IdP)
    // =========================================================================
    public function iniciarSesion(string $email, string $password): array {
        
        $apiUrl = Env::get('ATLAS_API_URL') . '/internal/erp/validate-login';
        $apiKey = Env::get('ATLAS_API_KEY');

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['email' => $email, 'password' => $password]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-ERP-API-KEY: ' . $apiKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Pruebas con POSTMAN
        // die(json_encode([
        //     'http_code' => $httpCode,
        //     'url_consultada' => $apiUrl,
        //     'respuesta_cruda_atlas' => $response
        // ]));

        if (!$response) {
            throw new Exception("Error de comunicación con el servidor central de AtlasWeb.");
        }

        $atlasData = json_decode($response, true);

        if ($httpCode !== 200 || !($atlasData['success'] ?? false)) {
            $mensajeError = $atlasData['message'] ?? 'CREDENCIALES_INCORRECTAS';
            throw new Exception($mensajeError);
        }

        $datosUsuarioAtlas = $atlasData['user'];

        $usuarioLocal = $this->repository->buscarUsuarioPorEmail($datosUsuarioAtlas['email']);

        if (!$usuarioLocal) {
            $usuarioId = $this->repository->crearUsuarioEspejo([
                'nombre' => $datosUsuarioAtlas['name'],
                'email' => $datosUsuarioAtlas['email'],
                'rut' => $datosUsuarioAtlas['rut'] ?? null,
                'rol_id' => 2, 
                'empresa_id' => 1 
            ]);
            $usuarioLocal = $this->repository->buscarUsuarioPorId($usuarioId);
        }

        $token = JwtHelper::generate([
            'id' => $usuarioLocal['id'],
            'email' => $usuarioLocal['email'],
            'rol_id' => $usuarioLocal['rol_id'],
            'empresa_id' => $usuarioLocal['empresa_id'] ?? 1,
            'version_token' => $usuarioLocal['version_token'] ?? 1
        ]);

        return [
            'token' => $token,
            'usuario' => $usuarioLocal
        ];
    }

    // =========================================================================
    // 2. CERRAR SESIÓN GLOBAL
    // =========================================================================
    public function cerrarSesionGlobal(int $usuarioId): void 
    {
        $this->repository->rotarVersionToken($usuarioId);
    }
}