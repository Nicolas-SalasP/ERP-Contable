<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\ContabilidadRepository;
use App\Services\AuditoriaService;
use Exception;

class ContabilidadService
{

    private ContabilidadRepository $repositorio;

    public function __construct()
    {
        $this->repositorio = new ContabilidadRepository();
    }

    public function registrarAsiento(int $referenciaId, string $codigoCuenta, float $debe, float $haber, string $glosa = ''): array
    {

        $codigoUnico = $this->repositorio->generarCodigoAsiento();

        if (empty($glosa))
            $glosa = "Reg. Manual Ref: " . $referenciaId;
        $asientoId = $this->repositorio->crearAsiento([
            'codigo_unico' => $codigoUnico,
            'origen_id' => $referenciaId,
            'origen_modulo' => 'MANUAL',
            'cuenta_codigo' => $codigoCuenta,
            'glosa' => $glosa,
            'debe' => $debe,
            'haber' => $haber
        ]);

        // AuditoriaService::registrar('REGISTRAR_ASIENTO', 'asientos_contables', $asientoId, null, ['codigo' => $codigoUnico]);

        return ['id' => $asientoId, 'codigo' => $codigoUnico];
    }

    public function registrarAsientoDoble(string $modulo, int $referenciaId, string $cuentaDebe, string $cuentaHaber, float $monto, string $glosa, string $fechaCierre): array
    {
        $prefijo = date('y', strtotime($fechaCierre)) . "12";

        $codigoUnico = $this->repositorio->generarCodigoPersonalizado($prefijo);

        $asientoId = $this->repositorio->registrarPartidaDobleReal(
            $codigoUnico,
            $fechaCierre,
            $glosa,
            $modulo,
            $referenciaId,
            $cuentaDebe,
            $cuentaHaber,
            $monto
        );

        // AuditoriaService::registrar('REGISTRAR_ASIENTO_DOBLE', 'asientos_contables', $asientoId, null, ['codigo' => $codigoUnico, 'modulo' => $modulo]);

        return ['id' => $asientoId, 'codigo' => $codigoUnico];
    }

    public function anularAsiento(int $codigoUnico, string $motivo): array
    {
        $asiento = $this->repositorio->getByCodigoUnico($codigoUnico);
        if (!$asiento)
            throw new Exception("El asiento contable {$codigoUnico} no existe.");
        if ($asiento['origen_modulo'] !== 'MANUAL') {
            throw new Exception("Este asiento pertenece al módulo {$asiento['origen_modulo']} (ID Ref: {$asiento['origen_id']}). Debe anular el documento original en dicho módulo.");
        }

        $detalles = $this->repositorio->obtenerDetalles($asiento['id']);
        if (empty($detalles))
            throw new Exception("El asiento no tiene detalles para reversar.");

        $nuevoCodigo = $this->repositorio->generarCodigoAsiento();

        $reversaId = $this->repositorio->crearAsiento([
            'codigo_unico' => $nuevoCodigo,
            'fecha' => date('Y-m-d'),
            'glosa' => "NULIDAD Asiento {$codigoUnico} - {$motivo}",
            'tipo_asiento' => 'traspaso',
            'origen_modulo' => 'MANUAL',
            'origen_id' => $asiento['id'],
            'cuenta_codigo' => $detalles[0]['cuenta_contable'],
            'debe' => 0,
            'haber' => 0
        ]);

        foreach ($detalles as $det) {
            $nuevoDebe = (float) $det['haber'];
            $nuevoHaber = (float) $det['debe'];
            $this->repositorio->crearDetalle($reversaId, $det['cuenta_contable'], $nuevoDebe, $nuevoHaber);
        }

        // AuditoriaService::registrar('ANULAR_ASIENTO', 'asientos_contables', $asiento['id'], null, ['reversa_id' => $reversaId]);

        return [
            'success' => true,
            'mensaje' => "Asiento {$codigoUnico} reversado correctamente.",
            'nuevo_asiento' => $nuevoCodigo
        ];
    }

    public function obtenerSaldosLibroMayor(string $fechaInicio, string $fechaFin): array
    {
        // AuditoriaService::registrar('CONSULTA_LIBRO_MAYOR', null, null, null, ['desde' => $fechaInicio, 'hasta' => $fechaFin]);

        $datos = $this->repositorio->getSaldosAgrupados($fechaInicio, $fechaFin);
        foreach ($datos as &$cuenta) {
            $saldo = (float) $cuenta['total_debe'] - (float) $cuenta['total_haber'];
            $cuenta['saldo_neto'] = abs($saldo);
            $cuenta['tipo_saldo'] = $saldo >= 0 ? 'DEUDOR' : 'ACREEDOR';
        }
        return $datos;
    }

    public function obtenerLibroDiario(array $filtros): array
    {
        $fechaInicio = $filtros['desde'] ?? date('Y-m-01');
        $fechaFin = $filtros['hasta'] ?? date('Y-m-t');
        $cuenta = $filtros['cuenta'] ?? null;

        return $this->repositorio->obtenerMovimientosDiario($fechaInicio, $fechaFin, $cuenta);
    }

    public function obtenerPlanCuentas(): array
    {
        return $this->repositorio->obtenerTodasLasCuentas();
    }

    public function obtenerAsientoPorId(int $id): array
    {
        return $this->repositorio->obtenerAsientoCompleto($id);
    }

    public function actualizarCuenta(int $id, array $datos): array
    {
        $this->repositorio->actualizarCuenta($id, $datos);
        return ['success' => true, 'mensaje' => 'Cuenta actualizada correctamente.'];
    }

    public function registrarAsientoManualAvanzado(array $datos): array
    {
        $detalles = $datos['detalles'] ?? [];
        if (empty($detalles) || count($detalles) < 2) {
            throw new Exception("El asiento debe tener al menos dos líneas contables.");
        }
        $totalDebe = 0.0;
        $totalHaber = 0.0;
        foreach ($detalles as $det) {
            $totalDebe += (float) ($det['debe'] ?? 0);
            $totalHaber += (float) ($det['haber'] ?? 0);
        }
        if (round($totalDebe, 2) !== round($totalHaber, 2)) {
            throw new Exception("El asiento está descuadrado. Diferencia detectada. (Debe: $totalDebe, Haber: $totalHaber).");
        }
        if (round($totalDebe, 2) <= 0) {
            throw new Exception("El asiento no puede tener valor cero.");
        }

        $this->repositorio->beginTransaction();
        try {
            $fecha = $datos['fecha'] ?? date('Y-m-d');
            $glosa = $datos['glosa'] ?? 'Asiento Manual Avanzado';
            
            $codigoUnico = $this->repositorio->generarCodigoAsiento('MANUAL');
            
            $asientoId = $this->repositorio->crearCabeceraAsiento($fecha, $glosa, $codigoUnico);

            foreach ($detalles as $fila) {
                $this->repositorio->crearDetalleAvanzado(
                    $asientoId,
                    (string) $fila['cuenta_codigo'],
                    (float) ($fila['debe'] ?? 0),
                    (float) ($fila['haber'] ?? 0),
                    isset($fila['centro_costo_id']) ? (int) $fila['centro_costo_id'] : null,
                    $fila['empleado_nombre'] ?? null
                );
            }
            // AuditoriaService::registrar('REGISTRAR_ASIENTO_AVANZADO', 'asientos_contables', $asientoId, null, ['codigo' => $codigoUnico]);

            $this->repositorio->commit();

            return ['success' => true, 'id' => $asientoId, 'codigo' => $codigoUnico];

        } catch (Exception $e) {
            $this->repositorio->rollBack();
            throw $e;
        }
    }
}