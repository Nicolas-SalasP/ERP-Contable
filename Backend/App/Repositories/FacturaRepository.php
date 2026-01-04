<?php
namespace App\Repositories;

use App\Config\Database;
use PDO;

class FacturaRepository
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function beginTransaction()
    {
        $this->db->beginTransaction();
    }

    public function commit()
    {
        $this->db->commit();
    }

    public function rollBack()
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    public function getLastCodigoByPrefix($prefix)
    {
        $sql = "SELECT MAX(codigo_unico) as max_code FROM facturas WHERE CAST(codigo_unico AS CHAR) LIKE ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$prefix . '%']);
        $row = $stmt->fetch();
        return $row['max_code'] ?? null;
    }

    public function create(array $data)
    {
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

    public function existeFactura($proveedorId, $numeroFactura)
    {
        $sql = "SELECT id FROM facturas WHERE proveedor_id = ? AND numero_factura = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$proveedorId, $numeroFactura]);
        return $stmt->fetch();
    }

    public function getByCodigoUnico($codigo)
    {
        $sql = "SELECT * FROM facturas WHERE codigo_unico = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$codigo]);
        return $stmt->fetch();
    }

    public function marcarComoAnulada($id)
    {
        $sql = "UPDATE facturas SET estado = 'ANULADA' WHERE id = ?";
        $this->db->prepare($sql)->execute([$id]);
    }
}