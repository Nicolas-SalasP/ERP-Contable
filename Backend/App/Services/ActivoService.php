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

    public function obtenerCuentasActivos() {
        return $this->activoRepository->getCuentasActivoFijo();
    }

    // ======================================================================
    // ACTIVOS FIJOS DIRECTOS
    // ======================================================================
    public function crearDesdeFactura($datos, $empresaId)
    {
        if ($datos['monto_adquisicion'] <= 0) {
            throw new Exception("El monto de adquisición debe ser mayor a cero.");
        }
        return $this->activoRepository->crearActivoFijo($datos, $empresaId);
    }

    public function obtenerTodos() { return $this->activoRepository->getActivos(); }
    public function obtenerCategoriasSII() { return $this->activoRepository->getCategoriasSii(); }
    public function obtenerPendientesDeContabilidad() { return $this->activoRepository->obtenerPendientesDeContabilidad(); }

    public function activarDepreciacion($id, $datos)
    {
        if (empty($datos['categoria_sii_id']) || empty($datos['tipo_depreciacion']) || empty($datos['fecha_activacion'])) {
            throw new Exception("Faltan datos obligatorios para activar el activo.");
        }
        $res = $this->activoRepository->activarActivo($id, $datos);
        if (!$res) throw new Exception("No se pudo activar el activo. Verifique los permisos.");
        return true;
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

        if ($totalDepreciacionMes <= 0) throw new Exception("El cálculo de depreciación resultó en 0.");

        $cuentaGastoDepreciacion = '690105';
        $cuentaDepreciacionAcum = '120304';
        $glosa = "Depreciación de Activos Fijos - Periodo " . date('m/Y', strtotime($fechaCierre));

        $resultadoAsiento = $this->contabilidadService->registrarAsientoDoble(
            'ACTIVOS_FIJOS', 0, $cuentaGastoDepreciacion, $cuentaDepreciacionAcum,
            $totalDepreciacionMes, $glosa, $fechaCierre
        );

        return [
            'activos_procesados' => count($activos),
            'monto_total_depreciado' => $totalDepreciacionMes,
            'asiento_codigo' => $resultadoAsiento['codigo'] ?? null
        ];
    }

    // ======================================================================
    // PROYECTOS DE ACTIVOS (En Construcción)
    // ======================================================================
    public function crearProyecto(array $data): array {
        try {
            $id = $this->activoRepository->crearProyectoActivo($data);
            return ['success' => true, 'id_proyecto' => $id, 'message' => 'Proyecto creado en estado EN_CONSTRUCCION'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error al crear proyecto: ' . $e->getMessage()];
        }
    }

    public function obtenerTodosProyectos() {
        return $this->activoRepository->obtenerProyectos();
    }

    public function vincularFacturaProyecto(int $proyectoId, int $facturaId, float $monto): array {
        try {
            $this->activoRepository->beginTransaction();
            
            $proyecto = $this->activoRepository->getProyectoById($proyectoId);
            if (!$proyecto || $proyecto['estado'] !== 'EN_CONSTRUCCION') {
                throw new Exception("El proyecto no existe o ya no está en construcción.");
            }

            $this->activoRepository->vincularFacturaAProyecto($proyectoId, $facturaId, $monto);
            $glosa = "Imputación Fact. {$facturaId} a Proyecto: {$proyecto['nombre']}";
            $this->contabilidadService->registrarAsientoDoble(
                'ACTIVOS_FIJOS', $facturaId, '120199', '690199', $monto, $glosa, date('Y-m-d')
            );

            $this->activoRepository->commit();
            return ['success' => true, 'message' => 'Factura vinculada y contabilizada correctamente.'];
        } catch (Exception $e) {
            $this->activoRepository->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function activarProyecto(int $proyectoId): array {
        try {
            $this->activoRepository->beginTransaction();
            
            $proyecto = $this->activoRepository->getProyectoById($proyectoId);
            if (!$proyecto || $proyecto['estado'] !== 'EN_CONSTRUCCION') {
                throw new Exception("El proyecto no está en condiciones de ser activado.");
            }

            $fechaActivacion = date('Y-m-d');
            $this->activoRepository->cambiarEstadoProyecto($proyectoId, 'ACTIVO_OPERATIVO', $fechaActivacion);
            $glosa = "Capitalización de Proyecto: {$proyecto['nombre']}";
            $this->contabilidadService->registrarAsientoDoble(
                'ACTIVOS_FIJOS', $proyectoId, '120103', '120199', $proyecto['valor_total_original'], $glosa, $fechaActivacion
            );

            $this->activoRepository->commit();
            return ['success' => true, 'message' => 'Proyecto activado. Costos capitalizados.'];
        } catch (Exception $e) {
            $this->activoRepository->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function ejecutarDepreciacionMensualProyectos($fechaCierre): array {
        try {
            $this->activoRepository->beginTransaction();
            $proyectos = $this->activoRepository->getProyectosParaDepreciar();
            $activosProcesados = 0;
            $montoTotal = 0;

            foreach ($proyectos as $p) {
                $cuotaBase = $p['valor_total_original'] / $p['vida_util_meses'];
                $valorRestante = $p['valor_total_original'] - $p['depreciacion_acumulada'];
                $cuotaAplicar = round(min($cuotaBase, $valorRestante), 2);

                if ($cuotaAplicar > 0) {
                    $glosa = "Deprec. Proyecto {$p['nombre']} - Periodo " . date('m/Y', strtotime($fechaCierre));
                    $resAsiento = $this->contabilidadService->registrarAsientoDoble(
                        'ACTIVOS_FIJOS', $p['id_proyecto'], '690105', '120304', $cuotaAplicar, $glosa, $fechaCierre
                    );

                    $this->activoRepository->registrarDepreciacionMensualProyecto($p['id_proyecto'], $cuotaAplicar, $resAsiento['asiento_id'] ?? 0, $fechaCierre);
                    $activosProcesados++;
                    $montoTotal += $cuotaAplicar;
                }
            }

            $this->activoRepository->commit();
            return ['success' => true, 'message' => "Se depreciaron {$activosProcesados} proyectos ($montoTotal CLP)."];
        } catch (Exception $e) {
            $this->activoRepository->rollBack();
            return ['success' => false, 'message' => 'Error en depreciación de proyectos: ' . $e->getMessage()];
        }
    }

    public function darDeBajaProyecto(int $proyectoId, string $motivo, float $montoVenta = 0): array {
        try {
            $this->activoRepository->beginTransaction();
            $proyecto = $this->activoRepository->getProyectoById($proyectoId);
            
            $estadoNuevo = ($motivo === 'VENTA') ? 'VENDIDO' : 'DADO_DE_BAJA';
            $this->activoRepository->cambiarEstadoProyecto($proyectoId, $estadoNuevo);
            
            // Aquí iría la llamada al AccountingService para el reverso complejo de 4 o 5 cuentas
            // $this->contabilidadService->generarAsientoBajaActivo(...);

            $this->activoRepository->commit();
            return ['success' => true, 'message' => "Proyecto de Activo {$estadoNuevo} correctamente."];
        } catch (Exception $e) {
            $this->activoRepository->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function obtenerFacturasDisponiblesProyecto() {
        return $this->activoRepository->obtenerFacturasParaProyectos();
    }
    public function obtenerAnalisisProyecto(int $id) {
        return $this->activoRepository->getDetalleAnaliticoProyecto($id);
    }
}