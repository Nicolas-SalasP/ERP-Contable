<?php
namespace App\Controllers;

use App\Services\BancoService;
use App\Middlewares\AuthMiddleware;
use Exception;

class BancoController
{
    private $servicio;

    public function __construct() {
        $this->servicio = new BancoService();
    }

    private function responderJson($datos, $codigo = 200) {
        http_response_code($codigo);
        header('Content-Type: application/json');
        echo json_encode($datos);
        exit;
    }

    public function listarCuentasEmpresa()
    {
        try {
            $auth = AuthMiddleware::authenticate();
            $empresaId = $auth->empresa_id ?? 1;

            $cuentas = $this->servicio->obtenerCuentasEmpresa($empresaId);
            $this->responderJson(['success' => true, 'data' => $cuentas]);

        } catch (Exception $e) {
            $this->responderJson(['success' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function procesarNominaMasiva()
    {
        try {
            $data = json_decode(file_get_contents("php://input"), true);
            $facturaIds = $data['facturas_ids'] ?? [];
            $cuentaBancariaId = $data['cuenta_bancaria_id'] ?? null;

            if (empty($facturaIds)) throw new Exception("Debe seleccionar al menos una factura.");
            if (!$cuentaBancariaId) throw new Exception("Debe seleccionar una cuenta bancaria de origen.");

            $resultado = $this->servicio->procesarNominaPagos($facturaIds, $cuentaBancariaId);
            $this->responderJson($resultado);

        } catch (Exception $e) {
            $this->responderJson(['success' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function importarExcel()
    {
        try {
            if (!isset($_FILES['archivo_excel'])) {
                throw new Exception("No se ha subido ningún archivo.");
            }
            
            $cuentaId = $_POST['cuenta_bancaria_id'] ?? null;
            if (!$cuentaId) {
                throw new Exception("Debe especificar la cuenta bancaria destino.");
            }

            $archivoTMP = $_FILES['archivo_excel']['tmp_name'];
            
            $resultado = $this->servicio->importarCartolaExcel($cuentaId, $archivoTMP);
            $this->responderJson($resultado);

        } catch (Exception $e) {
            $this->responderJson(['success' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function registrarIngresoManual()
    {
        try {
            $data = json_decode(file_get_contents("php://input"), true);
            if (empty($data['monto']) || empty($data['fecha'])) {
                throw new Exception("Faltan datos obligatorios.");
            }

            $resultado = $this->servicio->registrarIngresoManual($data);
            $this->responderJson($resultado);

        } catch (Exception $e) {
            $this->responderJson(['success' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function listarMovimientosPendientes($cuentaId) {
        try {
            $data = $this->servicio->obtenerMovimientosPendientes($cuentaId);
            $this->responderJson(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            $this->responderJson(['success' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function listarCuentasImputables() {
        try {
            $data = $this->servicio->obtenerCuentasImputables();
            $this->responderJson(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            $this->responderJson(['success' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function conciliarMovimiento() {
        try {
            $datos = json_decode(file_get_contents("php://input"), true);
            $resultado = $this->servicio->conciliarMovimientoDirecto($datos);
            $this->responderJson($resultado);
        } catch (Exception $e) {
            $this->responderJson(['success' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function ignorarMovimiento() {
        try {
            $datos = json_decode(file_get_contents("php://input"), true);
            $resultado = $this->servicio->ignorarMovimiento($datos['movimiento_id']);
            $this->responderJson($resultado);
        } catch (Exception $e) {
            $this->responderJson(['success' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function listarAnticiposPendientes() {
        try {
            $data = $this->servicio->obtenerAnticiposPendientes();
            $this->responderJson(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            $this->responderJson(['success' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function conciliarConAnticipo() {
        try {
            $datos = json_decode(file_get_contents("php://input"), true);
            $resultado = $this->servicio->conciliarConAnticipo($datos);
            $this->responderJson($resultado);
        } catch (Exception $e) {
            $this->responderJson(['success' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }
}