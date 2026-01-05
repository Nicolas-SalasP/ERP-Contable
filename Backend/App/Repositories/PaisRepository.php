<?php
namespace App\Repositories;

use PDO;
use Exception;
use App\Config\Database;
class PaisRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function obtenerTodos() {
        try {
            $stmt = $this->db->prepare("SELECT iso, nombre, moneda_defecto, etiqueta_id FROM paises WHERE activo = 1 ORDER BY nombre ASC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Error al obtener países: " . $e->getMessage());
        }
    }

    public function buscarPorIso($iso) {
        $stmt = $this->db->prepare("SELECT * FROM paises WHERE iso = ? AND activo = 1");
        $stmt->execute([$iso]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}