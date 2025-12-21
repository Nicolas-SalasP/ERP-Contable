<?php
namespace App\Config;

use PDO;
use PDOException;

class Database {
    // Patrón Singleton para no abrir 50 conexiones
    private static $instance = null;
    private $conn;

    private $host = 'localhost';
    private $db_name = 'sistema_contable';
    private $username = 'root';
    private $password = '';

    private function __construct() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4";
            
            $this->conn = new PDO($dsn, $this->username, $this->password);
            
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            die(json_encode([
                "success" => false, 
                "message" => "Error crítico de conexión a Base de Datos: " . $e->getMessage()
            ]));
        }
    }

    public static function getConnection() {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance->conn;
    }
}