<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use App\Middlewares\AuthMiddleware;
use PDO;
use Exception;

class ContabilidadRepository
{
    private PDO $db;
    private int $empresaId;

    public function __construct()
    {
        $this->db = Database::getConnection();
        try {
            $user = AuthMiddleware::authenticate();
            $this->empresaId = (int) $user->empresa_id;
        } catch (Exception $e) {
            $this->empresaId = 1;
        }
    }

    public function generarCodigoAsiento(string $origen = 'MANUAL'): string
    {
        $prefijo = ($origen === 'COMPRA' || $origen === 'VENTA') ? '26' : '10';
        $entidadSecuencia = ($prefijo === '26') ? 'ASIENTO_FACTURA' : 'ASIENTO_MANUAL';

        $transaccionExterna = $this->db->inTransaction();
        if (!$transaccionExterna) {
            $this->db->beginTransaction();
        }

        try {
            $stmt = $this->db->prepare("SELECT ultimo_valor FROM configuracion_secuencias WHERE empresa_id = ? AND entidad = ? FOR UPDATE");
            $stmt->execute([$this->empresaId, $entidadSecuencia]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);
            $ultimoValor = $fila ? (string) $fila['ultimo_valor'] : $prefijo . '0000000';
            $nuevoCodigo = (string) ($ultimoValor + 1);
            if (strpos($nuevoCodigo, $prefijo) !== 0) {
                $nuevoCodigo = $prefijo . substr($nuevoCodigo, strlen($prefijo));
            }

            if (!$fila) {
                $stmtInsert = $this->db->prepare("INSERT INTO configuracion_secuencias (empresa_id, entidad, ultimo_valor) VALUES (?, ?, ?)");
                $stmtInsert->execute([$this->empresaId, $entidadSecuencia, $nuevoCodigo]);
            } else {
                $stmtUpdate = $this->db->prepare("UPDATE configuracion_secuencias SET ultimo_valor = ? WHERE empresa_id = ? AND entidad = ?");
                $stmtUpdate->execute([$nuevoCodigo, $this->empresaId, $entidadSecuencia]);
            }

            if (!$transaccionExterna) {
                $this->db->commit();
            }

            return $nuevoCodigo;

        } catch (Exception $e) {
            if (!$transaccionExterna) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function crearAsiento(array $datos): int
    {
        $modulo = $datos['origen_modulo'] ?? 'MANUAL';
        $codigoUnico = $datos['codigo_unico'] ?? $this->generarCodigoAsiento($modulo);
        $fechaContable = !empty($datos['fecha']) ? $datos['fecha'] : date('Y-m-d');

        try {
            $sqlCabecera = "INSERT INTO asientos_contables 
                            (empresa_id, codigo_unico, fecha, glosa, tipo_asiento, origen_modulo, origen_id, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

            $tipo = $datos['tipo_asiento'] ?? 'traspaso';
            $origenId = $datos['origen_id'] ?? null;

            $stmt = $this->db->prepare($sqlCabecera);
            $stmt->execute([
                $this->empresaId,
                $codigoUnico,
                $fechaContable,
                $datos['glosa'],
                $tipo,
                $modulo,
                $origenId
            ]);

            $asientoId = (int) $this->db->lastInsertId();
            
            if (isset($datos['cuenta_codigo']) && !empty($datos['cuenta_codigo'])) {
                $debe = $datos['debe'] ?? 0;
                $haber = $datos['haber'] ?? 0;
                $this->crearDetalle($asientoId, (string) $datos['cuenta_codigo'], (float) $debe, (float) $haber);
            }

            return $asientoId;

        } catch (Exception $e) {
            throw $e;
        }
    }

    public function crearDetalle(int $asientoId, string $cuentaCodigo, float $debe, float $haber): void
    {
        $sql = "INSERT INTO detalles_asiento (asiento_id, cuenta_contable, debe, haber) VALUES (?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$asientoId, $cuentaCodigo, $debe, $haber]);
    }

    public function getByCodigoUnico($codigo): ?array
    {
        $sql = "SELECT * FROM asientos_contables WHERE codigo_unico = ? AND empresa_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$codigo, $this->empresaId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    public function obtenerDetalles(int $asientoId): array
    {
        $sql = "SELECT d.*, p.nombre as nombre_cuenta 
                FROM detalles_asiento d 
                LEFT JOIN plan_cuentas p ON d.cuenta_contable = p.codigo 
                WHERE d.asiento_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$asientoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarCuentaIdPorCodigo(string $codigo): ?int
    {
        $sql = "SELECT id FROM plan_cuentas WHERE codigo = ? AND empresa_id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$codigo, $this->empresaId]);
        return $stmt->fetchColumn() ? (int) $stmt->fetchColumn() : null;
    }

    public function getSaldosAgrupados(string $fechaInicio, string $fechaFin): array
    {
        $sql = "SELECT d.cuenta_contable as codigo, p.nombre as nombre_cuenta, SUM(d.debe) as total_debe, SUM(d.haber) as total_haber FROM detalles_asiento d JOIN asientos_contables a ON d.asiento_id = a.id LEFT JOIN plan_cuentas p ON (d.cuenta_contable = p.codigo AND p.empresa_id = a.empresa_id) WHERE a.empresa_id = ? AND a.fecha BETWEEN ? AND ? GROUP BY d.cuenta_contable";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->empresaId, $fechaInicio, $fechaFin]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerAsientoPorOrigen(string $modulo, int $origenId): array
    {
        $sql = "SELECT * FROM asientos_contables WHERE origen_modulo = ? AND origen_id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$modulo, $origenId]);
        $cabecera = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cabecera)
            return [];

        $detalles = $this->obtenerDetalles((int) $cabecera['id']);

        return ['cabecera' => $cabecera, 'detalles' => $detalles];
    }

    public function anularAsientoManual(int $id)
    {
        $sql = "UPDATE asientos_contables SET glosa = CONCAT(glosa, ' [ANULADA]') WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
    }

    public function obtenerMovimientosDiario(string $fechaInicio, string $fechaFin, ?string $codigoCuenta = null): array
    {
        $sql = "SELECT 
                    a.id as asiento_id,
                    a.codigo_unico,
                    a.fecha,
                    a.glosa,
                    a.tipo_asiento,
                    d.cuenta_contable as cuenta_codigo,
                    COALESCE(p.nombre, 'Cuenta No Encontrada') as cuenta_nombre,
                    d.debe,
                    d.haber,
                    a.origen_modulo,
                    CASE 
                        WHEN a.origen_modulo IN ('COMPRA', 'VENTA') THEN f.numero_factura
                        ELSE '' 
                    END as numero_documento
                FROM asientos_contables a
                INNER JOIN detalles_asiento d ON a.id = d.asiento_id
                LEFT JOIN plan_cuentas p ON (d.cuenta_contable = p.codigo AND p.empresa_id = a.empresa_id)
                LEFT JOIN facturas f ON (a.origen_id = f.id AND a.origen_modulo IN ('COMPRA', 'VENTA'))
                WHERE a.empresa_id = ? 
                AND a.fecha BETWEEN ? AND ?";

        $params = [$this->empresaId, $fechaInicio, $fechaFin];

        if (!empty($codigoCuenta)) {
            $sql .= " AND d.cuenta_contable LIKE ?";
            $params[] = $codigoCuenta . "%"; 
        }

        $sql .= " ORDER BY a.fecha DESC, a.id DESC, d.haber ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerTodasLasCuentas(): array
    {
        $sql = "SELECT * FROM plan_cuentas 
                WHERE empresa_id = ? 
                ORDER BY codigo ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->empresaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerAsientoCompleto(int $id): array
    {
        $sql = "SELECT * FROM asientos_contables WHERE id = ? AND empresa_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id, $this->empresaId]);
        $cabecera = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cabecera) return [];

        $sqlDet = "SELECT d.*, p.nombre as cuenta_nombre 
                FROM detalles_asiento d 
                LEFT JOIN plan_cuentas p ON (d.cuenta_contable = p.codigo AND p.empresa_id = ?)
                WHERE d.asiento_id = ?
                ORDER BY d.debe DESC, d.haber DESC";
        
        $stmtDet = $this->db->prepare($sqlDet);
        $stmtDet->execute([$this->empresaId, $id]);
        $detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

        return ['cabecera' => $cabecera, 'detalles' => $detalles];
    }

    public function agregarDetalleReclasificacion($asientoId, $cuenta, $fecha, $tipoOperacion, $debe, $haber)
    {
        $sql = "INSERT INTO detalles_asiento (asiento_id, cuenta_contable, fecha, tipo_operacion, debe, haber) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$asientoId, $cuenta, $fecha, $tipoOperacion, $debe, $haber]);
    }

    public function actualizarGlosaCabecera($asientoId, $nuevaGlosa)
    {
        $sql = "UPDATE asientos_contables SET glosa = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$nuevaGlosa, $asientoId]);
    }

    public function generarCodigoPersonalizado(string $prefijo): string
    {
        $sql = "SELECT MAX(codigo_unico) as max_cod FROM asientos_contables WHERE codigo_unico LIKE ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$prefijo . '%']);
        $res = $stmt->fetch();
        
        if ($res && !empty($res['max_cod'])) {
            $correlativoActual = (int)substr((string)$res['max_cod'], strlen($prefijo));
            $nuevoCorrelativo = str_pad((string)($correlativoActual + 1), 7, "0", STR_PAD_LEFT);
            return $prefijo . $nuevoCorrelativo;
        }
        return $prefijo . "0000001"; 
    }

    public function registrarPartidaDobleReal(string $codigoUnico, string $fecha, string $glosa, string $modulo, int $referenciaId, string $cuentaDebe, string $cuentaHaber, float $monto): int
    {
        $sqlCabecera = "INSERT INTO asientos_contables (empresa_id, codigo_unico, fecha, glosa, tipo_asiento, origen_modulo, origen_id, created_at) 
                        VALUES (?, ?, ?, ?, 'traspaso', ?, ?, NOW())";
        $stmtCabecera = $this->db->prepare($sqlCabecera);
        $stmtCabecera->execute([$this->empresaId, $codigoUnico, $fecha, $glosa, $modulo, $referenciaId]);
        $asientoId = (int)$this->db->lastInsertId();

        $sqlDet = "INSERT INTO detalles_asiento (asiento_id, cuenta_contable, debe, haber) VALUES (?, ?, ?, ?)";
        $stmtDet = $this->db->prepare($sqlDet);
        $stmtDet->execute([$asientoId, $cuentaDebe, $monto, 0]);
        $stmtDet->execute([$asientoId, $cuentaHaber, 0, $monto]);

        return $asientoId;
    }

    public function actualizarCuenta(int $id, array $datos): bool
    {
        $sql = "UPDATE plan_cuentas SET nombre = ?, imputable = ?, activo = ? WHERE id = ? AND empresa_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $datos['nombre'],
            (int) $datos['imputable'],
            (int) $datos['activo'],
            $id,
            $this->empresaId
        ]);
    }
    
    public function crearCabeceraAsiento(string $fecha, string $glosa, string $codigoUnico): int
    {
        $sql = "INSERT INTO asientos_contables 
                (empresa_id, codigo_unico, fecha, glosa, tipo_asiento, origen_modulo, created_at) 
                VALUES (?, ?, ?, ?, 'traspaso', 'MANUAL', NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $this->empresaId,
            $codigoUnico,
            $fecha,
            $glosa
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function crearDetalleAvanzado(int $asientoId, string $cuentaCodigo, float $debe, float $haber, ?int $centroCostoId = null, ?string $empleadoNombre = null): void
    {
        $sql = "INSERT INTO detalles_asiento (asiento_id, cuenta_contable, debe, haber, centro_costo_id, empleado_nombre) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$asientoId, $cuentaCodigo, $debe, $haber, $centroCostoId, $empleadoNombre]);
    }

    // --- CONTROL DE TRANSACCIONES ---
    public function beginTransaction(): void
    {
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
        }
    }

    public function commit(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->commit();
        }
    }

    public function rollBack(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }
}