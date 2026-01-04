<?php
namespace App\Controllers;

use App\Services\ContabilidadService;
use Exception;

class LibroMayorController 
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

    public function verReporte() 
    {
        $fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
        $fechaFin    = $_GET['fecha_fin'] ?? date('Y-m-t');

        try {
            $reporte = $this->servicio->obtenerSaldosLibroMayor($fechaInicio, $fechaFin);

            return $this->responderJson([
                'exito' => true,
                'periodo' => [
                    'desde' => $fechaInicio,
                    'hasta' => $fechaFin
                ],
                'datos' => $reporte
            ]);

        } catch (Exception $e) {
            return $this->responderJson([
                'exito' => false, 
                'mensaje' => 'Error al generar el Libro Mayor: ' . $e->getMessage()
            ], 500);
        }
    }
}