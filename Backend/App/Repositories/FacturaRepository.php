<?php
namespace App\Repositories;

use App\Config\Database;
use PDO;

class FacturaRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function beginTransaction() {
        $this->db->beginTransaction();
    }

    public function commit() {
        $this->db->commit();
    }

    public function rollBack() {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    public function getLastCodigoByPrefix($prefix) {
        $sql = "SELECT MAX(codigo_unico) as max_code FROM facturas WHERE CAST(codigo_unico AS CHAR) LIKE ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$prefix . '%']);
        $row = $stmt->fetch();
        return $row['max_code'] ?? null;
    }

    public function create(array $data) {
        $sql = "INSERT INTO facturas (
                    codigo_unico, proveedor_id, cuenta_bancaria_id, numero_factura, 
                    fecha_emision, fecha_vencimiento, monto_bruto, 
                    monto_neto, monto_iva, motivo_correccion_iva, estado
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'REGISTRADA')";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['codigoUnico'],
            $data['proveedorId'],
            $data['cuentaBancariaId'] ?: null,
            $data['numeroFactura'],
            $data['fechaEmision'],
            $data['fechaVencimiento'],
            $data['montoBruto'],
            $data['montoNeto'],
            $data['montoIva'],
            $data['motivoCorreccion'] ?: null
        ]);
        
        return $this->db->lastInsertId();
    }

    public function createAsiento($facturaId, $codigoCuenta, $debe, $haber) {
        $stmt = $this->db->prepare("SELECT id FROM plan_cuentas WHERE codigo = ? LIMIT 1");
        $stmt->execute([$codigoCuenta]);
        $cuenta = $stmt->fetch();

        if (!$cuenta) {
            throw new \Exception("Error Crítico: La cuenta contable $codigoCuenta no existe en el sistema.");
        }

        $sql = "INSERT INTO asientos_contables (factura_id, plan_cuenta_id, glosa, debe, haber) 
                VALUES (?, ?, 'Registro Automático Compra', ?, ?)";
        
        $this->db->prepare($sql)->execute([
            $facturaId, 
            $cuenta['id'], 
            $debe, 
            $haber
        ]);
    }

    // Verificar si ya existe la factura para ese proveedor
    public function existeFactura($proveedorId, $numeroFactura) {
        $sql = "SELECT id FROM facturas WHERE proveedor_id = ? AND numero_factura = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$proveedorId, $numeroFactura]);
        return $stmt->fetch();
    }

    // Buscar factura completa por su Smart ID (2626xxxx)
    public function getByCodigoUnico($codigo) {
        $sql = "SELECT * FROM facturas WHERE codigo_unico = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$codigo]);
        return $stmt->fetch();
    }
    
    // Marcar una factura antigua como "Anulada" (Opcional, visualmente útil)
    public function marcarComoAnulada($id) {
        $sql = "UPDATE facturas SET estado = 'ANULADA' WHERE id = ?";
        $this->db->prepare($sql)->execute([$id]);
    }
}