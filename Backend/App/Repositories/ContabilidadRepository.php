<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Config\Database;
use App\Middlewares\AuthMiddleware; // Para obtener el empresa_id actual
use PDO;
use Exception;

class ContabilidadRepository
{

    private PDO $db;
    private int $empresaId;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $user = AuthMiddleware::authenticate();
        $this->empresaId = (int) $user->empresa_id;
    }

    public function buscarCuentaIdPorCodigo(string $codigo): ?int
    {
        $sql = "SELECT id FROM plan_cuentas WHERE codigo = ? AND empresa_id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$codigo, $this->empresaId]);

        $columna = $stmt->fetchColumn();
        return $columna ? (int) $columna : null;
    }

    public function crearAsiento(array $datos): int
    {
        try {
            $cuentaId = $this->buscarCuentaIdPorCodigo((string) $datos['cuenta_codigo']);

            if (!$cuentaId) {
                throw new Exception("Error Crítico: La cuenta contable '{$datos['cuenta_codigo']}' no existe en su Plan de Cuentas.");
            }

            $sqlCabecera = "INSERT INTO asientos_contables 
                            (empresa_id, fecha, glosa, tipo_asiento, origen_modulo, origen_id, created_at) 
                            VALUES (?, NOW(), ?, 'traspaso', 'FACTURACION', ?, NOW())";

            $stmt = $this->db->prepare($sqlCabecera);
            $stmt->execute([
                $this->empresaId,
                $datos['glosa'],
                $datos['origen_id']
            ]);

            $asientoId = (int) $this->db->lastInsertId();

            $sqlDetalle = "INSERT INTO detalles_asiento 
                           (asiento_id, cuenta_contable, debe, haber) 
                           VALUES (?, ?, ?, ?)";

            $stmtDetalle = $this->db->prepare($sqlDetalle);
            $stmtDetalle->execute([
                $asientoId,
                $datos['cuenta_codigo'],
                $datos['debe'],
                $datos['haber']
            ]);

            return $asientoId;

        } catch (Exception $e) {
            throw $e;
        }
    }

    public function getSaldosAgrupados(string $fechaInicio, string $fechaFin): array
    {
        $sql = "SELECT 
                    d.cuenta_contable as codigo,
                    p.nombre as nombre_cuenta,
                    SUM(d.debe) as total_debe,
                    SUM(d.haber) as total_haber
                FROM detalles_asiento d
                JOIN asientos_contables a ON d.asiento_id = a.id
                LEFT JOIN plan_cuentas p ON (d.cuenta_contable = p.codigo AND p.empresa_id = a.empresa_id)
                WHERE a.empresa_id = ? 
                  AND a.fecha BETWEEN ? AND ?
                GROUP BY d.cuenta_contable";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->empresaId, $fechaInicio, $fechaFin]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}