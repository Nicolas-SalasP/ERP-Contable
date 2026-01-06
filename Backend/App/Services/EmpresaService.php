<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use PDO;
use Exception;

class EmpresaService {

    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Crea una nueva empresa, su usuario administrador y clona la configuración base.
     * @return array Datos de la empresa y usuario creados.
     */
    public function registrarEmpresaCompleta(array $data): array {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("SELECT id FROM empresas WHERE rut = ?");
            $stmt->execute([$data['empresa_rut']]);
            if ($stmt->fetch()) {
                throw new Exception("El RUT de la empresa ya existe.");
            }

            $stmt = $this->db->prepare("INSERT INTO empresas (rut, razon_social, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$data['empresa_rut'], $data['empresa_razon_social']]);
            $empresaId = (int)$this->db->lastInsertId();

            $passwordHash = password_hash($data['admin_password'], PASSWORD_BCRYPT);
            $stmt = $this->db->prepare("INSERT INTO usuarios (empresa_id, nombre, email, password, rol_id, estado_suscripcion_id, fecha_fin_suscripcion, created_at) VALUES (?, ?, ?, ?, 1, 1, DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())");
            $stmt->execute([$empresaId, $data['admin_nombre'], $data['admin_email'], $passwordHash]);
            $usuarioId = (int)$this->db->lastInsertId();

            $stmt = $this->db->prepare("INSERT INTO plan_cuentas (empresa_id, codigo, nombre, tipo, nivel, imputable, created_at) SELECT ?, codigo, nombre, tipo, nivel, imputable, NOW() FROM catalogo_plan_maestro");
            $stmt->execute([$empresaId]);

            $stmt = $this->db->prepare("INSERT INTO configuracion_secuencias (empresa_id, entidad, ultimo_valor) VALUES (?, 'comprobante_diario', 0), (?, 'facturas', 0)");
            $stmt->execute([$empresaId, $empresaId]);

            $this->db->commit();

            AuditoriaService::registrar(
                'REGISTRO_NUEVA_EMPRESA', 
                'empresas', 
                $empresaId, 
                null, 
                ['rut' => $data['empresa_rut'], 'admin_email' => $data['admin_email']]
            );

            return ['success' => true, 'empresa_id' => $empresaId, 'mensaje' => 'Empresa creada exitosamente.'];

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw new Exception("Error en registro: " . $e->getMessage());
        }
    }

    private function crearRegistroEmpresa(string $rut, string $razonSocial): int {
        $stmt = $this->db->prepare("SELECT id FROM empresas WHERE rut = ?");
        $stmt->execute([$rut]);
        if ($stmt->fetch()) {
            throw new Exception("El RUT de la empresa ya está registrado.");
        }

        $sql = "INSERT INTO empresas (rut, razon_social, created_at) VALUES (?, ?, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$rut, $razonSocial]);
        
        return (int)$this->db->lastInsertId();
    }

    private function crearUsuarioAdmin(int $empresaId, array $data): int {
        $stmt = $this->db->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$data['admin_email']]);
        if ($stmt->fetch()) {
            throw new Exception("El email del administrador ya está en uso.");
        }

        $passwordHash = password_hash($data['admin_password'], PASSWORD_BCRYPT);

        $sql = "INSERT INTO usuarios (
                    empresa_id, nombre, email, password, rol_id, 
                    estado_suscripcion_id, fecha_fin_suscripcion, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $empresaId,
            $data['admin_nombre'],
            $data['admin_email'],
            $passwordHash,
            1,
            1,
            date('Y-m-d', strtotime('+30 days'))
        ]);

        return (int)$this->db->lastInsertId();
    }

    private function inicializarPlanDeCuentas(int $empresaId): void {
        $sql = "INSERT INTO plan_cuentas (empresa_id, codigo, nombre, tipo, nivel, imputable, created_at)
                SELECT ?, codigo, nombre, tipo, nivel, imputable, NOW()
                FROM catalogo_plan_maestro";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$empresaId]);
    }

    private function inicializarSecuencias(int $empresaId): void {
        $secuenciasBase = [
            'comprobante_diario' => 0,
            'comprobante_ingreso' => 0,
            'comprobante_egreso' => 0,
            'proveedores_internos' => 0
        ];

        $sql = "INSERT INTO configuracion_secuencias (empresa_id, entidad, ultimo_valor) VALUES (?, ?, ?)";
        $stmt = $this->db->prepare($sql);

        foreach ($secuenciasBase as $entidad => $valor) {
            $stmt->execute([$empresaId, $entidad, $valor]);
        }
    }
}