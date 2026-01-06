<?php
namespace App\Controllers;

use App\Services\ProveedorService;
use App\Services\PaisService;
use Exception;

class ProveedorController
{
    private $service;
    private $paisService;

    public function __construct()
    {
        $this->service = new ProveedorService();
        $this->paisService = new PaisService(); 
    }

    public function getByCodigo($codigo)
    {
        try {
            $proveedor = $this->service->buscarPorCodigo($codigo);

            if ($proveedor) {
                $nombrePais = $this->formatPais($proveedor['pais_iso']);

                echo json_encode([
                    'success' => true,
                    'data' => [
                        'id' => $proveedor['id'],
                        'codigo_interno' => $proveedor['codigo_interno'],
                        'rut' => $proveedor['rut'],
                        'razonSocial' => $proveedor['razon_social'],
                        'pais' => $nombrePais,
                        'pais_iso' => $proveedor['pais_iso'],
                        'moneda' => $proveedor['moneda_defecto'],
                        'ubicacion' => ($proveedor['comuna'] ?? '') . ', ' . ($proveedor['region'] ?? ''),
                        'contacto' => $proveedor['nombre_contacto']
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Proveedor no encontrado']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function getAll()
    {
        try {
            $proveedores = $this->service->obtenerTodos();
            echo json_encode([
                'success' => true,
                'count' => count($proveedores),
                'data' => $proveedores
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function create()
    {
        $input = json_decode(file_get_contents("php://input"), true);

        try {
            $empresaId = $this->getEmpresaIdFromToken();

            if (!$empresaId) {
                http_response_code(401);
                throw new Exception("No se pudo identificar la empresa del usuario. Token inválido o ausente.");
            }

            $input['empresa_id'] = $empresaId;

            $resultado = $this->service->crearProveedor($input);

            echo json_encode([
                'success' => true,
                'message' => 'Proveedor creado exitosamente',
                'id' => $resultado['id'],
                'codigo_generado' => $resultado['codigo']
            ]);

        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Integrity constraint violation') !== false) {
                http_response_code(400);
                $mensaje = 'Error de integridad: Verifique que la empresa exista o que el código no esté duplicado.';
            } else {
                http_response_code(400);
                $mensaje = $e->getMessage();
            }
            echo json_encode(['success' => false, 'message' => $mensaje]);
        }
    }

    private function formatPais($iso)
    {
        try {
            $paisData = $this->paisService->obtenerPorIso($iso);
            return $paisData ? $paisData['nombre'] : 'Extranjero';
        } catch (Exception $e) {
            return 'Desconocido';
        }
    }

    private function getEmpresaIdFromToken()
    {
        $headers = null;
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers['Authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
        }

        if (!isset($headers['Authorization'])) {
            return null;
        }

        $token = str_replace('Bearer ', '', $headers['Authorization']);
        $tokenParts = explode('.', $token);

        if (count($tokenParts) < 2) {
            return null;
        }

        $payloadJson = base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1]));
        $payload = json_decode($payloadJson);

        if (!$payload) {
            return null;
        }
        if (isset($payload->empresa_id)) {
            return $payload->empresa_id;
        }
        if (isset($payload->data) && isset($payload->data->empresa_id)) {
            return $payload->data->empresa_id;
        }
        if (isset($payload->user) && isset($payload->user->empresa_id)) {
            return $payload->user->empresa_id;
        }

        return null;
    }
}