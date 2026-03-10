<?php
namespace App\Config;

use PDO;
use PDOException;
use App\Config\Env;

class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        try {
            $host = Env::get('DB_HOST', '127.0.0.1');
            $db_name = Env::get('DB_NAME', 'sistema_contable');
            $username = Env::get('DB_USER', 'root');
            $password = Env::get('DB_PASS', '');

            $dsn = "mysql:host={$host};dbname={$db_name};charset=utf8mb4";
            
            $this->conn = new PDO($dsn, $username, $password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            die(json_encode([
                "success" => false, 
                "message" => "Error crítico de conexión a BD: " . $e->getMessage()
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