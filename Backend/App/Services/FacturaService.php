<?php
namespace App\Services;

use App\Repositories\FacturaRepository;
use Exception;

class FacturaService
{
    private $repository;
    const COD_PROVEEDORES = '210101';
    const COD_IVA_CREDITO = '110001';
    const COD_GASTO_GEN = '500001';

    const TIPO_DOC_FACTURA = '26';

    public function __construct()
    {
        $this->repository = new FacturaRepository();
    }

    public function registrarCompra($datos)
    {
        if ($this->repository->existeFactura($datos['proveedorId'], $datos['numeroFactura'])) {
            throw new Exception("FACTURA_DUPLICADA");
        }

        $anioFiscal = date('y', strtotime($datos['fechaEmision']));
        $prefijo = $anioFiscal . self::TIPO_DOC_FACTURA;

        $nuevoCodigo = $this->repository->generarCodigoSistema($prefijo);
        $datos['codigoUnico'] = $nuevoCodigo;

        $montoBruto = (float) $datos['montoBruto'];
        $tieneIva = filter_var($datos['tieneIva'], FILTER_VALIDATE_BOOLEAN);

        if ($tieneIva) {
            $montoIva = (float) ($datos['montoIva'] ?? 0);
            $montoNeto = (float) $datos['montoNeto'];
        } else {
            $montoIva = 0;
            $montoNeto = $montoBruto;
        }

        $this->repository->beginTransaction();

        try {
            $facturaId = $this->repository->create($datos);
            $asientoId = $this->repository->crearAsiento([
                'fecha' => $datos['fechaEmision'],
                'glosa' => "Compra Fac. {$datos['numeroFactura']} - {$datos['proveedorNombre']}",
                'origen_id' => $facturaId
            ]);
            $this->repository->crearDetalleAsiento($asientoId, self::COD_PROVEEDORES, 0, $montoBruto); // Haber

            if ($tieneIva && $montoIva > 0) {
                $this->repository->crearDetalleAsiento($asientoId, self::COD_IVA_CREDITO, $montoIva, 0); // Debe
            }

            $this->repository->crearDetalleAsiento($asientoId, self::COD_GASTO_GEN, $montoNeto, 0); // Debe

            $this->repository->commit();

            return [
                'id' => $facturaId,
                'codigo' => $nuevoCodigo,
                'asiento_id' => $asientoId
            ];

        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function verificarDuplicidad($proveedorId, $numeroFactura)
    {
        return (bool) $this->repository->existeFactura($proveedorId, $numeroFactura);
    }

    public function buscarHistorial($termino, $numFactura = '', $estado = '')
    {
        return $this->repository->buscarHistorial($termino, $numFactura, $estado);
    }

    public function obtenerAsientoPorFactura($facturaId)
    {
        $cabecera = $this->repository->obtenerCabeceraAsientoPorFactura($facturaId);

        if (!$cabecera) {
            throw new Exception("Esta factura no tiene un asiento contable asociado.");
        }

        $detalles = $this->repository->obtenerDetallesAsiento($cabecera['id']);

        return [
            'cabecera' => $cabecera,
            'detalles' => $detalles
        ];
    }

    public function anularDocumento($codigo, $motivo)
    {
        $factura = $this->repository->getByCodigoUnico($codigo);
        if (!$factura)
            throw new Exception("Factura no encontrada");

        $this->repository->marcarComoAnulada($factura['id']);
        return true;
    }
}