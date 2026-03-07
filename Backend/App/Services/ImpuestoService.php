<?php
namespace App\Services;

use App\Repositories\ImpuestoRepository;
use Exception;

class ImpuestoService {
    private $repo;

    public function __construct() {
        $this->repo = new ImpuestoRepository();
    }

    public function simularCierreMensual($mes, $anio) {
        $datos = $this->repo->getMovimientosIvaMes($mes, $anio);
        $debito = floatval($datos['iva_debito'] ?? 0);
        $credito = floatval($datos['iva_credito'] ?? 0);
        
        $aPagar = 0;
        $remanente = 0;

        if ($debito > $credito) {
            $aPagar = $debito - $credito;
        } else {
            $remanente = $credito - $debito;
        }

        return [
            'mes' => $mes,
            'anio' => $anio,
            'iva_debito' => $debito,
            'iva_credito' => $credito,
            'a_pagar' => $aPagar,
            'remanente' => $remanente,
            'ya_cerrado' => $this->repo->verificarCierreExistente($mes, $anio) ? true : false
        ];
    }

    public function ejecutarCierreMensual($mes, $anio) {
        if ($this->repo->verificarCierreExistente($mes, $anio)) {
            throw new Exception("El cierre de IVA para $mes/$anio ya fue realizado.");
        }

        $datos = $this->repo->getMovimientosIvaMes($mes, $anio);
        $debito = floatval($datos['iva_debito'] ?? 0);
        $credito = floatval($datos['iva_credito'] ?? 0);

        if ($debito == 0 && $credito == 0) {
            throw new Exception("No hay movimientos de IVA registrados en este mes para centralizar.");
        }

        $this->repo->asegurarCuentaIvaPorPagar();

        $this->repo->iniciarTransaccion();
        try {
            $fechaCierre = date("Y-m-t", strtotime("$anio-$mes-01"));
            $codigoUnico = $this->repo->generarCodigoAsiento($fechaCierre);
            $glosa = "Centralización IVA (F29) - Periodo $mes/$anio";

            $asientoId = $this->repo->crearAsientoCierre($fechaCierre, $glosa, $codigoUnico);
            if ($debito > 0) $this->repo->agregarDetalleAsiento($asientoId, '210201', $debito, 0);
            if ($credito > 0) $this->repo->agregarDetalleAsiento($asientoId, '110001', 0, $credito);
            if ($debito > $credito) {
                $this->repo->agregarDetalleAsiento($asientoId, '210301', 0, ($debito - $credito));
            } elseif ($credito > $debito) {
                $this->repo->agregarDetalleAsiento($asientoId, '110402', ($credito - $debito), 0);
            }

            $this->repo->confirmarTransaccion();
            return ['success' => true, 'mensaje' => 'Asiento de centralización generado exitosamente.'];
        } catch (Exception $e) {
            $this->repo->revertirTransaccion();
            throw new Exception("Error al generar asiento de cierre: " . $e->getMessage());
        }
    }
}