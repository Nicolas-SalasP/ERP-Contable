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

        AuditoriaService::registrar('REGISTRAR_ASIENTO', 'asientos_contables', $asientoId, null, ['codigo' => $codigoUnico]);

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

        AuditoriaService::registrar('ANULAR_ASIENTO', 'asientos_contables', $asiento['id'], null, ['reversa_id' => $reversaId]);

        return [
            'success' => true,
            'mensaje' => "Asiento {$codigoUnico} reversado correctamente.",
            'nuevo_asiento' => $nuevoCodigo
        ];
    }

    public function obtenerSaldosLibroMayor(string $fechaInicio, string $fechaFin): array
    {
        AuditoriaService::registrar('CONSULTA_LIBRO_MAYOR', null, null, null, ['desde' => $fechaInicio, 'hasta' => $fechaFin]);

        $datos = $this->repositorio->getSaldosAgrupados($fechaInicio, $fechaFin);
        foreach ($datos as &$cuenta) {
            $saldo = (float) $cuenta['total_debe'] - (float) $cuenta['total_haber'];
            $cuenta['saldo_neto'] = abs($saldo);
            $cuenta['tipo_saldo'] = $saldo >= 0 ? 'DEUDOR' : 'ACREEDOR';
        }
        return $datos;
    }
}