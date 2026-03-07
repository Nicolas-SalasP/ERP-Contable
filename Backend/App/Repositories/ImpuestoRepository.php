<?php
namespace App\Repositories;

use App\Config\Database;
use App\Middlewares\AuthMiddleware;
use PDO;

class ImpuestoRepository {
    private $db;
    private $empresaId;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->empresaId = AuthMiddleware::authenticate()->empresa_id ?? 1;
    }

    public function getMovimientosIvaMes($mes, $anio) {
        $sql = "SELECT 
                    SUM(CASE WHEN da.cuenta_contable = '210201' THEN da.haber - da.debe ELSE 0 END) as iva_debito,
                    SUM(CASE WHEN da.cuenta_contable = '110001' THEN da.debe - da.haber ELSE 0 END) as iva_credito
                FROM detalles_asiento da
                JOIN asientos_contables ac ON da.asiento_id = ac.id
                WHERE ac.empresa_id = ? 
                AND MONTH(ac.fecha) = ? 
                AND YEAR(ac.fecha) = ?
                AND ac.origen_modulo != 'CIERRE_F29'";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->empresaId, $mes, $anio]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function verificarCierreExistente($mes, $anio) {
        $sql = "SELECT id FROM asientos_contables 
                WHERE empresa_id = ? AND MONTH(fecha) = ? AND YEAR(fecha) = ? 
                AND origen_modulo = 'CIERRE_F29'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->empresaId, $mes, $anio]);
        return $stmt->fetchColumn();
    }

    public function asegurarCuentaIvaPorPagar() {
        $stmt = $this->db->prepare("SELECT id FROM plan_cuentas WHERE empresa_id = ? AND codigo = '210301'");
        $stmt->execute([$this->empresaId]);
        if (!$stmt->fetchColumn()) {
            $sql = "INSERT INTO plan_cuentas (empresa_id, codigo, nombre, tipo, nivel, imputable, activo) VALUES (?, '210301', 'IVA por Pagar (F29)', 'PASIVO', 4, 1, 1)";
            $this->db->prepare($sql)->execute([$this->empresaId]);
        }
    }

    public function iniciarTransaccion() { $this->db->beginTransaction(); }
    public function confirmarTransaccion() { $this->db->commit(); }
    public function revertirTransaccion() { $this->db->rollBack(); }

    public function generarCodigoAsiento($fecha) {
        $anioMes = date('Ym', strtotime($fecha));
        $stmt = $this->db->prepare("SELECT ultimo_valor FROM configuracion_secuencias WHERE empresa_id = ? AND entidad = 'ASIENTO' FOR UPDATE");
        $stmt->execute([$this->empresaId]);
        $ultimoValor = $stmt->fetchColumn();

        if ($ultimoValor === false) {
            $this->db->prepare("INSERT INTO configuracion_secuencias (empresa_id, entidad, ultimo_valor) VALUES (?, 'ASIENTO', 1)")->execute([$this->empresaId]);
            $nuevoValor = 1;
        } else {
            $nuevoValor = $ultimoValor + 1;
            $this->db->prepare("UPDATE configuracion_secuencias SET ultimo_valor = ? WHERE empresa_id = ? AND entidad = 'ASIENTO'")->execute([$nuevoValor, $this->empresaId]);
        }
        return $anioMes . str_pad((string)$nuevoValor, 5, "0", STR_PAD_LEFT);
    }

    public function crearAsientoCierre($fecha, $glosa, $codigoUnico) {
        $sql = "INSERT INTO asientos_contables (empresa_id, codigo_unico, fecha, glosa, tipo_asiento, origen_modulo) 
                VALUES (?, ?, ?, ?, 'traspaso', 'CIERRE_F29')";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->empresaId, $codigoUnico, $fecha, $glosa]);
        return $this->db->lastInsertId();
    }

    public function agregarDetalleAsiento($asientoId, $cuentaContable, $debe, $haber) {
        $sql = "INSERT INTO detalles_asiento (asiento_id, cuenta_contable, debe, haber) VALUES (?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$asientoId, $cuentaContable, $debe, $haber]);
    }
}