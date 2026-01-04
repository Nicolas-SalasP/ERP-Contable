<?php
namespace App\Controllers;

use App\Services\FacturaService;
use Exception;

class FacturaController 
{
    private $servicio;

    public function __construct() 
    {
        $this->servicio = new FacturaService();
    }

    private function responderJson($datos, $codigoEstado = 200) 
    {
        http_response_code($codigoEstado);
        header('Content-Type: application/json');
        echo json_encode($datos);
        exit;
    }

    // POST: Registrar una nueva factura
    public function registrarCompra() 
    {
        $input = file_get_contents("php://input");
        $datos = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || empty($datos)) {
            return $this->responderJson(['exito' => false, 'mensaje' => 'JSON inválido'], 400);
        }

        try {
            $requeridos = ['proveedorId', 'numeroFactura', 'montoBruto', 'fechaEmision'];
            foreach ($requeridos as $campo) {
                if (!isset($datos[$campo])) throw new Exception("Falta el campo: $campo");
            }

            $resultado = $this->servicio->registrarCompra($datos);

            return $this->responderJson([
                'exito' => true,
                'mensaje' => 'Factura registrada y contabilizada correctamente',
                'datos' => $resultado
            ], 201);

        } catch (Exception $e) {
            $status = 400;
            $error = 'ERROR_GENERAL';
            
            if ($e->getMessage() === "FACTURA_DUPLICADA") {
                $status = 409;
                $error = 'DUPLICADO';
                $msg = 'Esta factura ya existe en el sistema.';
            } else {
                $msg = $e->getMessage();
            }

            return $this->responderJson([
                'exito' => false, 
                'codigo_error' => $error,
                'mensaje' => $msg
            ], $status);
        }
    }

    // GET: Verificar si ya existe
    public function verificarExistencia() 
    {
        $provId = $_GET['proveedor_id'] ?? null;
        $numFac = $_GET['numero_factura'] ?? null;

        if (!$provId || !$numFac) {
            return $this->responderJson(['exito' => false, 'mensaje' => 'Faltan parámetros'], 400);
        }

        try {
            $existe = $this->servicio->verificarDuplicidad($provId, $numFac);
            return $this->responderJson(['exito' => true, 'existe' => $existe]);
        } catch (Exception $e) {
            return $this->responderJson(['exito' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    // POST: Anular factura
    public function anular() 
    {
        $input = json_decode(file_get_contents("php://input"), true);
        
        if (empty($input['codigo']) || empty($input['motivo'])) {
            return $this->responderJson(['exito' => false, 'mensaje' => 'Falta codigo o motivo'], 400);
        }

        try {
            $res = $this->servicio->anularDocumento($input['codigo'], $input['motivo']);
            return $this->responderJson([
                'exito' => true, 
                'mensaje' => 'Anulación exitosa',
                'datos' => $res
            ]);
        } catch (Exception $e) {
            return $this->responderJson(['exito' => false, 'mensaje' => $e->getMessage()], 400);
        }
    }
}