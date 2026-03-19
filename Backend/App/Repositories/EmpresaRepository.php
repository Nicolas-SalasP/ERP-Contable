<?php
namespace App\Repositories;

use App\Config\Database;
use App\Middlewares\AuthMiddleware;
use PDO;
use Exception;

class EmpresaRepository
{
    private $db;
    private $empresaId;

    public function __construct()
    {
        $this->db = Database::getConnection();
        try {
            $auth = AuthMiddleware::authenticate();
            $this->empresaId = $auth->empresa_id ?? null;
        } catch (Exception $e) {
            $this->empresaId = null;
        }
    }

    public function obtenerPerfil(?int $id = null)
    {
        $targetId = $id ?? $this->empresaId;
        if (!$targetId)
            return null;

        $stmt = $this->db->prepare("SELECT * FROM empresas WHERE id = ?");
        $stmt->execute([$targetId]);
        $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($empresa) {
            $stmtBancos = $this->db->prepare("SELECT * FROM cuentas_bancarias_empresa WHERE empresa_id = ?");
            $stmtBancos->execute([$targetId]);
            $empresa['bancos'] = $stmtBancos->fetchAll(PDO::FETCH_ASSOC);
            $stmtCentros = $this->db->prepare("SELECT * FROM centros_costo WHERE empresa_id = ? AND activo = 1 ORDER BY codigo");
            $stmtCentros->execute([$targetId]);
            $empresa['centros_costo'] = $stmtCentros->fetchAll(PDO::FETCH_ASSOC);
        }

        return $empresa;
    }

    public function actualizarDatos(array $data)
    {
        $sql = "UPDATE empresas SET razon_social = ?, giro = ?, direccion = ?, telefono = ?, email_contacto = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['razon_social'],
            $data['giro'] ?? null,
            $data['direccion'] ?? null,
            $data['telefono'] ?? null,
            $data['email_contacto'] ?? null,
            $this->empresaId
        ]);
    }

    public function actualizarLogo($path)
    {
        if (!$this->empresaId)
            return false;

        $stmt = $this->db->prepare("UPDATE empresas SET logo_path = ? WHERE id = ?");
        return $stmt->execute([$path, $this->empresaId]);
    }

    public function agregarCuenta(array $data)
    {
        if (!$this->empresaId)
            return false;

        $sql = "INSERT INTO cuentas_bancarias_empresa (empresa_id, banco, tipo_cuenta, numero_cuenta, titular, rut_titular, email_notificacion) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $this->empresaId,
            $data['banco'],
            $data['tipo_cuenta'],
            $data['numero_cuenta'],
            $data['titular'],
            $data['rut_titular'],
            $data['email_notificacion']
        ]);
    }

    public function eliminarCuenta($id)
    {
        if (!$this->empresaId)
            return false;

        $stmt = $this->db->prepare("DELETE FROM cuentas_bancarias_empresa WHERE id = ? AND empresa_id = ?");
        return $stmt->execute([$id, $this->empresaId]);
    }

    public function existeRut(string $rut): bool
    {
        $rutLimpio = str_replace(['.', '-'], '', $rut);
        $stmt = $this->db->prepare("SELECT id FROM empresas WHERE rut = ? LIMIT 1");
        $stmt->execute([$rutLimpio]);

        return (bool) $stmt->fetch();
    }

    public function existeEmailUsuario(string $email): bool
    {
        $stmt = $this->db->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        return (bool) $stmt->fetch();
    }

    public function crearEmpresa(string $rut, string $razonSocial): int
    {
        $sql = "INSERT INTO empresas (rut, razon_social, created_at) VALUES (?, ?, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$rut, $razonSocial]);
        return (int) $this->db->lastInsertId();
    }

    public function crearUsuarioAdmin(int $empresaId, string $nombre, string $email, string $passwordHash): int
    {
        $sql = "INSERT INTO usuarios (
                    empresa_id, nombre, email, password, rol_id, 
                    estado_suscripcion_id, fecha_fin_suscripcion, created_at
                ) VALUES (?, ?, ?, ?, 1, 1, DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$empresaId, $nombre, $email, $passwordHash]);
        return (int) $this->db->lastInsertId();
    }

    public function clonarPlanMaestro(int $empresaId): void
    {
        $sql = "INSERT INTO plan_cuentas (empresa_id, codigo, nombre, tipo, nivel, imputable, created_at)
                SELECT ?, codigo, nombre, tipo, nivel, imputable, NOW()
                FROM catalogo_plan_maestro";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$empresaId]);
    }

    public function inicializarSecuencias(int $empresaId): void
    {
        $secuenciasBase = ['ASIENTO' => 0, 'FACTURA' => 0, 'COTIZACION' => 0];
        $sql = "INSERT INTO configuracion_secuencias (empresa_id, entidad, ultimo_valor) VALUES (?, ?, ?)";
        $stmt = $this->db->prepare($sql);

        foreach ($secuenciasBase as $entidad => $valor) {
            $stmt->execute([$empresaId, $entidad, $valor]);
        }
    }

    public function obtenerCatalogoBancos(): array
    {
        $sql = "SELECT * FROM catalogo_bancos ORDER BY nombre ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarCentrosCostoFormat()
    {
        $stmt = $this->db->prepare("SELECT id as value, CONCAT('[', codigo, '] ', nombre) as label FROM centros_costo WHERE empresa_id = ? AND activo = 1 ORDER BY nombre");
        $stmt->execute([$this->empresaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function agregarCentroCosto($codigo, $nombre)
    {
        $stmt = $this->db->prepare("INSERT INTO centros_costo (empresa_id, codigo, nombre) VALUES (?, ?, ?)");
        return $stmt->execute([$this->empresaId, $codigo, $nombre]);
    }

    public function eliminarCentroCosto($id)
    {
        $stmt = $this->db->prepare("DELETE FROM centros_costo WHERE id = ? AND empresa_id = ?");
        return $stmt->execute([$id, $this->empresaId]);
    }

    public function crearEmpresaYVincularUsuario(int $usuarioId, array $datosEmpresa): int
    {
        $sqlEmpresa = "INSERT INTO empresas (rut, razon_social, giro, direccion, telefono, regimen_tributario, created_at) 
                       VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmtEmpresa = $this->db->prepare($sqlEmpresa);
        $stmtEmpresa->execute([
            $datosEmpresa['empresa_rut'],
            $datosEmpresa['empresa_razon_social'],
            $datosEmpresa['giro'] ?? null,
            $datosEmpresa['direccion'] ?? null,
            $datosEmpresa['telefono'] ?? null,
            $datosEmpresa['regimen_tributario'] ?? '14_D3'
        ]);

        $empresaId = (int) $this->db->lastInsertId();
        $sqlUsuario = "UPDATE usuarios SET empresa_id = ? WHERE id = ?";
        $stmtUsuario = $this->db->prepare($sqlUsuario);
        $stmtUsuario->execute([$empresaId, $usuarioId]);

        if (method_exists($this, 'clonarPlanMaestro')) {
            $this->clonarPlanMaestro($empresaId);
        }
        if (method_exists($this, 'inicializarSecuencias')) {
            $this->inicializarSecuencias($empresaId);
        }

        return $empresaId;
    }
}