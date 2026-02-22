<?php
namespace App\Services;

use App\Repositories\FacturaRepository;
use App\Repositories\ContabilidadRepository;
use App\Services\ContabilidadService; // <-- IMPORTAMOS EL SERVICIO
use Exception;

class FacturaService
{
    private $repoFactura;
    private $repoContabilidad;
    private $servicioContabilidad;

    const COD_PROVEEDORES = '210101';
    const COD_IVA_CREDITO = '110001';
    const COD_CUENTA_PUENTE = '690199';

    const TIPO_DOC_FACTURA = '26';

    public function __construct()
    {
        $this->repoFactura = new FacturaRepository();
        $this->repoContabilidad = new ContabilidadRepository();
        $this->servicioContabilidad = new ContabilidadService();
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

        $cuentaNeto = !empty($datos['cuentaDestino']) ? $datos['cuentaDestino'] : self::COD_CUENTA_PUENTE;

        $this->repoFactura->beginTransaction();

        try {
            $facturaId = $this->repoFactura->create($datos);
            $fechaContable = !empty($datos['fechaContable']) ? $datos['fechaContable'] : $datos['fechaEmision'];

            $asientoId = $this->repoContabilidad->crearAsiento([
                'codigo_unico' => $nuevoCodigo,
                'fecha' => $fechaContable,
                'glosa' => "Compra Fac. {$datos['numeroFactura']} - {$datos['proveedorNombre']}",
                'tipo_asiento' => 'egreso',
                'origen_modulo' => 'COMPRA',
                'origen_id' => $facturaId
            ]);

            $this->repoContabilidad->crearDetalle($asientoId, self::COD_PROVEEDORES, 0, $montoBruto);

            if ($tieneIva && $montoIva > 0) {
                $this->repoContabilidad->crearDetalle($asientoId, self::COD_IVA_CREDITO, $montoIva, 0);
            }

            $this->repoContabilidad->crearDetalle($asientoId, $cuentaNeto, $montoNeto, 0);

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

    public function reclasificarDentroDelMismoAsiento($facturaId, $datos)
    {
        $nuevaCuenta = $datos['nuevaCuenta'];
        $fechaReclasificacion = $datos['fechaContableCambio'];
        $motivo = $datos['nuevaGlosa'] ?? 'Reclasificación';
        $asientoOriginal = $this->repoContabilidad->obtenerAsientoPorOrigen('COMPRA', (int) $facturaId);

        if (!$asientoOriginal || !isset($asientoOriginal['cabecera'])) {
            throw new Exception("No existe asiento original asociado a esta factura.");
        }

        $asientoId = (int) $asientoOriginal['cabecera']['id'];
        $cuentaOriginal = null;
        $montoNeto = 0;

        foreach ($asientoOriginal['detalles'] as $det) {
            if ($det['cuenta_contable'] !== self::COD_PROVEEDORES && $det['cuenta_contable'] !== self::COD_IVA_CREDITO) {
                if ((float) $det['debe'] > 0) {
                    $cuentaOriginal = $det['cuenta_contable'];
                    $montoNeto = (float) $det['debe'];
                    break;
                }
            }
        }

        if (!$cuentaOriginal || $montoNeto <= 0) {
            throw new Exception("No se encontró una imputación neta válida para reclasificar dentro del asiento.");
        }

        $this->repoFactura->beginTransaction();

        try {
            $this->repoContabilidad->agregarDetalleReclasificacion(
                $asientoId,
                $cuentaOriginal,
                $fechaReclasificacion,
                "SALIDA: {$motivo}",
                0,
                $montoNeto
            );
            $this->repoContabilidad->agregarDetalleReclasificacion(
                $asientoId,
                $nuevaCuenta,
                $fechaReclasificacion,
                "ENTRADA: {$motivo}",
                $montoNeto,
                0
            );

            $glosaActualizada = $asientoOriginal['cabecera']['glosa'] . " | [Reclasificado: {$motivo}]";
            $this->repoContabilidad->actualizarGlosaCabecera($asientoId, $glosaActualizada);

            $this->repoFactura->commit();
            return $this->repoContabilidad->obtenerAsientoPorOrigen('COMPRA', (int) $facturaId);

        } catch (Exception $e) {
            $this->repoFactura->rollBack();
            throw $e;
        }
    }

    public function procesarPagoFactura(int $facturaId, array $datos)
    {
        $factura = $this->repoFactura->obtenerFacturaPorId($facturaId);
        if (!$factura) {
            throw new Exception("La factura no existe.");
        }
        if ($factura['estado'] === 'PAGADA') {
            throw new Exception("Esta factura ya se encuentra pagada.");
        }

        $cuentaPasivoProveedores = '210101';
        $cuentaActivoBanco = $datos['cuenta_contable_banco'];

        $glosa = "Pago Fact. N° " . $factura['numero_factura'] . " - OP: " . ($datos['numero_operacion'] ?? 'S/N');

        $resultadoAsiento = $this->servicioContabilidad->registrarAsientoDoble(
            'PAGO_FACTURA',
            $facturaId,
            $cuentaPasivoProveedores,
            $cuentaActivoBanco,
            $datos['monto_pagado'],
            $glosa,
            $datos['fecha_pago']
        );

        $datos['factura_id'] = $facturaId;
        $this->repoFactura->registrarPago($datos, $resultadoAsiento['id']);
        $this->repoFactura->marcarComoPagada($facturaId);

        return [
            'success' => true,
            'mensaje' => 'Pago procesado y contabilizado correctamente.',
            'asiento_codigo' => $resultadoAsiento['codigo']
        ];
    }
}