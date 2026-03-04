<?php
namespace App\Repositories;

use App\Config\Database;
use App\Middlewares\AuthMiddleware;
use PDO;
use Exception;

class ActivoRepository
{
    private $db;
    private $empresaId;

    public function __construct()
    {
        $this->db = Database::getConnection();
        try {
            $auth = AuthMiddleware::authenticate();
            $this->empresaId = $auth->empresa_id ?? 1;
        } catch (Exception $e) {
            $this->empresaId = 1;
        }
    }

    public function crearActivoFijo($datos, $empresaId)
    {
        $stmtCuenta = $this->db->prepare("SELECT id FROM plan_cuentas WHERE codigo = ? AND empresa_id = ?");
        $stmtCuenta->execute([$datos['cuenta_contable'], $empresaId]);
        $cuenta = $stmtCuenta->fetch();
        
        $planCuentaId = $cuenta ? $cuenta['id'] : null;

        if (!$planCuentaId) {
            throw new Exception("No se encontró la cuenta contable en el plan de cuentas.");
        }

        $stmtCat = $this->db->prepare("SELECT vida_util_normal, vida_util_acelerada FROM sii_categorias_activos WHERE id = ?");
        $stmtCat->execute([$datos['categoria_sii_id']]);
        $categoria = $stmtCat->fetch();
        
        if (!$categoria) {
            throw new Exception("No se encontró la categoría del SII en la base de datos.");
        }
        
        $vidaUtilAnios = ($datos['tipo_depreciacion'] === 'ACELERADA') ? $categoria['vida_util_acelerada'] : $categoria['vida_util_normal'];
        $vidaUtilMeses = $vidaUtilAnios * 12;

        $sql = "INSERT INTO activos_fijos (
                    empresa_id, plan_cuenta_id, factura_id, nombre_activo, 
                    monto_adquisicion, fecha_adquisicion, estado, 
                    categoria_sii_id, tipo_depreciacion, vida_util_meses
                ) VALUES (?, ?, ?, ?, ?, ?, 'ACTIVO', ?, ?, ?)";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $empresaId,
            $planCuentaId,
            $datos['factura_id'],
            $datos['nombre_activo'],
            $datos['monto_adquisicion'],
            $datos['fecha_activacion'],
            $datos['categoria_sii_id'],
            $datos['tipo_depreciacion'],
            $vidaUtilMeses
        ]);

        return $this->db->lastInsertId();
    }

    public function getActivos()
    {
        $sql = "SELECT a.*, pc.codigo as cuenta_codigo, pc.nombre as cuenta_nombre, 
                    sii.nombre as categoria_sii, sii.vida_util_normal, sii.vida_util_acelerada
                FROM activos_fijos a
                INNER JOIN plan_cuentas pc ON a.plan_cuenta_id = pc.id
                LEFT JOIN sii_categorias_activos sii ON a.categoria_sii_id = sii.id
                WHERE a.empresa_id = ?
                ORDER BY a.fecha_adquisicion DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->empresaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCategoriasSii()
    {
        $stmt = $this->db->query("SELECT * FROM sii_categorias_activos ORDER BY nombre ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function activarActivo($id, $datos)
    {
        $sql = "UPDATE activos_fijos 
                SET estado = 'ACTIVO', 
                    categoria_sii_id = ?, 
                    tipo_depreciacion = ?, 
                    fecha_activacion = ? 
                WHERE id = ? AND empresa_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $datos['categoria_sii_id'],
            $datos['tipo_depreciacion'],
            $datos['fecha_activacion'],
            $id,
            $this->empresaId
        ]);
    }

    public function obtenerPendientesDeContabilidad()
    {
        $sql = "SELECT 
                    f.id AS factura_id, 
                    f.numero_factura, 
                    p.razon_social AS proveedor, 
                    f.fecha_emision,
                    da.cuenta_contable,
                    pc.nombre AS nombre_cuenta,
                    da.debe AS monto_adquisicion
                FROM detalles_asiento da
                JOIN asientos_contables a ON da.asiento_id = a.id
                JOIN facturas f ON a.origen_id = f.id
                JOIN proveedores p ON f.proveedor_id = p.id
                JOIN plan_cuentas pc ON da.cuenta_contable = pc.codigo
                WHERE da.cuenta_contable LIKE '120%' 
                AND da.debe > 0
                AND f.id NOT IN (SELECT factura_id FROM activos_fijos WHERE factura_id IS NOT NULL)
                GROUP BY f.id, da.cuenta_contable, da.debe
                ORDER BY f.fecha_emision DESC";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerActivosDepreciables()
    {
        $sql = "SELECT id, nombre_activo, monto_adquisicion, vida_util_meses 
                FROM activos_fijos 
                WHERE estado = 'ACTIVO' AND vida_util_meses > 0";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}