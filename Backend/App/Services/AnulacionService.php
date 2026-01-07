<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\ContabilidadRepository;
use App\Repositories\FacturaRepository;
use Exception;

class AnulacionService
{
    private ContabilidadRepository $contabilidadRepo;
    private FacturaRepository $facturaRepo;

    public function __construct()
    {
        $this->contabilidadRepo = new ContabilidadRepository();
        $this->facturaRepo = new FacturaRepository();
    }

    public function buscarDocumentoPorCodigo(string $codigo)
    {
        $factura = $this->facturaRepo->getByCodigoUnico($codigo);
        if ($factura) {
            $asiento = $this->contabilidadRepo->obtenerAsientoPorOrigen('COMPRA', (int)$factura['id']);
            return [
                'tipo_origen' => 'FACTURA',
                'id' => $factura['id'],
                'codigo' => $factura['codigo_unico'],
                'fecha' => $factura['fecha_emision'],
                'descripcion' => "Factura N° " . $factura['numero_factura'],
                'entidad' => 'Proveedor ID: ' . $factura['proveedor_id'], 
                'monto' => $factura['monto_bruto'],
                'estado' => $factura['estado'],
                'asiento_detalle' => $asiento['detalles'] ?? []
            ];
        }

        $asientoManual = $this->contabilidadRepo->getByCodigoUnico((int)$codigo); 
        if ($asientoManual) {
            $detalles = $this->contabilidadRepo->obtenerDetalles((int)$asientoManual['id']);
            return [
                'tipo_origen' => 'MANUAL',
                'id' => $asientoManual['id'],
                'codigo' => $asientoManual['codigo_unico'],
                'fecha' => $asientoManual['fecha'],
                'descripcion' => $asientoManual['glosa'],
                'entidad' => 'Asiento Manual',
                'monto' => 0, 
                'estado' => strpos($asientoManual['glosa'], '[ANULADA]') !== false ? 'ANULADA' : 'VIGENTE',
                'asiento_detalle' => $detalles
            ];
        }

        return null;
    }

    public function anularDocumento(array $datos): array
    {
        $codigo = $datos['codigo'];
        $motivo = $datos['motivo'];
        
        $documento = $this->buscarDocumentoPorCodigo($codigo);
        if (!$documento) {
            throw new Exception("Documento no encontrado.");
        }

        if ($documento['estado'] === 'ANULADA') {
            throw new Exception("El documento ya se encuentra anulado.");
        }

        $this->contabilidadRepo->beginTransaction();

        try {
            $nuevoAsientoId = 0;

            if ($documento['tipo_origen'] === 'FACTURA') {
                $this->facturaRepo->marcarComoAnulada((int)$documento['id']);
                $asientoOriginal = $this->contabilidadRepo->obtenerAsientoPorOrigen('COMPRA', (int)$documento['id']);
                
                if (!empty($asientoOriginal['detalles'])) {
                    $datosCabecera = [
                        'glosa' => "REVERSO NULO: " . $documento['descripcion'] . ". Motivo: " . $motivo,
                        'tipo_asiento' => 'anulacion',
                        'origen_modulo' => 'COMPRA', 
                        'origen_id' => $documento['id']
                    ];
                    $nuevoAsientoId = $this->contabilidadRepo->crearAsiento($datosCabecera);

                    foreach ($asientoOriginal['detalles'] as $linea) {
                        $this->contabilidadRepo->crearDetalle(
                            $nuevoAsientoId,
                            (string)$linea['cuenta_contable'],
                            (float)$linea['haber'], 
                            (float)$linea['debe']   
                        );
                    }
                }

            } else {
                $this->contabilidadRepo->anularAsientoManual((int)$documento['id']);
                $datosCabecera = [
                    'glosa' => "REVERSO MANUAL: " . $documento['descripcion'] . ". Motivo: " . $motivo,
                    'tipo_asiento' => 'anulacion',
                    'origen_modulo' => 'MANUAL',
                    'origen_id' => $documento['id']
                ];
                $nuevoAsientoId = $this->contabilidadRepo->crearAsiento($datosCabecera);

                foreach ($documento['asiento_detalle'] as $linea) {
                    $this->contabilidadRepo->crearDetalle(
                        $nuevoAsientoId,
                        (string)$linea['cuenta_contable'],
                        (float)$linea['haber'],
                        (float)$linea['debe']
                    );
                }
            }

            $this->contabilidadRepo->commit();

            return [
                'success' => true,
                'nuevo_asiento_id' => $nuevoAsientoId
            ];

        } catch (Exception $e) {
            $this->contabilidadRepo->rollBack();
            throw $e;
        }
    }
}