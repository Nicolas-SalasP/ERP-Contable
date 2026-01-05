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
        $this->empresaId = (int)$auth->empresa_id;
    }

    public function getAll()
    {
        $sql = "SELECT * FROM proveedores WHERE empresa_id = ? ORDER BY id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->empresaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
            $current = $row ? (int)$row['ultimo_valor'] : 0;
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
}