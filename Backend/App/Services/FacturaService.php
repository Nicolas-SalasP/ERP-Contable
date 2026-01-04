<?php
namespace App\Services;

use App\Repositories\FacturaRepository;
use App\Services\ContabilidadService;
use Exception;

class FacturaService
{
    private $repositorio;
    private $contabilidad;

    const CUENTA_PROVEEDORES = '352130'; 
    const CUENTA_IVA_CREDITO = '110001';
    const CUENTA_GASTO_NETO = '500001'; 

    const TIPO_DOC_FACTURA = "26";

    public function __construct()
    {
        $this->repositorio = new FacturaRepository();
        $this->contabilidad = new ContabilidadService();
    }

    public function registrarCompra(array $datos)
    {
        if ($this->verificarDuplicidad($datos['proveedorId'], $datos['numeroFactura'])) {
            throw new Exception("FACTURA_DUPLICADA");
        }

        if (strtotime($datos['fechaVencimiento']) < strtotime($datos['fechaEmision'])) {
            throw new Exception("La fecha de vencimiento no puede ser anterior a la fecha de emisión.");
        }

        $bruto = floatval($datos['montoBruto']);
        $iva = floatval($datos['montoIva']);
        $neto = floatval($datos['montoNeto']);

        if (abs(($neto + $iva) - $bruto) > 1) {
            throw new Exception("Error: El Neto + IVA no cuadra con el Bruto informado.");
        }

        if ($datos['tieneIva'] && $iva >= $bruto) {
            throw new Exception("El IVA no puede ser mayor o igual al total.");
        }
        $anioFiscal = date('y', strtotime($datos['fechaEmision']));
        $prefijo = $anioFiscal . self::TIPO_DOC_FACTURA;

        $ultimoCodigo = $this->repositorio->getLastCodigoByPrefix($prefijo);
        $nuevoCodigo = $ultimoCodigo ? $ultimoCodigo + 1 : intval($prefijo . '0000');

        $datos['codigoUnico'] = $nuevoCodigo;

        try {
            $this->repositorio->beginTransaction();

            $facturaId = $this->repositorio->create($datos);

            $this->contabilidad->registrarAsiento(
                $facturaId,
                self::CUENTA_PROVEEDORES,
                0,
                $bruto
            );

            if ($datos['tieneIva'] && $iva > 0) {
                $this->contabilidad->registrarAsiento(
                    $facturaId,
                    self::CUENTA_IVA_CREDITO,
                    $iva,
                    0
                );
            }

            $this->contabilidad->registrarAsiento(
                $facturaId,
                self::CUENTA_GASTO_NETO,
                $neto,
                0
            );

            $this->repositorio->commit();

            return [
                'id' => $facturaId,
                'codigo' => $nuevoCodigo
            ];

        } catch (Exception $e) {
            $this->repositorio->rollBack();
            throw $e;
        }
    }

    public function anularDocumento($codigo, $motivo)
    {
        $original = $this->repositorio->getByCodigoUnico($codigo);

        if (!$original)
            throw new Exception("El documento no existe.");
        if ($original['estado'] === 'ANULADA')
            throw new Exception("Ya está anulada.");

        $anio = date('y');
        $prefijo = $anio . self::TIPO_DOC_FACTURA;
        $ultimo = $this->repositorio->getLastCodigoByPrefix($prefijo);
        $nuevoCodigo = $ultimo ? $ultimo + 1 : intval($prefijo . '0000');

        try {
            $this->repositorio->beginTransaction();

            $datosReverso = [
                'codigoUnico' => $nuevoCodigo,
                'proveedorId' => $original['proveedor_id'],
                'cuentaBancariaId' => $original['cuenta_bancaria_id'],
                'numeroFactura' => $original['numero_factura'] . " (NULA)",
                'fechaEmision' => date('Y-m-d'),
                'fechaVencimiento' => date('Y-m-d'),
                'montoBruto' => -1 * abs($original['monto_bruto']),
                'montoNeto' => -1 * abs($original['monto_neto']),
                'montoIva' => -1 * abs($original['monto_iva']),
                'motivoCorreccion' => "ANULACIÓN REF: $codigo. " . $motivo,
                'tieneIva' => ($original['monto_iva'] > 0)
            ];

            $facturaId = $this->repositorio->create($datosReverso);

            $this->contabilidad->registrarAsiento(
                $facturaId,
                self::CUENTA_PROVEEDORES,
                0,
                $datosReverso['montoBruto']
            );

            if ($datosReverso['tieneIva']) {
                $this->contabilidad->registrarAsiento(
                    $facturaId,
                    self::CUENTA_IVA_CREDITO,
                    $datosReverso['montoIva'],
                    0
                );
            }

            $this->contabilidad->registrarAsiento(
                $facturaId,
                self::CUENTA_GASTO_NETO,
                $datosReverso['montoNeto'],
                0
            );

            $this->repositorio->marcarComoAnulada($original['id']);

            $this->repositorio->commit();

            return ['id' => $facturaId, 'codigo' => $nuevoCodigo];

        } catch (Exception $e) {
            $this->repositorio->rollBack();
            throw $e;
        }
    }

    public function verificarDuplicidad($proveedorId, $numeroFactura)
    {
        return (bool) $this->repositorio->existeFactura($proveedorId, $numeroFactura);
    }
}