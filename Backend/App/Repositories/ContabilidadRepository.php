<?php
namespace App\Repositories;

use App\Config\Database;
use PDO;
use Exception;

class ContabilidadRepository 
{
    private $db;

    public function __construct() 
    {
        $this->db = Database::getConnection();
    }

    public function createAsiento($referenciaId, $codigoCuenta, $debe, $haber, $tipoReferencia = 'FACTURA') 
    {
        $stmt = $this->db->prepare("SELECT id FROM plan_cuentas WHERE codigo = ? LIMIT 1");
        $stmt->execute([$codigoCuenta]);
        $cuenta = $stmt->fetch();

        if (!$cuenta) {
            throw new Exception("Error Crítico: La cuenta contable $codigoCuenta no existe en el sistema.");
        }
        
        $sql = "INSERT INTO asientos_contables (factura_id, plan_cuenta_id, glosa, debe, haber) 
                VALUES (?, ?, ?, ?, ?)";
        
        $glosa = "Registro Automático - " . $tipoReferencia;

        $this->db->prepare($sql)->execute([
            $referenciaId, 
            $cuenta['id'], 
            $glosa, 
            $debe, 
            $haber
        ]);
    }

    public function getSaldosAgrupados($fechaInicio, $fechaFin) 
    {
        $sql = "SELECT 
                    pc.codigo, 
                    pc.nombre as cuenta,
                    SUM(ac.debe) as total_debe,
                    SUM(ac.haber) as total_haber
                FROM asientos_contables ac
                INNER JOIN plan_cuentas pc ON ac.plan_cuenta_id = pc.id
                INNER JOIN facturas f ON ac.factura_id = f.id
                WHERE f.fecha_emision BETWEEN ? AND ?
                  AND f.estado != 'ANULADA'
                GROUP BY pc.codigo, pc.nombre
                ORDER BY pc.codigo ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$fechaInicio, $fechaFin]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}