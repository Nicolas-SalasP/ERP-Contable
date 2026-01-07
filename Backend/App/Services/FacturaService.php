<?php
namespace App\Services;

use App\Repositories\FacturaRepository;
use App\Repositories\ContabilidadRepository;
use Exception;

class FacturaService
{
    private $repoFactura;
    private $repoContabilidad;

    const COD_PROVEEDORES = '210101';
    const COD_IVA_CREDITO = '110001';
    const COD_GASTO_GEN = '500001';

    const TIPO_DOC_FACTURA = '26';

    public function __construct()
    {
        $this->repoFactura = new FacturaRepository();
        $this->repoContabilidad = new ContabilidadRepository();
    }

    public function obtenerHistorialPaginado($filtroProveedor, $filtroNumero, $filtroEstado, $limit, $offset)
    {
        $data = $this->repoFactura->buscarHistorial($filtroProveedor, $filtroNumero, $filtroEstado, $limit, $offset);
        $total = $this->repoFactura->contarHistorial($filtroProveedor, $filtroNumero, $filtroEstado);

        return [
            'data' => $data,
            'total' => $total
        ];
    }

    public function registrarCompra($datos)
    {
        if ($this->repoFactura->existeFactura($datos['proveedorId'], $datos['numeroFactura'])) {
            throw new Exception("FACTURA_DUPLICADA");
        }
        $anioFiscal = date('y', strtotime($datos['fechaEmision']));
        $prefijo = $anioFiscal . self::TIPO_DOC_FACTURA;

        $nuevoCodigo = $this->repoFactura->generarCodigoSistema($prefijo);
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

        $this->repoFactura->beginTransaction();

        try {
            $facturaId = $this->repoFactura->create($datos);

            $asientoId = $this->repoContabilidad->crearAsiento([
                'codigo_unico' => $nuevoCodigo,
                'fecha' => $datos['fechaEmision'],
                'glosa' => "Compra Fac. {$datos['numeroFactura']} - {$datos['proveedorNombre']}",
                'tipo_asiento' => 'egreso',
                'origen_modulo' => 'COMPRA',
                'origen_id' => $facturaId
            ]);

            $this->repoContabilidad->crearDetalle($asientoId, self::COD_PROVEEDORES, 0, $montoBruto);

            if ($tieneIva && $montoIva > 0) {
                $this->repoContabilidad->crearDetalle($asientoId, self::COD_IVA_CREDITO, $montoIva, 0);
            }

            $this->repoContabilidad->crearDetalle($asientoId, self::COD_GASTO_GEN, $montoNeto, 0);

            $this->repoFactura->commit();

            return [
                'id' => $facturaId,
                'codigo' => $nuevoCodigo,
                'asiento_id' => $asientoId
            ];

        } catch (Exception $e) {
            $this->repoFactura->rollBack();
            throw $e;
        }
    }

    public function verificarDuplicidad($proveedorId, $numeroFactura)
    {
        return (bool) $this->repoFactura->existeFactura($proveedorId, $numeroFactura);
    }

    public function obtenerAsientoPorFactura($facturaId)
    {
        $resultado = $this->repoContabilidad->obtenerAsientoPorOrigen('COMPRA', $facturaId);

        if (empty($resultado)) {
            throw new Exception("Esta factura no tiene un asiento contable asociado.");
        }

        return $resultado;
    }
}