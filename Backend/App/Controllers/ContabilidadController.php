<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\ContabilidadService;
use Exception;

class ContabilidadController 
{
    private ContabilidadService $servicio;

    public function __construct() 
    {
        $this->servicio = new ContabilidadService();
    }

    public function registrarAsientoManual(): void 
    {
        $input = file_get_contents("php://input");
        $datos = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || empty($datos)) {
            $this->responderJson(['exito' => false, 'mensaje' => 'JSON inválido o vacío'], 400);
        }

        try {
            if (empty($datos['cuenta']) || !isset($datos['debe']) || !isset($datos['haber'])) {
                throw new Exception("Faltan datos obligatorios: cuenta, debe o haber.");
            }

            $referenciaId = isset($datos['referencia_id']) ? (int)$datos['referencia_id'] : 0; 
            $glosa = $datos['glosa'] ?? 'Asiento Manual';
            $resultado = $this->servicio->registrarAsiento(
                $referenciaId, 
                (string)$datos['cuenta'], 
                (float)$datos['debe'], 
                (float)$datos['haber'],
                (string)$glosa
            );

            $this->responderJson([
                'exito' => true,
                'mensaje' => 'Asiento registrado correctamente en el Libro Diario.',
                'id' => $resultado['id'],
                'codigo' => $resultado['codigo']
            ], 201);

        } catch (Exception $e) {
            $codigo = $e->getMessage() === 'CUENTA_SUSPENDIDA' ? 403 : 400;
            
            $this->responderJson([
                'exito' => false, 
                'error_code' => 'ERROR_CONTABLE',
                'mensaje' => $e->getMessage()
            ], $codigo);
        }
    }

    public function anularAsiento(): void
    {
        $input = file_get_contents("php://input");
        $datos = json_decode($input, true);

        if (empty($datos['codigo']) || empty($datos['motivo'])) {
            $this->responderJson(['exito' => false, 'mensaje' => 'Faltan parámetros obligatorios: codigo del asiento o motivo.'], 400);
        }

        try {
            $codigoAsiento = (int) $datos['codigo'];
            $motivo = (string) $datos['motivo'];

            $resultado = $this->servicio->anularAsiento($codigoAsiento, $motivo);

            $this->responderJson($resultado);

        } catch (Exception $e) {
            $this->responderJson([
                'exito' => false, 
                'mensaje' => $e->getMessage()
            ], 400);
        }
    }

    public function verSaldosMayor(): void
    {
        try {
            $fechaInicio = $_GET['desde'] ?? date('Y-m-01');
            $fechaFin = $_GET['hasta'] ?? date('Y-m-t');

            $saldos = $this->servicio->obtenerSaldosLibroMayor($fechaInicio, $fechaFin);
            
            $this->responderJson([
                'exito' => true, 
                'rango' => ['desde' => $fechaInicio, 'hasta' => $fechaFin],
                'datos' => $saldos
            ]);

        } catch (Exception $e) {
            $this->responderJson(['exito' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    private function responderJson(array $datos, int $codigoEstado = 200): void 
    {
        http_response_code($codigoEstado);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($datos);
        exit;
    }
}