<?php
namespace App\Repositories;

use App\Config\Database;
use App\Middlewares\AuthMiddleware;
use PDO;
use Exception;

class FacturaRepository
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

    // --- TRANSACCIONES ---
    public function beginTransaction()
    {
        if (!$this->db->inTransaction())
            $this->db->beginTransaction();
    }
    public function commit()
    {
        if ($this->db->inTransaction())
            $this->db->commit();
    }
    public function rollBack()
    {
        if ($this->db->inTransaction())
            $this->db->rollBack();
    }

    // --- SECUENCIAS ---
    public function generarCodigoSistema($prefijo)
    {
        $stmt = $this->db->prepare("SELECT ultimo_valor FROM configuracion_secuencias WHERE empresa_id = ? AND entidad = 'facturas' FOR UPDATE");
        $stmt->execute([$this->empresaId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        $ultimoValor = $fila ? (string) $fila['ultimo_valor'] : '0';
        $prefijoStr = (string) $prefijo;

        if (strpos($ultimoValor, $prefijoStr) === 0) {
            $nuevoCodigo = (string) ((int) $ultimoValor + 1);
        } else {
            $nuevoCodigo = $prefijoStr . '0000001';
        }

        if (!$fila) {
            $stmtInsert = $this->db->prepare("INSERT INTO configuracion_secuencias (empresa_id, entidad, ultimo_valor) VALUES (?, 'facturas', ?)");
            $stmtInsert->execute([$this->empresaId, $nuevoCodigo]);
        } else {
            $stmtUpdate = $this->db->prepare("UPDATE configuracion_secuencias SET ultimo_valor = ? WHERE empresa_id = ? AND entidad = 'facturas'");
            $stmtUpdate->execute([$nuevoCodigo, $this->empresaId]);
        }

        return $nuevoCodigo;
    }

    // --- CRUDS BÁSICOS ---
    public function create(array $data)
    {
        $sql = "INSERT INTO facturas (codigo_unico, proveedor_id, cuenta_bancaria_id, numero_factura, fecha_emision, fecha_vencimiento, monto_bruto, monto_neto, monto_iva, motivo_correccion_iva, estado, created_at, empresa_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'REGISTRADA', NOW(), ?)";
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
            $data['motivoCorreccion'] ?: null,
            $this->empresaId
        ]);
        return $this->db->lastInsertId();
    }

    public function existeFactura($proveedorId, $numeroFactura)
    {
        $sql = "SELECT id FROM facturas WHERE proveedor_id = ? AND numero_factura = ? AND estado != 'ANULADA' AND empresa_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$proveedorId, $numeroFactura, $this->empresaId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getByCodigoUnico($codigo)
    {
        $sql = "SELECT * FROM facturas WHERE codigo_unico = ? AND empresa_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$codigo, $this->empresaId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function marcarComoAnulada($id)
    {
        $sql = "UPDATE facturas SET estado = 'ANULADA' WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
    }

    // --- ESCRITURA CONTABLE ---
    public function obtenerCabeceraAsientoPorFactura(int $facturaId): ?array
    {
        $sql = "SELECT id, id as numero_asiento, fecha as fecha_contable, glosa FROM asientos_contables WHERE origen_id = :fid AND origen_modulo = 'COMPRA' LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':fid' => $facturaId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    public function obtenerDetallesAsiento(int $asientoId): array
    {
        $sql = "SELECT d.cuenta_contable, d.debe, d.haber, p.nombre as nombre_cuenta 
                FROM detalles_asiento d
                LEFT JOIN plan_cuentas p ON d.cuenta_contable COLLATE utf8mb4_unicode_ci = p.codigo COLLATE utf8mb4_unicode_ci
                WHERE d.asiento_id = :aid";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':aid' => $asientoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerAsientoPorId(int $id)
    {
        $sql = "SELECT * FROM asientos_contables WHERE id = ? AND empresa_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id, $this->empresaId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function buscarHistorial($proveedor = '', $numero = '', $estado = '', $limit = 10, $offset = 0)
    {
        $params = [':empresaId' => $this->empresaId];
        
        $sql = "SELECT f.*, 
                       p.razon_social as nombre_proveedor, 
                       p.rut as rut_proveedor,
                       ac.codigo_unico as codigo_asiento 
                FROM facturas f
                LEFT JOIN proveedores p ON f.proveedor_id = p.id
                LEFT JOIN asientos_contables ac ON (ac.origen_id = f.id AND ac.origen_modulo = 'COMPRA')
                WHERE f.empresa_id = :empresaId";

        if (!empty($proveedor)) {
            $sql .= " AND (p.razon_social LIKE :proveedor OR p.rut LIKE :proveedor)";
            $params[':proveedor'] = "%{$proveedor}%";
        }

        if (!empty($numero)) {
            $sql .= " AND f.numero_factura LIKE :numero";
            $params[':numero'] = "%{$numero}%";
        }

        if (!empty($estado)) {
            $sql .= " AND f.estado = :estado";
            $params[':estado'] = $estado;
        }

        $sql .= " ORDER BY f.created_at DESC";
        $sql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contarHistorial($proveedor = '', $numero = '', $estado = '')
    {
        $params = [':empresaId' => $this->empresaId];

        $sql = "SELECT COUNT(*) as total
                FROM facturas f
                LEFT JOIN proveedores p ON f.proveedor_id = p.id
                WHERE f.empresa_id = :empresaId";

        if (!empty($proveedor)) {
            $sql .= " AND (p.razon_social LIKE :proveedor OR p.rut LIKE :proveedor)";
            $params[':proveedor'] = "%{$proveedor}%";
        }

        if (!empty($numero)) {
            $sql .= " AND f.numero_factura LIKE :numero";
            $params[':numero'] = "%{$numero}%";
        }

        if (!empty($estado)) {
            $sql .= " AND f.estado = :estado";
            $params[':estado'] = $estado;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? (int)$result['total'] : 0;
    }
}