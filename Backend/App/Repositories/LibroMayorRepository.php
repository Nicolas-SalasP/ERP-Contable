<?php
namespace App\Repositories;

use App\Config\Database;
use PDO;

class LibroMayorRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function getLibroDiario() {
        $sql = "SELECT 
                    ac.id AS asiento_id,
                    ac.created_at AS fecha_creacion,
                    ac.debe,
                    ac.haber,
                    ac.glosa,
                    pc.codigo AS cuenta_codigo,
                    pc.nombre AS cuenta_nombre,
                    pc.tipo AS cuenta_tipo,
                    f.codigo_unico AS doc_referencia,
                    f.fecha_emision,
                    p.razon_social AS entidad
                FROM asientos_contables ac
                JOIN plan_cuentas pc ON ac.plan_cuenta_id = pc.id
                LEFT JOIN facturas f ON ac.factura_id = f.id
                LEFT JOIN proveedores p ON f.proveedor_id = p.id
                ORDER BY f.fecha_emision DESC, f.id DESC, ac.id ASC";

        return $this->db->query($sql)->fetchAll();
    }
    
    public function getPlanCuentas() {
        return $this->db->query("SELECT * FROM plan_cuentas ORDER BY codigo ASC")->fetchAll();
    }
}