<?php
namespace App\Services;

use App\Repositories\ActivoRepository;
use App\Services\ContabilidadService;
use Exception;

class ActivoService
{
    private $activoRepository;
    private $contabilidadService;

    public function __construct()
    {
        $this->activoRepository = new ActivoRepository();
        $this->contabilidadService = new ContabilidadService();
    }

    public function crearDesdeFactura($datos, $empresaId)
    {
        if ($datos['monto_adquisicion'] <= 0) {
            throw new Exception("El monto de adquisición debe ser mayor a cero.");
        }

        return $this->activoRepository->crearActivoFijo($datos, $empresaId);
    }

    public function obtenerTodos()
    {
        return $this->activoRepository->getActivos();
    }

    public function obtenerCategoriasSII()
    {
        return $this->activoRepository->getCategoriasSii();
    }

    public function activarDepreciacion($id, $datos)
    {
        if (empty($datos['categoria_sii_id']) || empty($datos['tipo_depreciacion']) || empty($datos['fecha_activacion'])) {
            throw new Exception("Faltan datos obligatorios para activar el activo.");
        }

        $res = $this->activoRepository->activarActivo($id, $datos);
        if (!$res) {
            throw new Exception("No se pudo activar el activo. Verifique los permisos.");
        }
        return true;
    }

    public function obtenerPendientesDeContabilidad()
    {
        return $this->activoRepository->obtenerPendientesDeContabilidad();
    }

    public function ejecutarDepreciacionMensual($fechaCierre)
    {
        $activos = $this->activoRepository->obtenerActivosDepreciables();

        if (empty($activos)) {
            throw new Exception("No hay activos fijos en estado ACTIVO para depreciar.");
        }

        $totalDepreciacionMes = 0;

        foreach ($activos as $activo) {
            $cuotaMensual = round($activo['monto_adquisicion'] / $activo['vida_util_meses']);
            $totalDepreciacionMes += $cuotaMensual;
        }

        if ($totalDepreciacionMes <= 0) {
            throw new Exception("El cálculo de depreciación resultó en 0.");
        }

        $cuentaGastoDepreciacion = '690105';
        $cuentaDepreciacionAcum = '120304';

        $glosa = "Depreciación de Activos Fijos - Periodo " . date('m/Y', strtotime($fechaCierre));

        $resultadoAsiento = $this->contabilidadService->registrarAsientoDoble(
            'ACTIVOS_FIJOS',
            0,
            $cuentaGastoDepreciacion,
            $cuentaDepreciacionAcum,
            $totalDepreciacionMes,
            $glosa,
            $fechaCierre
        );

        return [
            'activos_procesados' => count($activos),
            'monto_total_depreciado' => $totalDepreciacionMes,
            'asiento_codigo' => $resultadoAsiento['codigo'] ?? null
        ];
    }
}