<?php
namespace App\Controllers;

use App\Services\AnulacionService;
use Exception;

class AnulacionController 
{
    private $servicio;

    public function __construct() 
    {
        $this->servicio = new AnulacionService();
    }

    private function responderJson($datos, $codigoEstado = 200) 
    {
        http_response_code($codigoEstado);
        header('Content-Type: application/json');
        echo json_encode($datos);
        exit;
    }

    public function buscar() 
    {
        $input = json_decode(file_get_contents("php://input"), true);
        $codigo = $input['codigo'] ?? '';

        if (empty($codigo)) {
            return $this->responderJson(['success' => false, 'mensaje' => 'Debe ingresar un código.'], 400);
        }

        try {
            $documento = $this->servicio->buscarDocumentoPorCodigo($codigo);
            if ($documento) {
                return $this->responderJson(['success' => true, 'data' => $documento]);
            } else {
                return $this->responderJson(['success' => false, 'mensaje' => 'Documento no encontrado o código inválido.'], 404);
            }
        } catch (Exception $e) {
            return $this->responderJson(['success' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function ejecutar() 
    {
        $input = json_decode(file_get_contents("php://input"), true);
        if (empty($input['codigo']) || empty($input['motivo'])) {
            return $this->responderJson(['success' => false, 'mensaje' => 'Faltan datos requeridos (código o motivo).'], 400);
        }

        try {
            $resultado = $this->servicio->anularDocumento($input['codigo'], $input['tipo'], $input['motivo']);
            return $this->responderJson(['success' => true, 'mensaje' => 'Anulación exitosa', 'nuevo_asiento_id' => $resultado]);
        } catch (Exception $e) {
            return $this->responderJson(['success' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }
}