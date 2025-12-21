<?php
// 1. IMPORTAR NAMESPACES
use App\Controllers\ProveedorController;
use App\Controllers\CuentaBancariaController;
use App\Controllers\FacturaController;

// 2. CONFIGURACIÓN CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // 3. CARGA DE ARCHIVOS
    $baseDir = dirname(__DIR__) . '/App'; 

    $files = [
        '/Config/Database.php',
        '/Repositories/FacturaRepository.php',
        '/Services/AccountingService.php',
        '/Controllers/ProveedorController.php',
        '/Controllers/CuentaBancariaController.php',
        '/Controllers/FacturaController.php'
    ];

    foreach ($files as $file) {
        $path = $baseDir . $file;
        if (!file_exists($path)) {
            throw new Exception("Error Crítico: No se encuentra el archivo: $path");
        }
        require_once $path;
    }

    // 4. ROUTER
    $scriptName = $_SERVER['SCRIPT_NAME']; 
    $basePath = dirname($scriptName); 
    $requestUri = $_SERVER['REQUEST_URI'];
    
    if (strpos($requestUri, '?') !== false) {
        $requestUri = explode('?', $requestUri)[0];
    }
    
    $path = str_replace($basePath, '', $requestUri);
    $path = '/' . ltrim($path, '/');

    // --- DEFINICIÓN DE RUTAS ---

    // A. Proveedores
    if (preg_match('/^\/api\/proveedores\/([a-zA-Z0-9]+)$/', $path, $matches)) {
        $controller = new ProveedorController();
        $controller->getByCodigo($matches[1]);
    
    // B. Cuentas Bancarias
    } elseif (preg_match('/^\/api\/cuentas-bancarias\/proveedor\/(\d+)$/', $path, $matches)) {
        $controller = new CuentaBancariaController();
        $controller->getByProveedor($matches[1]);

    // C. Nueva Ruta: Verificar Duplicado (GET)
    } elseif ($path === '/api/facturas/check' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $controller = new FacturaController();
        $controller->checkExistence();

    // D. Guardar Factura
    } elseif ($path === '/api/facturas' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller = new FacturaController();
        $controller->create();

    // E. Anular Factura
    } elseif ($path === '/api/facturas/anular' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller = new FacturaController();
        $controller->anular();

    } else {
        http_response_code(404);
        echo json_encode(["success" => false, "error" => "Endpoint no encontrado"]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error Server", "details" => $e->getMessage()]);
}