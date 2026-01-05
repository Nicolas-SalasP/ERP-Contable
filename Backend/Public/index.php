<?php
declare(strict_types=1);

// -----------------------------------------------------------------------------
// 1. Configuración de Headers y CORS
// -----------------------------------------------------------------------------
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

// Manejo de pre-flight OPTIONS para CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// -----------------------------------------------------------------------------
// 2. Carga de Archivos y Dependencias
// -----------------------------------------------------------------------------
$baseDir = dirname(__DIR__) . '/App';

// Helpers y Configuración Base
require_once $baseDir . '/Config/Database.php';
require_once $baseDir . '/Helpers/JwtHelper.php';

// Servicio de Auditoría (Crítico para el sistema)
$archivoAuditoria = $baseDir . '/Services/AuditoriaService.php';
if (!file_exists($archivoAuditoria)) {
    die(json_encode(["error" => "CRITICO", "mensaje" => "No se encuentra el archivo: " . $archivoAuditoria]));
}
require_once $archivoAuditoria;

// Middlewares y Router
require_once $baseDir . '/Middlewares/AuthMiddleware.php';
require_once $baseDir . '/Config/Router.php';

// Carga Manual de Clases (Orden: Repositorios -> Servicios -> Controladores)
$files = [
    // Repositorios
    '/Repositories/AutenticacionRepository.php',
    '/Repositories/FacturaRepository.php',
    '/Repositories/ProveedorRepository.php',
    '/Repositories/CuentaBancariaRepository.php',
    '/Repositories/ContabilidadRepository.php',
    '/Repositories/LibroMayorRepository.php',
    '/Repositories/PaisRepository.php',

    // Servicios
    '/Services/EmpresaService.php',
    '/Services/AutenticacionService.php',
    '/Services/ProveedorService.php',
    '/Services/CuentaBancariaService.php',
    '/Services/FacturaService.php',
    '/Services/ContabilidadService.php',
    '/Services/LibroMayorService.php',
    '/Services/PaisService.php',

    // Controladores
    '/Controllers/AutenticacionController.php',
    '/Controllers/ProveedorController.php',
    '/Controllers/CuentaBancariaController.php',
    '/Controllers/FacturaController.php',
    '/Controllers/ContabilidadController.php',
    '/Controllers/LibroMayorController.php',
    '/Controllers/PaisController.php'
];

foreach ($files as $file) {
    $path = $baseDir . $file;
    if (file_exists($path)) {
        require_once $path;
    } else {
        // Descomentar para depuración si es necesario
        // error_log("Advertencia: No se pudo cargar " . $path);
    }
}

// Importación de Namespaces para el Router
use App\Config\Router;
use App\Controllers\AutenticacionController;
use App\Controllers\ProveedorController;
use App\Controllers\CuentaBancariaController;
use App\Controllers\FacturaController;
use App\Controllers\ContabilidadController;
use App\Controllers\PaisController;

// -----------------------------------------------------------------------------
// 3. Definición de Rutas del Sistema
// -----------------------------------------------------------------------------
$router = new Router();

// --- Autenticación ---
$router->post('/api/auth/login', [AutenticacionController::class, 'login'], false);
$router->post('/api/auth/register', [AutenticacionController::class, 'registro'], false);

// --- Proveedores ---
$router->get('/api/proveedores', [ProveedorController::class, 'getAll'], true);
$router->post('/api/proveedores', [ProveedorController::class, 'create'], true);
$router->get('/api/proveedores/{codigo}', [ProveedorController::class, 'getByCodigo'], true);

// --- Países ---
$router->get('/api/paises', [PaisController::class, 'index'], true); 

// --- Cuentas Bancarias ---
$router->post('/api/cuentas-bancarias', [CuentaBancariaController::class, 'create'], true);
$router->delete('/api/cuentas-bancarias/{id}', [CuentaBancariaController::class, 'delete'], true);
$router->get('/api/cuentas-bancarias/proveedor/{id}', [CuentaBancariaController::class, 'getByProveedor'], true);

// --- FACTURAS (Módulo Completo) ---
$router->post('/api/facturas', [FacturaController::class, 'registrarCompra'], true);
$router->get('/api/facturas/check', [FacturaController::class, 'checkDuplicada'], true);
$router->get('/api/facturas/historial', [FacturaController::class, 'historialProveedor'], true);
$router->get('/api/facturas/{id}/asiento', [FacturaController::class, 'obtenerAsiento'], true);
$router->post('/api/facturas/anular', [FacturaController::class, 'anular'], true);


// --- Contabilidad ---
$router->post('/api/contabilidad/asiento-manual', [ContabilidadController::class, 'registrarAsientoManual'], true);
$router->get('/api/contabilidad/saldos-mayor', [ContabilidadController::class, 'verSaldosMayor'], true);

// -----------------------------------------------------------------------------
// 4. Despacho de la Petición
// -----------------------------------------------------------------------------
$router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);