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

        if (!$targetId) {
            return null;
        }

        $stmt = $this->db->prepare("SELECT * FROM empresas WHERE id = ?");
        $stmt->execute([$targetId]);
        $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($empresa) {
            $stmtBancos = $this->db->prepare("SELECT * FROM cuentas_bancarias_empresa WHERE empresa_id = ?");
            $stmtBancos->execute([$targetId]);
            $empresa['bancos'] = $stmtBancos->fetchAll(PDO::FETCH_ASSOC);
        }
        
        return $empresa;
    }

    public function actualizarDatos(array $data)
    {
        if (!$this->empresaId) return false;
        $sql = "UPDATE empresas SET 
                razon_social = ?, rut = ?, direccion = ?, email = ?, telefono = ?, color_primario = ?
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['razon_social'], 
            $data['rut'], 
            $data['direccion'], 
            $data['email'], 
            $data['telefono'],
            $data['color_primario'],
            $this->empresaId
        ]);
    }

    public function actualizarLogo($path)
    {
        if (!$this->empresaId) return false;

        $stmt = $this->db->prepare("UPDATE empresas SET logo_path = ? WHERE id = ?");
        return $stmt->execute([$path, $this->empresaId]);
    }

    public function agregarCuenta(array $data)
    {
        if (!$this->empresaId) return false;

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
        if (!$this->empresaId) return false;

        $stmt = $this->db->prepare("DELETE FROM cuentas_bancarias_empresa WHERE id = ? AND empresa_id = ?");
        return $stmt->execute([$id, $this->empresaId]);
    }

    public function existeRut(string $rut): bool
    {
        $stmt = $this->db->prepare("SELECT id FROM empresas WHERE rut = ?");
        $stmt->execute([$rut]);
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
        return (int)$this->db->lastInsertId();
    }

    public function crearUsuarioAdmin(int $empresaId, string $nombre, string $email, string $passwordHash): int
    {
        $sql = "INSERT INTO usuarios (
                    empresa_id, nombre, email, password, rol_id, 
                    estado_suscripcion_id, fecha_fin_suscripcion, created_at
                ) VALUES (?, ?, ?, ?, 1, 1, DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$empresaId, $nombre, $email, $passwordHash]);
        return (int)$this->db->lastInsertId();
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
}