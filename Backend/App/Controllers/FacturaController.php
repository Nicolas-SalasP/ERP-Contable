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

    public function registrarCompra()
    {
        $input = file_get_contents("php://input");
        $datos = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE || empty($datos)) {
            return $this->responderJson(['success' => false, 'mensaje' => 'JSON inválido'], 400);
        }

        try {
            $requeridos = ['proveedorId', 'numeroFactura', 'montoBruto', 'fechaEmision'];
            foreach ($requeridos as $campo) {
                if (!isset($datos[$campo]))
                    throw new Exception("Falta el campo: $campo");
            }

            $resultado = $this->servicio->registrarCompra($datos);

            return $this->responderJson([
                'success' => true,
                'exito' => true,
                'mensaje' => 'Factura registrada y contabilizada correctamente',
                'datos' => $resultado,
                'id' => $resultado['id'] ?? null,
                'codigo' => $resultado['codigo'] ?? null
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
                'success' => false,
                'exito' => false,
                'codigo_error' => $error,
                'mensaje' => $msg
            ], $status);
        }
    }

    public function checkDuplicada()
    {
        $provId = $_GET['proveedor_id'] ?? null;
        $numFac = $_GET['numero_factura'] ?? null;

        if (!$provId || !$numFac) {
            return $this->responderJson(['success' => false, 'mensaje' => 'Faltan parámetros'], 400);
        }

        try {
            $existe = $this->servicio->verificarDuplicidad($provId, $numFac);
            return $this->responderJson([
                'success' => true,
                'exists' => $existe
            ]);

        } catch (Exception $e) {
            return $this->responderJson(['success' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function anular()
    {
        $input = json_decode(file_get_contents("php://input"), true);

        if (empty($input['codigo']) || empty($input['motivo'])) {
            return $this->responderJson(['success' => false, 'mensaje' => 'Falta codigo o motivo'], 400);
        }

        try {
            $res = $this->servicio->anularDocumento($input['codigo'], $input['motivo']);
            return $this->responderJson([
                'success' => true,
                'mensaje' => 'Anulación exitosa',
                'datos' => $res
            ]);
        } catch (Exception $e) {
            return $this->responderJson(['success' => false, 'mensaje' => $e->getMessage()], 400);
        }
    }

    public function historialProveedor() 
    {
        $busqueda = $_GET['q'] ?? '';
        $numFactura = $_GET['num'] ?? '';
        $estado = $_GET['estado'] ?? '';
        
        try {
            $resultados = $this->servicio->buscarHistorial($busqueda, $numFactura, $estado);
            
            return $this->responderJson([
                'success' => true, 
                'data' => $resultados
            ]);
        } catch (Exception $e) {
            return $this->responderJson(['success' => false, 'mensaje' => 'Error al buscar historial: ' . $e->getMessage()], 500);
        }
    }

    public function obtenerAsiento()
    {
        $facturaId = null;
        if (isset($_GET['id'])) {
            $facturaId = (int) $_GET['id'];
        } else {
            $urlParts = explode('/', strtok($_SERVER['REQUEST_URI'], '?'));
            foreach ($urlParts as $index => $part) {
                if ($part === 'facturas' && isset($urlParts[$index + 1]) && is_numeric($urlParts[$index + 1])) {
                    $facturaId = (int) $urlParts[$index + 1];
                    break;
                }
            }
        }

        if (!$facturaId) {
            return $this->responderJson(['success' => false, 'mensaje' => 'ID de factura no proporcionado'], 400);
        }

        try {
            $asientoData = $this->servicio->obtenerAsientoPorFactura($facturaId);

            return $this->responderJson([
                'success' => true,
                'data' => $asientoData
            ]);
        } catch (Exception $e) {
            return $this->responderJson(['success' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }
}