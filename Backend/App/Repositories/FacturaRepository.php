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

    public function generarCodigoSistema($prefijo)
    {
        // Bloqueamos la fila para evitar concurrencia (FOR UPDATE)
        $stmt = $this->db->prepare("SELECT ultimo_valor FROM configuracion_secuencias WHERE empresa_id = ? AND entidad = 'facturas' FOR UPDATE");
        $stmt->execute([$this->empresaId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            $stmtInit = $this->db->prepare("INSERT INTO configuracion_secuencias (empresa_id, entidad, ultimo_valor) VALUES (?, 'facturas', 0)");
            $stmtInit->execute([$this->empresaId]);
            $ultimoValor = 0;
        } else {
            $ultimoValor = (string) $fila['ultimo_valor'];
        }

        // Lógica de correlativo: Si el prefijo cambia (ej: cambia el año), reinicia.
        $prefijoStr = (string) $prefijo;
        if (strpos($ultimoValor, $prefijoStr) === 0) {
            $nuevoCodigo = (int) $ultimoValor + 1;
        } else {
            // Inicia nueva secuencia: Prefijo + 0001
            $nuevoCodigo = (int) ($prefijoStr . '0001');
        }

        $stmtUpdate = $this->db->prepare("UPDATE configuracion_secuencias SET ultimo_valor = ? WHERE empresa_id = ? AND entidad = 'facturas'");
        $stmtUpdate->execute([$nuevoCodigo, $this->empresaId]);

        return $nuevoCodigo;
    }

    public function create(array $data)
    {
        $sql = "INSERT INTO facturas (
                    codigo_unico, proveedor_id, cuenta_bancaria_id, numero_factura, 
                    fecha_emision, fecha_vencimiento, monto_bruto, 
                    monto_neto, monto_iva, motivo_correccion_iva, estado, created_at, empresa_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'REGISTRADA', NOW(), ?)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['codigoUnico'], // Aquí usamos el correlativo que viene del Service
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

    // --- CONTABILIDAD ---

    public function crearAsiento(array $datosAsiento): int
    {
        // Guarda en 'asientos_contables'
        $sql = "INSERT INTO asientos_contables (empresa_id, fecha, glosa, tipo_asiento, origen_modulo, origen_id, created_at) 
                VALUES (?, ?, ?, 'egreso', 'COMPRA', ?, NOW())";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $this->empresaId,
            $datosAsiento['fecha'],
            $datosAsiento['glosa'],
            $datosAsiento['origen_id']
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function crearDetalleAsiento(int $asientoId, string $codigoCuenta, float $debe, float $haber)
    {
        // Guarda en 'detalles_asiento'
        $sql = "INSERT INTO detalles_asiento (asiento_id, cuenta_contable, debe, haber) 
                VALUES (?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$asientoId, $codigoCuenta, $debe, $haber]);
    }

    // --- LECTURA ---

    public function buscarHistorial(string $termino, string $numFactura = '', string $estado = ''): array
    {
        // Construcción dinámica de la consulta
        $sql = "SELECT f.id, f.numero_factura, f.fecha_emision, 
                       f.monto_bruto as monto_total, 
                       f.estado as estado_pago, 
                       p.razon_social, p.rut, p.codigo_interno
                FROM facturas f 
                JOIN proveedores p ON f.proveedor_id = p.id 
                WHERE f.empresa_id = :empresa";

        $params = [':empresa' => $this->empresaId];

        // 1. Filtro por Proveedor (Texto general)
        if (!empty($termino)) {
            $sql .= " AND (p.razon_social LIKE :t1 OR p.rut LIKE :t1 OR p.codigo_interno LIKE :t1)";
            $params[':t1'] = "%$termino%";
        }

        // 2. Filtro por Número de Factura
        if (!empty($numFactura)) {
            $sql .= " AND f.numero_factura LIKE :num";
            $params[':num'] = "%$numFactura%";
        }

        // 3. Filtro por Estado (Pagado, Pendiente, Anulada)
        if (!empty($estado)) {
            // Nota: En tu BD el estado es 'REGISTRADA', 'ANULADA', etc.
            // Si el frontend manda 'PAGADO', no encontrará nada hasta que implementes pagos.
            // Pero el filtro funcionará correctamente para 'REGISTRADA' o 'ANULADA'.
            $sql .= " AND f.estado = :est";
            $params[':est'] = $estado;
        }

        $sql .= " ORDER BY f.fecha_emision DESC LIMIT 50";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Error BD Historial: " . $e->getMessage());
        }
    }

    public function obtenerCabeceraAsientoPorFactura(int $facturaId): ?array
    {
        $sql = "SELECT id, id as numero_asiento, fecha as fecha_contable, glosa 
                FROM asientos_contables 
                WHERE origen_id = :fid AND origen_modulo = 'COMPRA'
                LIMIT 1";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':fid' => $facturaId]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return $res ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    public function obtenerDetallesAsiento(int $asientoId): array
    {
        // IMPORTANTE: JOIN con plan_cuentas para obtener el nombre de la cuenta
        $sql = "SELECT d.cuenta_contable, d.debe, d.haber, p.nombre as nombre_cuenta 
                FROM detalles_asiento d
                LEFT JOIN plan_cuentas p ON d.cuenta_contable = p.codigo
                WHERE d.asiento_id = :aid";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':aid' => $asientoId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    // --- MÉTODOS AUXILIARES ---
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
}