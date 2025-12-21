<?php
namespace App\Services;

use App\Repositories\FacturaRepository;
use Exception;

class AccountingService {
    private $repository;

    public function __construct() {
        $this->repository = new FacturaRepository();
    }

    public function registrarFacturaCompra(array $data) {
        // 1. VALIDACIÓN DE DUPLICADOS (Nueva lógica)
        if ($this->repository->existeFactura($data['proveedorId'], $data['numeroFactura'])) {
            throw new Exception("DUPLICATE_INVOICE"); // Lanzamos código específico
        }

        // ... (resto de validaciones de fecha y montos igual que antes) ...
        if ($data['fechaVencimiento'] < $data['fechaEmision']) {
            throw new Exception("La fecha de vencimiento no puede ser anterior a la fecha de emisión.");
        }
        
        // ... (Lógica de Smart ID igual que antes) ...
        $anioFiscal = date('y', strtotime($data['fechaEmision']));
        $tipoDoc = "26";
        $prefijo = $anioFiscal . $tipoDoc;
        $ultimoCodigo = $this->repository->getLastCodigoByPrefix($prefijo);
        
        if ($ultimoCodigo) {
            $nuevoCodigo = $ultimoCodigo + 1;
        } else {
            $nuevoCodigo = intval($prefijo . '0000');
        }
        $data['codigoUnico'] = $nuevoCodigo;

        // ... (Transacción y guardado igual que antes) ...
        try {
            $this->repository->beginTransaction();
            $facturaId = $this->repository->create($data);
            
            // Asientos (Igual que antes)
            $bruto = floatval($data['montoBruto']);
            $iva = floatval($data['montoIva']);
            $neto = floatval($data['montoNeto']);
            
            $this->repository->createAsiento($facturaId, '352130', 0, $bruto); // Haber
            if ($data['tieneIva'] && $iva > 0) $this->repository->createAsiento($facturaId, '110001', $iva, 0); // Debe
            $this->repository->createAsiento($facturaId, '500001', $neto, 0); // Debe

            $this->repository->commit();
            return ['id' => $facturaId, 'codigo' => $nuevoCodigo];
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    // --- NUEVO MÓDULO: REVERSAR (ANULAR) DOCUMENTO ---
    public function anularDocumento($codigoAAnular, $motivo) {
        // 1. Buscar el documento original
        $original = $this->repository->getByCodigoUnico($codigoAAnular);
        
        if (!$original) {
            throw new Exception("El documento con código $codigoAAnular no existe.");
        }

        if ($original['estado'] === 'ANULADA') {
            throw new Exception("Este documento ya fue anulado previamente.");
        }

        // 2. Generar Nuevo Smart ID para la anulación (Es un documento nuevo)
        // Usamos la fecha de HOY para el reverso
        $anioFiscal = date('y'); 
        $tipoDoc = "26"; // Mismo tipo para que quede en el mismo libro
        $prefijo = $anioFiscal . $tipoDoc;
        $ultimoCodigo = $this->repository->getLastCodigoByPrefix($prefijo);
        $nuevoCodigo = $ultimoCodigo ? $ultimoCodigo + 1 : intval($prefijo . '0000');

        try {
            $this->repository->beginTransaction();

            // 3. Crear la Data "Espejo" (Negativa)
            $dataReverso = [
                'codigoUnico' => $nuevoCodigo,
                'proveedorId' => $original['proveedor_id'],
                'cuentaBancariaId' => $original['cuenta_bancaria_id'],
                'numeroFactura' => $original['numero_factura'] . " (NULA)", // Marca visual
                'fechaEmision' => date('Y-m-d'), // Fecha de hoy (fecha de anulación)
                'fechaVencimiento' => date('Y-m-d'),
                'montoBruto' => -1 * abs($original['monto_bruto']), // Negativo
                'montoNeto' => -1 * abs($original['monto_neto']),   // Negativo
                'montoIva' => -1 * abs($original['monto_iva']),     // Negativo
                'motivoCorreccion' => "ANULACIÓN REF: $codigoAAnular. " . $motivo
            ];

            // 4. Guardar Cabecera
            $facturaId = $this->repository->create($dataReverso);

            // 5. Generar Asientos Inversos (Mismas cuentas, montos negativos)
            // Al sumar el asiento original (100) con este (-100), el saldo es 0.
            
            // Facturas por Pagar (Haber negativo)
            $this->repository->createAsiento($facturaId, '352130', 0, $dataReverso['montoBruto']); 

            // IVA (Debe negativo)
            if ($original['monto_iva'] > 0) {
                $this->repository->createAsiento($facturaId, '110001', $dataReverso['montoIva'], 0);
            }

            // Gasto (Debe negativo)
            $this->repository->createAsiento($facturaId, '500001', $dataReverso['montoNeto'], 0);

            // 6. (Opcional) Marcar original como Anulada para que se vea tachada en listas
            $this->repository->marcarComoAnulada($original['id']);
            $this->repository->marcarComoAnulada($facturaId); // La anulación también nace anulada/cerrada

            $this->repository->commit();

            return ['id' => $facturaId, 'codigo' => $nuevoCodigo];

        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }
}