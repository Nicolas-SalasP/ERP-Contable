<?php
namespace App\Controllers;

use App\Services\CotizacionService;
use App\Services\PdfService;
use App\Repositories\EmpresaRepository;
use App\Helpers\FechaHelper;
use Exception;

class CotizacionController
{
    private $servicio;
    private $empresaRepo;

    public function __construct()
    {
        $this->servicio = new CotizacionService();
        $this->empresaRepo = new EmpresaRepository();
    }

    private function responderJson($datos, $codigoEstado = 200)
    {
        http_response_code($codigoEstado);
        header('Content-Type: application/json');
        echo json_encode($datos);
        exit;
    }

    public function crear()
    {
        $input = json_decode(file_get_contents("php://input"), true);

        if (empty($input['clienteId']) || empty($input['items']) || empty($input['fechaEmision'])) {
            return $this->responderJson(['success' => false, 'mensaje' => 'Faltan datos obligatorios'], 400);
        }

        try {
            $diasValidez = isset($input['diasValidez']) ? (int)$input['diasValidez'] : 15;
            $fechaVencimiento = FechaHelper::calcularVencimientoHabil($input['fechaEmision'], $diasValidez);
            $input['fechaVencimiento'] = $fechaVencimiento;

            $resultado = $this->servicio->registrarCotizacion($input);
            
            return $this->responderJson([
                'success' => true,
                'mensaje' => 'Cotización creada correctamente',
                'id' => $resultado['id'],
                'fechaVencimientoCalculada' => $fechaVencimiento 
            ], 201);

        } catch (Exception $e) {
            return $this->responderJson(['success' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function listar()
    {
        try {
            $data = $this->servicio->obtenerHistorial();
            return $this->responderJson(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            return $this->responderJson(['success' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function cambiarEstado($id)
    {
        $data = json_decode(file_get_contents("php://input"), true);
        $nuevoEstado = $data['estado'] ?? null;

        if (!$nuevoEstado) {
            return $this->responderJson(['success' => false, 'mensaje' => 'Estado no proporcionado'], 400);
        }

        try {
            $res = $this->servicio->cambiarEstado((int) $id, $nuevoEstado);
            return $this->responderJson(['success' => $res]);
        } catch (Exception $e) {
            return $this->responderJson(['success' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    public function descargarPdf($id)
    {
        try {
            $cotizacion = $this->servicio->obtenerDetalleCompleto((int) $id);
            
            if (!$cotizacion) {
                throw new Exception("Cotización no encontrada");
            }

            $empresaId = $cotizacion['empresa_id'];
            $empresa = $this->empresaRepo->obtenerPerfil($empresaId);
            
            $pdf = new PdfService();
            $pdfContent = $pdf->generarCotizacion($cotizacion, $empresa, 'S');

            if (ob_get_level()) ob_end_clean();
            $nombreClienteLimpio = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $cotizacion['nombre_cliente']);
            $nombreClienteLimpio = trim($nombreClienteLimpio); 
            $nombreArchivo = 'Cotizacion_' . $id . ' - ' . $nombreClienteLimpio . '.pdf';

            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
            
            echo $pdfContent;
            exit;

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
}