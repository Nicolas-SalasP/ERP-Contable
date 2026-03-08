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

    public function adjuntarPdfFactura(int $facturaId, array $archivo): array
    {
        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Error en la transmisión del archivo.");
        }
        if ($archivo['type'] !== 'application/pdf') {
            throw new Exception("El archivo debe ser un documento PDF.");
        }
        $directorioDestino = dirname(__DIR__, 2) . '/Public/uploads/facturas/';
        if (!is_dir($directorioDestino)) {
            mkdir($directorioDestino, 0755, true);
        }

        $nombreSeguro = 'factura_' . $facturaId . '_' . bin2hex(random_bytes(5)) . '.pdf';
        $rutaFinal = $directorioDestino . $nombreSeguro;
        $rutaRelativa = 'uploads/facturas/' . $nombreSeguro;

        if (move_uploaded_file($archivo['tmp_name'], $rutaFinal)) {
            $this->repoFactura->actualizarRutaPdf($facturaId, $rutaRelativa);

            return [
                'success' => true, 
                'mensaje' => 'PDF adjuntado correctamente',
                'archivo_pdf' => $rutaRelativa
            ];
        } else {
            throw new Exception("Error al guardar el archivo en el servidor.");
        }
    }

    public function procesarPagoConAnticipo(int $facturaId, array $datos)
    {
        $anticipoId = (int)$datos['anticipo_id'];
        $montoAplicar = (float)$datos['monto'];
        $this->repoFactura->beginTransaction();
        
        try {
            $factura = $this->repoFactura->obtenerFacturaPorId($facturaId);
            if (!$factura || $factura['estado'] === 'PAGADA') {
                throw new Exception("Factura inválida o ya se encuentra pagada por completo.");
            }

            $anticipo = $this->repoFactura->obtenerAnticipoParaActualizar($anticipoId);
            if (!$anticipo || (float)$anticipo['saldo_disponible'] < $montoAplicar) {
                throw new Exception("El anticipo no existe o no tiene saldo suficiente para este cruce.");
            }

            $codigoAsiento = $this->repoContabilidad->generarCodigoAsiento('MANUAL');
            $asientoId = $this->repoContabilidad->crearCabeceraAsiento(
                date('Y-m-d'), 
                "Cruce Anticipo Ref: {$anticipo['referencia']} a Fact. N° {$factura['numero_factura']}", 
                $codigoAsiento
            );
            $this->repoContabilidad->crearDetalleAvanzado($asientoId, '210101', $montoAplicar, 0.0);
            $this->repoContabilidad->crearDetalleAvanzado($asientoId, '110205', 0.0, $montoAplicar);

            $nuevoSaldoAnticipo = (float)$anticipo['saldo_disponible'] - $montoAplicar;
            $estadoAnticipo = $nuevoSaldoAnticipo <= 0 ? 'APLICADO' : 'VIGENTE';
            
            $this->repoFactura->actualizarSaldoAnticipo($anticipoId, $nuevoSaldoAnticipo, $estadoAnticipo);

            $this->repoFactura->registrarPago([
                'factura_id' => $facturaId,
                'cuenta_bancaria_empresa_id' => null,
                'fecha_pago' => date('Y-m-d'),
                'monto_pagado' => $montoAplicar,
                'metodo_pago' => 'Cruce Anticipo',
                'numero_operacion' => 'ANT-'.$anticipoId
            ], $asientoId);

            $totalPagado = $this->repoFactura->obtenerTotalPagadoFactura($facturaId);
            if ($totalPagado >= (float)$factura['monto_bruto']) {
                $this->repoFactura->marcarComoPagada($facturaId);
            }

            $this->repoFactura->commit();
            return ['success' => true, 'mensaje' => 'Factura cruzada con anticipo exitosamente.'];
            
        } catch (Exception $e) {
            $this->repoFactura->rollBack();
            throw $e;
        }
    }
}