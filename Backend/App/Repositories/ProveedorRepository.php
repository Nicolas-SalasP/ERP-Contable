<?php
namespace App\Repositories;

use App\Config\Database;
use App\Middlewares\AuthMiddleware;
use PDO;
use Exception;

class ProveedorRepository
{
    private $db;
    private $empresaId;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $auth = AuthMiddleware::authenticate();
        $this->empresaId = (int) $auth->empresa_id;
    }

    public function getAll()
    {
        $sql = "SELECT * FROM proveedores WHERE empresa_id = ? ORDER BY id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->empresaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id)
    {
        $sql = "SELECT * FROM proveedores WHERE id = ? AND empresa_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id, $this->empresaId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getByCodigo($codigo)
    {
        $sql = "SELECT * FROM proveedores WHERE codigo_interno = ? AND empresa_id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$codigo, $this->empresaId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getNextCodigo()
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT ultimo_valor FROM configuracion_secuencias WHERE entidad = 'proveedores' AND empresa_id = ? FOR UPDATE");
            $stmt->execute([$this->empresaId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $current = $row ? (int) $row['ultimo_valor'] : 0;
            $next = $current + 1;

            if ($row) {
                $update = $this->db->prepare("UPDATE configuracion_secuencias SET ultimo_valor = ? WHERE entidad = 'proveedores' AND empresa_id = ?");
                $update->execute([$next, $this->empresaId]);
            } else {
                $insert = $this->db->prepare("INSERT INTO configuracion_secuencias (empresa_id, entidad, ultimo_valor) VALUES (?, 'proveedores', ?)");
                $insert->execute([$this->empresaId, $next]);
            }

            $this->db->commit();
            return $next;

        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function create(array $data)
    {
        $sql = "INSERT INTO proveedores (
                    empresa_id, codigo_interno, rut, razon_social, pais_iso, 
                    moneda_defecto, region, comuna, direccion, 
                    telefono, email_contacto, nombre_contacto
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            $this->empresaId,
            $data['codigo'],
            $data['rut'],
            $data['razonSocial'],
            $data['paisIso'],
            $data['moneda'],
            $data['region'] ?? null,
            $data['comuna'] ?? null,
            $data['direccion'] ?? null,
            $data['telefono'] ?? null,
            $data['emailContacto'] ?? null,
            $data['nombreContacto'] ?? null
        ]);

        return $this->db->lastInsertId();
    }

    public function obtenerFicha360(int $id): ?array
    {
        $sqlProv = "SELECT * FROM proveedores WHERE id = ? AND empresa_id = ?";
        $stmtProv = $this->db->prepare($sqlProv);
        $stmtProv->execute([$id, $this->empresaId]);
        $proveedor = $stmtProv->fetch(PDO::FETCH_ASSOC);

        if (!$proveedor)
            return null;

        $sqlFacturas = "SELECT f.id, f.numero_factura, f.fecha_emision, f.monto_neto, f.monto_bruto, f.estado, f.archivo_pdf, 
                        (SELECT codigo_unico FROM asientos_contables 
                        WHERE origen_modulo = 'COMPRA' AND origen_id = f.id AND tipo_asiento != 'anulacion' 
                        ORDER BY id ASC LIMIT 1) as comprobante_contable 
                        FROM facturas f 
                        WHERE f.proveedor_id = ? AND f.empresa_id = ? ORDER BY f.fecha_emision DESC";
        $stmtFacturas = $this->db->prepare($sqlFacturas);
        $stmtFacturas->execute([$id, $this->empresaId]);
        $facturas = $stmtFacturas->fetchAll(PDO::FETCH_ASSOC);

        $sqlAnticipos = "SELECT a.*, c.banco, c.numero_cuenta, ac.codigo_unico as comprobante_contable 
                        FROM anticipos_proveedores a 
                        LEFT JOIN cuentas_bancarias_empresa c ON a.cuenta_bancaria_id = c.id 
                        LEFT JOIN asientos_contables ac ON a.asiento_id = ac.id
                        WHERE a.proveedor_id = ? AND a.empresa_id = ? ORDER BY a.fecha DESC";
        $stmtAnticipos = $this->db->prepare($sqlAnticipos);
        $stmtAnticipos->execute([$id, $this->empresaId]);
        $anticipos = $stmtAnticipos->fetchAll(PDO::FETCH_ASSOC);

        return [
            'proveedor' => $proveedor,
            'facturas' => $facturas,
            'anticipos' => $anticipos
        ];
    }

    public function crearSolicitudAnticipo(array $datos): void
    {
        $sql = "INSERT INTO anticipos_proveedores 
                (empresa_id, proveedor_id, cuenta_bancaria_id, fecha, monto, saldo_disponible, referencia, estado, asiento_id) 
                VALUES (?, ?, NULL, ?, ?, ?, ?, 'PENDIENTE', NULL)";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            $this->empresaId,
            $datos['proveedor_id'],
            $datos['fecha'],
            $datos['monto'],
            $datos['monto'],
            $datos['referencia']
        ]);
    }
}