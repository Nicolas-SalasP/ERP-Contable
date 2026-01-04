<?php
namespace App\Services;

use App\Repositories\ContabilidadRepository;

class ContabilidadService 
{
    private $repositorio;

    public function __construct() 
    {
        $this->repositorio = new ContabilidadRepository();
    }

    public function registrarAsiento($referenciaId, $codigoCuenta, $debe, $haber) 
    {
        $this->repositorio->createAsiento($referenciaId, $codigoCuenta, $debe, $haber);
    }

    public function obtenerSaldosLibroMayor($fechaInicio, $fechaFin)
    {
        $datos = $this->repositorio->getSaldosAgrupados($fechaInicio, $fechaFin);

        foreach ($datos as &$cuenta) {
            $cuenta['saldo_neto'] = floatval($cuenta['total_debe']) - floatval($cuenta['total_haber']);
            $cuenta['tipo_saldo'] = $cuenta['saldo_neto'] >= 0 ? 'DEUDOR' : 'ACREEDOR';
        }

        return $datos;
    }
}