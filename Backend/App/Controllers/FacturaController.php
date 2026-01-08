<?php
namespace App\Controllers;

use App\Services\FacturaService;
use App\Services\AnulacionService; // <--- AGREGADO IMPORTANTE
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

    // --- HISTORIAL PAGINADO (Para la tabla principal) ---
    public function historial()
    {
        $proveedor = $_GET['search'] ?? '';
        $numero = $_GET['num'] ?? '';
        $estado = $_GET['estado'] ?? '';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;

        try {
            $resultado = $this->servicio->obtenerHistorialPaginado($proveedor, $numero, $estado, $limit, $offset);

            return $this->responderJson([
                'success' => true,
                'data' => $resultado['data'],
                'pagination' => [
                    'total' => $resultado['total'],
                    'page' => $page,
                    'limit' => $limit,
                    'totalPages' => ceil($resultado['total'] / $limit)
                ]
            ]);
        } catch (Exception $e) {
            return $this->responderJson(['success' => false, 'mensaje' => $e->getMessage()], 500);
        }
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
            $status = ($e->getMessage() === "FACTURA_DUPLICADA") ? 409 : 400;
            return $this->responderJson([
                'success' => false,
                'codigo_error' => ($status === 409) ? 'DUPLICADO' : 'ERROR_GENERAL',
                'mensaje' => ($status === 409) ? 'Esta factura ya existe en el sistema.' : $e->getMessage()
            ], $status);
        }
    }

    public function anular()
    {
        $input = json_decode(file_get_contents("php://input"), true);

        if (empty($input['codigo']) || empty($input['motivo'])) {
            return $this->responderJson(['success' => false, 'mensaje' => 'Falta codigo o motivo'], 400);
        }

        try {
            $anulacionService = new AnulacionService();
            $res = $anulacionService->anularDocumento($input); 

            return $this->responderJson([
                'success' => true,
                'mensaje' => 'Anulación exitosa',
                'datos' => $res
            ]);
        } catch (Exception $e) {
            return $this->responderJson(['success' => false, 'mensaje' => $e->getMessage()], 400);
        }
    }

    // Métodos auxiliares simples
    public function checkDuplicada()
    {
        try {
            $existe = $this->servicio->verificarDuplicidad($_GET['proveedor_id'] ?? null, $_GET['numero_factura'] ?? null);
            return $this->responderJson(['success' => true, 'exists' => $existe]);
        } catch (Exception $e) {
            return $this->responderJson(['success' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function obtenerAsiento($id = null)
    {
        $facturaId = $id ?? $_GET['id'] ?? null;

        if (!$facturaId) {
            return $this->responderJson(['success' => false, 'mensaje' => 'ID faltante'], 400);
        }

        try {
            $data = $this->servicio->obtenerAsientoPorFactura((int)$facturaId);
            return $this->responderJson(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            return $this->responderJson(['success' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }
}