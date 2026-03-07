<?php
namespace App\Repositories;

use App\Config\Database;
use App\Middlewares\AuthMiddleware;
use PDO;
use Exception;

class BancoRepository
{
    private $db;
    private $empresaId;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->empresaId = AuthMiddleware::authenticate()->empresa_id ?? 1;
    }

    public function iniciarTransaccion() { $this->db->beginTransaction(); }
    public function confirmarTransaccion() { $this->db->commit(); }
    public function revertirTransaccion() { $this->db->rollBack(); }

    public function getCuentasEmpresa($empresaId) {
        $stmt = $this->db->prepare("SELECT * FROM cuentas_bancarias_empresa WHERE empresa_id = ?");
        $stmt->execute([$empresaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCuentaBancaria($id) {
        $stmt = $this->db->prepare("SELECT * FROM cuentas_bancarias_empresa WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$id, $this->empresaId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function existeMovimiento($cuentaId, $fecha, $cargo, $abono, $saldoFila) {
        $stmt = $this->db->prepare("SELECT id FROM movimientos_bancarios WHERE cuenta_bancaria_id = ? AND fecha = ? AND cargo = ? AND abono = ? AND saldo_historico = ?");
        $stmt->execute([$cuentaId, $fecha, $cargo, $abono, $saldoFila]);
        return $stmt->fetchColumn();
    }

    public function registrarMovimientoCartola($datos) {
        $sql = "INSERT INTO movimientos_bancarios (cuenta_bancaria_id, fecha, hora, descripcion, nro_documento, cargo, abono, saldo_historico, estado, asiento_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $datos['cuenta_bancaria_id'], $datos['fecha'], $datos['hora'] ?? null, $datos['descripcion'], 
            $datos['nro_documento'], $datos['cargo'], $datos['abono'], 
            $datos['saldo_historico'], $datos['estado'], $datos['asiento_id']
        ]);
    }

    public function actualizarDescripcionMovimiento($id, $nuevaDescripcion) {
        $stmt = $this->db->prepare("UPDATE movimientos_bancarios SET descripcion = ? WHERE id = ?");
        $stmt->execute([$nuevaDescripcion, $id]);
    }

    public function actualizarSaldoCuenta($cuentaId, $nuevoSaldo) {
        $stmt = $this->db->prepare("UPDATE cuentas_bancarias_empresa SET saldo_actual = ? WHERE id = ?");
        $stmt->execute([$nuevoSaldo, $cuentaId]);
    }

    public function crearAsientoContable($fecha, $glosa, $codigoUnico, $centroCostoId = null, $empleadoNombre = null) {
        $sql = "INSERT INTO asientos_contables (empresa_id, centro_costo_id, empleado_nombre, codigo_unico, fecha, glosa, tipo_asiento, origen_modulo) 
                VALUES (?, ?, ?, ?, ?, ?, 'egreso', 'BANCO_CONCILIACION')";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->empresaId, $centroCostoId, $empleadoNombre, $codigoUnico, $fecha, $glosa]);
        return $this->db->lastInsertId();
    }

    public function agregarDetalleAsiento($asientoId, $cuentaContable, $debe, $haber) {
        $sql = "INSERT INTO detalles_asiento (asiento_id, cuenta_contable, debe, haber) VALUES (?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$asientoId, $cuentaContable, $debe, $haber]);
    }

    public function getFacturasPorIds(array $ids) {
        $in = str_repeat('?,', count($ids) - 1) . '?';
        $params = array_merge($ids, [$this->empresaId]);
        $sql = "SELECT id, numero_factura, proveedor_id, monto_bruto FROM facturas WHERE id IN ($in) AND empresa_id = ? AND estado = 'REGISTRADA'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function marcarFacturaPagada($facturaId) {
        $stmt = $this->db->prepare("UPDATE facturas SET estado = 'PAGADA' WHERE id = ?");
        $stmt->execute([$facturaId]);
    }

    public function registrarPagoFactura($facturaId, $cuentaId, $asientoId, $fecha, $monto) {
        $sql = "INSERT INTO pagos_facturas (factura_id, cuenta_bancaria_empresa_id, asiento_id, fecha_pago, monto_pagado, metodo_pago) 
                VALUES (?, ?, ?, ?, ?, 'Transferencia Masiva')";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$facturaId, $cuentaId, $asientoId, $fecha, $monto]);
    }

    public function getMovimientosPendientes($cuentaId) {
        $stmt = $this->db->prepare("SELECT * FROM movimientos_bancarios WHERE cuenta_bancaria_id = ? AND estado = 'PENDIENTE' ORDER BY fecha ASC");
        $stmt->execute([$cuentaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMovimientoById($id) {
        $stmt = $this->db->prepare("SELECT * FROM movimientos_bancarios WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function actualizarEstadoMovimiento($id, $estado, $asientoId = null) {
        $stmt = $this->db->prepare("UPDATE movimientos_bancarios SET estado = ?, asiento_id = ? WHERE id = ?");
        $stmt->execute([$estado, $asientoId, $id]);
    }

    public function getCuentasImputables() {
        $stmt = $this->db->prepare("SELECT codigo, nombre, tipo FROM plan_cuentas WHERE empresa_id = ? AND imputable = 1 AND activo = 1 ORDER BY codigo");
        $stmt->execute([$this->empresaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

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
}