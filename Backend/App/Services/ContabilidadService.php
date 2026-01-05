<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\ContabilidadRepository;
use App\Services\AuditoriaService;
use Exception;

class ContabilidadService {
    
    private ContabilidadRepository $repositorio;

    public function __construct() {
        $this->repositorio = new ContabilidadRepository();
    }

    public function registrarAsiento(int $referenciaId, string $codigoCuenta, float $debe, float $haber, string $glosa = ''): void {
        
        if (empty($glosa)) {
            $glosa = "Reg. Automático Ref: " . $referenciaId;
        }

        $asientoId = $this->repositorio->crearAsiento([
            'origen_id' => $referenciaId,
            'cuenta_codigo' => $codigoCuenta,
            'glosa' => $glosa,
            'debe' => $debe,
            'haber' => $haber
        ]);

        AuditoriaService::registrar(
            'REGISTRAR_ASIENTO', 
            'asientos_contables', 
            $asientoId, 
            null, 
            [
                'cuenta' => $codigoCuenta, 
                'debe' => $debe, 
                'haber' => $haber,
                'ref' => $referenciaId
            ]
        );
    }

    public function obtenerSaldosLibroMayor(string $fechaInicio, string $fechaFin): array {
        AuditoriaService::registrar('CONSULTA_LIBRO_MAYOR', null, null, null, ['desde' => $fechaInicio, 'hasta' => $fechaFin]);

        $datos = $this->repositorio->getSaldosAgrupados($fechaInicio, $fechaFin);
        foreach ($datos as &$cuenta) {
            $saldo = (float)$cuenta['total_debe'] - (float)$cuenta['total_haber'];
            
            $cuenta['saldo_neto'] = abs($saldo);
            $cuenta['tipo_saldo'] = $saldo >= 0 ? 'DEUDOR' : 'ACREEDOR';
        }

        return $datos;
    }
}