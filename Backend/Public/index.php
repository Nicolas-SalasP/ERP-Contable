<?php
use App\Controllers\ProveedorController;
use App\Controllers\CuentaBancariaController;
use App\Controllers\FacturaController;

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Content-Type: application/json; charset=UTF-8");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $baseDir = dirname(__DIR__) . '/App'; 
    $files = [
        '/Config/Database.php',
        
        // Repositorios
        '/Repositories/FacturaRepository.php',
        '/Repositories/ProveedorRepository.php',
        '/Repositories/CuentaBancariaRepository.php',
        
        // Servicios
        '/Services/ProveedorService.php', 
        '/Services/CuentaBancariaService.php',  
        
        // Controladores
        '/Controllers/ProveedorController.php',
        '/Controllers/CuentaBancariaController.php',
        '/Controllers/FacturaController.php'
    ];

    foreach ($files as $file) {
        $path = $baseDir . $file;
        if (!file_exists($path)) {
            throw new Exception("Error Crítico de Configuración: No se encuentra el archivo: $path");
        }
        require_once $path;
    }
    $scriptName = $_SERVER['SCRIPT_NAME']; 
    $basePath = dirname($scriptName); 
    $requestUri = $_SERVER['REQUEST_URI'];
    
    if (strpos($requestUri, '?') !== false) {
        $requestUri = explode('?', $requestUri)[0];
    }
    
    $path = str_replace($basePath, '', $requestUri);
    $path = '/' . ltrim($path, '/');

    // --- RUTAS DEL SISTEMA ---

    // === MÓDULO PROVEEDORES ===
    if ($path === '/api/proveedores' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $controller = new ProveedorController();
        $controller->getAll();

    } elseif ($path === '/api/proveedores' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller = new ProveedorController();
        $controller->create();

    } elseif (preg_match('/^\/api\/proveedores\/([a-zA-Z0-9]+)$/', $path, $matches)) {
        $controller = new ProveedorController();
        $controller->getByCodigo($matches[1]);
    
    // === MÓDULO CUENTAS BANCARIAS ===

    } elseif ($path === '/api/cuentas-bancarias' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller = new CuentaBancariaController();
        $controller->create();

    } elseif (preg_match('/^\/api\/cuentas-bancarias\/(\d+)$/', $path, $matches) && $_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $controller = new CuentaBancariaController();
        $controller->delete($matches[1]);

    } elseif (preg_match('/^\/api\/cuentas-bancarias\/proveedor\/(\d+)$/', $path, $matches)) {
        $controller = new CuentaBancariaController();
        $controller->getByProveedor($matches[1]);

    // === MÓDULO FACTURAS ===
    } elseif ($path === '/api/facturas/check' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $controller = new FacturaController();
        $controller->checkExistence();

    } elseif ($path === '/api/facturas/anular' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller = new FacturaController();
        $controller->anular();

    } elseif ($path === '/api/facturas' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller = new FacturaController();
        $controller->create();

    } else {
        http_response_code(404);
        echo json_encode(["success" => false, "error" => "Endpoint no encontrado: " . $path]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false, 
        "message" => "Error del Servidor", 
        "details" => $e->getMessage()
    ]);
}