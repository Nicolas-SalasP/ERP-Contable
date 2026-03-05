<?php
namespace App\Controllers;

use App\Services\RentaService;
use Exception;

class RentaController
{
    private $servicio;

    public function __construct()
    {
        $this->servicio = new RentaService();
    }

    private function responderJson($datos, $codigoEstado = 200)
    {
        http_response_code($codigoEstado);
        header('Content-Type: application/json');
        echo json_encode($datos);
        exit;
    }

    public function obtenerPreRenta($anio)
    {
        try {
            if (!$anio || !is_numeric($anio)) {
                $anio = (int) date('Y');
            }
            
            $resultado = $this->servicio->calcularBaseImponible((int)$anio);
            $this->responderJson(['success' => true, 'data' => $resultado]);
            
        } catch (Exception $e) {
            $this->responderJson(['success' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function obtenerMapeo() {
        try {
            return $this->responderJson(['success' => true, 'data' => $this->servicio->obtenerMapeoCuentas()]);
        } catch (Exception $e) {
            return $this->responderJson(['success' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function guardarMapeo() {
        try {
            $data = json_decode(file_get_contents("php://input"), true);
            $this->servicio->guardarMapeoCuenta($data['codigo_cuenta'], $data['concepto_sii']);
            return $this->responderJson(['success' => true, 'mensaje' => 'Mapeo guardado correctamente']);
        } catch (Exception $e) {
            return $this->responderJson(['success' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function eliminarMapeo($id) {
        try {
            $this->servicio->eliminarMapeoCuenta((int)$id);
            return $this->responderJson(['success' => true, 'mensaje' => 'Mapeo eliminado']);
        } catch (Exception $e) {
            return $this->responderJson(['success' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }
}