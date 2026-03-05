<?php
namespace App\Repositories;

use App\Config\Database;
use App\Middlewares\AuthMiddleware;
use PDO;

class RentaRepository
{
    private $db;
    private $empresaId;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->empresaId = AuthMiddleware::authenticate()->empresa_id ?? 1;
    }

    public function getMapeoActual() {
        $sql = "SELECT m.id, m.codigo_cuenta, pc.nombre, m.concepto_sii 
                FROM sii_mapeo_cuentas m 
                JOIN plan_cuentas pc ON m.codigo_cuenta = pc.codigo AND m.empresa_id = pc.empresa_id
                WHERE m.empresa_id = ?
                ORDER BY m.concepto_sii, m.codigo_cuenta";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->empresaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCuentasDisponiblesMapeo() {
        $sql = "SELECT codigo, nombre FROM plan_cuentas 
                WHERE empresa_id = ? AND imputable = 1 
                AND (codigo LIKE '4%' OR codigo LIKE '5%' OR codigo LIKE '6%' OR codigo LIKE '7%')
                AND codigo NOT IN (SELECT codigo_cuenta FROM sii_mapeo_cuentas WHERE empresa_id = ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->empresaId, $this->empresaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardarMapeo($codigoCuenta, $conceptoSII) {
        $sql = "INSERT INTO sii_mapeo_cuentas (empresa_id, codigo_cuenta, concepto_sii) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE concepto_sii = VALUES(concepto_sii)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$this->empresaId, $codigoCuenta, $conceptoSII]);
    }

    public function eliminarMapeo($id) {
        $sql = "DELETE FROM sii_mapeo_cuentas WHERE id = ? AND empresa_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id, $this->empresaId]);
    }

    public function getConfiguracionTributaria()
    {
        $stmt = $this->db->prepare("SELECT regimen_tributario, tasa_impuesto FROM empresas WHERE id = ?");
        $stmt->execute([$this->empresaId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getMovimientosPorConceptoSII(int $anio)
    {
        $sql = "SELECT 
                    m.concepto_sii,
                    SUM(da.haber) AS total_ingresos,
                    SUM(da.debe) AS total_egresos
                FROM detalles_asiento da
                JOIN asientos_contables a ON da.asiento_id = a.id
                JOIN sii_mapeo_cuentas m ON da.cuenta_contable = m.codigo_cuenta
                WHERE a.empresa_id = ? AND YEAR(a.fecha) = ?
                GROUP BY m.concepto_sii";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->empresaId, $anio]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTotalComprasPagadas(int $anio)
    {
        $sql = "SELECT SUM(pf.monto_pagado) as total_pagado 
                FROM pagos_facturas pf
                JOIN facturas f ON pf.factura_id = f.id
                WHERE f.empresa_id = ? AND YEAR(pf.fecha_pago) = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->empresaId, $anio]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res['total_pagado'] ?? 0;
    }

    public function getTotalActivoFijoComprado(int $anio)
    {
        $sql = "SELECT SUM(monto_adquisicion) as total_activos 
                FROM activos_fijos 
                WHERE empresa_id = ? AND YEAR(fecha_adquisicion) = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->empresaId, $anio]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res['total_activos'] ?? 0;
    }

    public function getTotalComprasDevengadas(int $anio)
    {
        $sql = "SELECT SUM(monto_neto) as total_compras 
                FROM facturas 
                WHERE empresa_id = ? AND YEAR(fecha_emision) = ? AND estado != 'ANULADA'";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->empresaId, $anio]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $res['total_compras'] ?? 0;
    }

    public function getDepreciacionContableNormal(int $anio)
    {
        $sql = "SELECT SUM(da.debe) as total_depreciacion 
                FROM detalles_asiento da
                JOIN asientos_contables a ON da.asiento_id = a.id
                WHERE a.empresa_id = ? AND YEAR(a.fecha) = ? AND da.cuenta_contable = '690105'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->empresaId, $anio]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res['total_depreciacion'] ?? 0;
    }
}