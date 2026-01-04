<?php
namespace App\Controllers;

use App\Services\ContabilidadService;
use Exception;

class ContabilidadController 
{
    private $servicio;

    public function __construct() 
    {
        $this->servicio = new ContabilidadService();
    }

    private function responderJson($datos, $codigoEstado = 200) 
    {
        http_response_code($codigoEstado);
        header('Content-Type: application/json');
        echo json_encode($datos);
        exit;
    }

    public function registrarAsientoManual() 
    {
        $input = file_get_contents("php://input");
        $datos = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || empty($datos)) {
            return $this->responderJson(['exito' => false, 'mensaje' => 'JSON inválido'], 400);
        }

        try {
            if (empty($datos['cuenta']) || !isset($datos['debe']) || !isset($datos['haber'])) {
                throw new Exception("Faltan datos: cuenta, debe o haber");
            }

            $referenciaId = $datos['referencia_id'] ?? 0; 

            $this->servicio->registrarAsiento(
                $referenciaId, 
                $datos['cuenta'], 
                floatval($datos['debe']), 
                floatval($datos['haber'])
            );

            return $this->responderJson([
                'exito' => true,
                'mensaje' => 'Asiento manual registrado correctamente'
            ], 201);

        } catch (Exception $e) {
            return $this->responderJson([
                'exito' => false, 
                'mensaje' => $e->getMessage()
            ], 400);
        }
    }

    public function verLibroDiario()
    {
        try {
            // $asientos = $this->servicio->obtenerLibroDiario();
            $asientos = [];
            
            return $this->responderJson([
                'exito' => true, 
                'datos' => $asientos
            ]);
        } catch (Exception $e) {
            return $this->responderJson(['exito' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }
}