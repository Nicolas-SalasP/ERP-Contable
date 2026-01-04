<?php
namespace App\Repositories;

use App\Config\Database;
use PDO;

class ProveedorRepository
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getAll()
    {
        $stmt = $this->db->query("SELECT * FROM proveedores ORDER BY id DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByCodigo($codigo)
    {
        $stmt = $this->db->prepare("SELECT * FROM proveedores WHERE codigo_interno = ? LIMIT 1");
        $stmt->execute([$codigo]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getNextCodigo()
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT ultimo_valor FROM configuracion_secuencias WHERE entidad = 'proveedores' FOR UPDATE");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $current = $row ? $row['ultimo_valor'] : 700000;
            $next = $current + 1;

            if ($row) {
                $update = $this->db->prepare("UPDATE configuracion_secuencias SET ultimo_valor = ? WHERE entidad = 'proveedores'");
                $update->execute([$next]);
            } else {
                $insert = $this->db->prepare("INSERT INTO configuracion_secuencias (entidad, ultimo_valor) VALUES ('proveedores', ?)");
                $insert->execute([$next]);
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
                    codigo_interno, rut, razon_social, pais_iso, 
                    moneda_defecto, region, comuna, direccion, 
                    telefono, email_contacto, nombre_contacto
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
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