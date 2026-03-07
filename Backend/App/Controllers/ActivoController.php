<?php
namespace App\Controllers;

use App\Services\ActivoService;
use Exception;

class ActivoController
{
    private $servicio;

    public function __construct()
    {
        $this->servicio = new ActivoService();
    }

    private function responderJson($datos, $codigoEstado = 200)
    {
        http_response_code($codigoEstado);
        header('Content-Type: application/json');
        echo json_encode($datos);
        exit;
    }

    public function getParametros() {
        $cuentas = $this->servicio->obtenerCuentasActivos();
        return $this->responderJson(['success' => true, 'data' => ['cuentas' => $cuentas]]);
    }

    // ======================================================================
    // ACTIVOS FIJOS DIRECTOS
    // ======================================================================
    public function crear()
    {
        $input = json_decode(file_get_contents("php://input"), true);
        $empresaId = 1;

        if (empty($input['nombre_activo']) || empty($input['categoria_sii_id'])) {
            return $this->responderJson(['success' => false, 'mensaje' => 'Faltan datos obligatorios'], 400);
        }

        try {
            $nuevoId = $this->servicio->crearDesdeFactura($input, $empresaId);
            return $this->responderJson(['success' => true, 'mensaje' => 'Activo creado y activado exitosamente.', 'id' => $nuevoId]);
        } catch (Exception $e) {
            return $this->responderJson(['success' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function listar()
    {
        try {
            $activos = $this->servicio->obtenerTodos();
            $categorias = $this->servicio->obtenerCategoriasSII();
            return $this->responderJson(['success' => true, 'data' => $activos, 'categorias' => $categorias]);
        } catch (Exception $e) {
            return $this->responderJson(['success' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function activar($id)
    {
        $datos = json_decode(file_get_contents("php://input"), true);
        try {
            $this->servicio->activarDepreciacion((int) $id, $datos);
            return $this->responderJson(['success' => true, 'mensaje' => 'Activo configurado y depreciación iniciada.']);
        } catch (Exception $e) {
            return $this->responderJson(['success' => false, 'mensaje' => $e->getMessage()], 400);
        }
    }

    public function listarPendientes()
    {
        try {
            $pendientes = $this->servicio->obtenerPendientesDeContabilidad();
            return $this->responderJson(['success' => true, 'data' => $pendientes]);
        } catch (Exception $e) {
            return $this->responderJson(['success' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function procesarDepreciacion()
    {
        $input = json_decode(file_get_contents("php://input"), true);
        $fechaCierre = $input['fecha_cierre'] ?? date('Y-m-t');

        try {
            $resultado = $this->servicio->ejecutarDepreciacionMensual($fechaCierre);
            return $this->responderJson(['success' => true, 'mensaje' => 'Proceso ejecutado.', 'data' => $resultado]);
        } catch (Exception $e) {
            return $this->responderJson(['success' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    // ======================================================================
    // PROYECTOS DE ACTIVOS (En Construcción)
    // ======================================================================
    public function crearProyecto()
    {
        $input = json_decode(file_get_contents("php://input"), true);
        $response = $this->servicio->crearProyecto($input);
        $this->responderJson($response, $response['success'] ? 200 : 400);
    }

    public function listarProyectos()
    {
        try {
            $proyectos = $this->servicio->obtenerTodosProyectos();
            return $this->responderJson(['success' => true, 'data' => $proyectos]);
        } catch (Exception $e) {
            return $this->responderJson(['success' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function imputarFacturaProyecto($idProyecto)
    {
        $data = json_decode(file_get_contents("php://input"), true);
        $response = $this->servicio->vincularFacturaProyecto((int)$idProyecto, (int)$data['factura_id'], (float)$data['monto']);
        $this->responderJson($response, $response['success'] ? 200 : 400);
    }

    public function activarProyecto($idProyecto)
    {
        $response = $this->servicio->activarProyecto((int)$idProyecto);
        $this->responderJson($response, $response['success'] ? 200 : 400);
    }

    public function procesarDepreciacionProyectos()
    {
        $input = json_decode(file_get_contents("php://input"), true);
        $fechaCierre = $input['fecha_cierre'] ?? date('Y-m-t');
        
        $response = $this->servicio->ejecutarDepreciacionMensualProyectos($fechaCierre);
        $this->responderJson($response, $response['success'] ? 200 : 500);
    }

    public function bajaProyecto($idProyecto)
    {
        $data = json_decode(file_get_contents("php://input"), true);
        $response = $this->servicio->darDeBajaProyecto((int)$idProyecto, $data['motivo'] ?? 'BAJA', (float)($data['monto_venta'] ?? 0));
        $this->responderJson($response, $response['success'] ? 200 : 400);
    }

    public function facturasDisponiblesProyecto() {
        $data = $this->servicio->obtenerFacturasDisponiblesProyecto();
        return $this->responderJson(['success' => true, 'data' => $data]);
    }

    public function analisisProyecto($idProyecto) {
        $data = $this->servicio->obtenerAnalisisProyecto((int)$idProyecto);
        return $this->responderJson(['success' => true, 'data' => $data]);
    }
}