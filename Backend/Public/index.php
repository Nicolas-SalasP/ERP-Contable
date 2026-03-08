<?php
declare(strict_types=1);

// -----------------------------------------------------------------------------
// 1. Configuración de Headers y CORS
// -----------------------------------------------------------------------------
$origenes_permitidos = [
    'http://localhost:5173',
    'http://localhost:3000',
    'http://localhost',
    'https://tu-dominio-produccion.com'
];

$origen_peticion = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origen_peticion, $origenes_permitidos)) {
    header("Access-Control-Allow-Origin: $origen_peticion");
} else {
}

header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// -----------------------------------------------------------------------------
// 2. Carga de Archivos y Dependencias
// -----------------------------------------------------------------------------
$baseDir = dirname(__DIR__) . '/App';

require_once $baseDir . '/Config/Database.php';
require_once $baseDir . '/Helpers/JwtHelper.php';
require_once __DIR__ . '/../vendor/autoload.php';

$archivoAuditoria = $baseDir . '/Services/AuditoriaService.php';
if (!file_exists($archivoAuditoria)) {
    die(json_encode(["error" => "CRITICO", "mensaje" => "No se encuentra el archivo: " . $archivoAuditoria]));
}

require_once $archivoAuditoria;
require_once $baseDir . '/Middlewares/AuthMiddleware.php';
require_once $baseDir . '/Config/Router.php';

$files = [
    // Repositorios
    '/Repositories/AutenticacionRepository.php',
    '/Repositories/EmpresaRepository.php',
    '/Repositories/FacturaRepository.php',
    '/Repositories/ProveedorRepository.php',
    '/Repositories/CuentaBancariaRepository.php',
    '/Repositories/ContabilidadRepository.php',
    '/Repositories/LibroMayorRepository.php',
    '/Repositories/PaisRepository.php',
    '/Repositories/CotizacionRepository.php',
    '/Repositories/ClienteRepository.php',
    '/Repositories/ActivoRepository.php',
    '/Repositories/RentaRepository.php',
    '/Repositories/BancoRepository.php',
    '/Repositories/ImpuestoRepository.php',

    // Servicios
    '/Services/EmpresaService.php',
    '/Services/AutenticacionService.php',
    '/Services/ProveedorService.php',
    '/Services/CuentaBancariaService.php',
    '/Services/FacturaService.php',
    '/Services/ContabilidadService.php',
    '/Services/LibroMayorService.php',
    '/Services/PaisService.php',
    '/Services/AnulacionService.php',
    '/Services/CotizacionService.php',
    '/Services/ClienteService.php',
    '/Services/ActivoService.php',
    '/Services/RentaService.php',
    '/Services/BancoService.php',
    '/Services/ImpuestoService.php',

    // Controladores
    '/Controllers/AutenticacionController.php',
    '/Controllers/EmpresaController.php',
    '/Controllers/ProveedorController.php',
    '/Controllers/CuentaBancariaController.php',
    '/Controllers/FacturaController.php',
    '/Controllers/ContabilidadController.php',
    '/Controllers/LibroMayorController.php',
    '/Controllers/PaisController.php',
    '/Controllers/AnulacionController.php',
    '/Controllers/CotizacionController.php',
    '/Controllers/ClienteController.php',
    '/Controllers/ActivoController.php',
    '/Controllers/RentaController.php',
    '/Controllers/BancoController.php',
    '/Controllers/ImpuestoController.php'
];

foreach ($files as $file) {
    $path = $baseDir . $file;
    if (file_exists($path)) {
        require_once $path;
    } else {
    }
}

use App\Config\Router;
use App\Controllers\AutenticacionController;
use App\Controllers\EmpresaController;
use App\Controllers\ProveedorController;
use App\Controllers\CuentaBancariaController;
use App\Controllers\FacturaController;
use App\Controllers\ContabilidadController;
use App\Controllers\PaisController;
use App\Controllers\AnulacionController;
use App\Controllers\ClienteController;
use App\Controllers\CotizacionController;
use App\Controllers\ActivoController;
use App\Controllers\RentaController;
use App\Controllers\BancoController;
use App\Controllers\ImpuestoController;

// -----------------------------------------------------------------------------
// 3. Definición de Rutas del Sistema
// -----------------------------------------------------------------------------
$router = new Router();

// --- Autenticación ---
$router->post('/api/auth/login', [AutenticacionController::class, 'login'], false);
$router->post('/api/auth/recuperar', [AutenticacionController::class, 'solicitarRecuperacion'], false);
$router->post('/api/auth/resetear', [AutenticacionController::class, 'restablecerPassword'], false);

// --- Proveedores ---
$router->get('/api/proveedores', [ProveedorController::class, 'getAll'], true);
$router->post('/api/proveedores', [ProveedorController::class, 'create'], true);
$router->get('/api/proveedores/{codigo}', [ProveedorController::class, 'getByCodigo'], true);
$router->get('/api/proveedores/ficha/{id}', [ProveedorController::class, 'verFicha360'], true);
$router->post('/api/proveedores/anticipos', [ProveedorController::class, 'crearAnticipo'], true);

// --- Países ---
$router->get('/api/paises', [PaisController::class, 'index'], true);

// --- Cuentas Bancarias ---
$router->post('/api/cuentas-bancarias', [CuentaBancariaController::class, 'create'], true);
$router->delete('/api/cuentas-bancarias/{id}', [CuentaBancariaController::class, 'delete'], true);
$router->get('/api/cuentas-bancarias/proveedor/{id}', [CuentaBancariaController::class, 'getByProveedor'], true);

// --- Facturas ---
$router->post('/api/facturas', [FacturaController::class, 'registrarCompra'], true);
$router->get('/api/facturas/check', [FacturaController::class, 'checkDuplicada'], true);
$router->get('/api/facturas/historial', [FacturaController::class, 'historial'], true);
$router->get('/api/facturas/{id}/asiento', [FacturaController::class, 'obtenerAsiento'], true);
$router->post('/api/facturas/anular', [FacturaController::class, 'anular'], true);
$router->post('/api/anulacion/buscar', [AnulacionController::class, 'buscar'], true);
$router->post('/api/anulacion/ejecutar', [AnulacionController::class, 'ejecutar'], true);
$router->post('/api/facturas/{id}/pdf', [FacturaController::class, 'subirPdf'], true);
$router->post('/api/facturas/{id}/cruzar-anticipo', [FacturaController::class, 'cruzarConAnticipo'], true);

// --- Contabilidad ---
$router->post('/api/contabilidad/asiento-manual', [ContabilidadController::class, 'registrarAsientoManual'], true);
$router->get('/api/contabilidad/saldos-mayor', [ContabilidadController::class, 'verSaldosMayor'], true);
$router->post('/api/contabilidad/anular', [ContabilidadController::class, 'anularAsiento'], true);
$router->get('/api/contabilidad/libro-diario', [ContabilidadController::class, 'listarLibroDiario'], true);
$router->get('/api/contabilidad/plan-cuentas', [ContabilidadController::class, 'listarPlanCuentas'], true);
$router->get('/api/contabilidad/asientos/{id}', [ContabilidadController::class, 'verAsientoCompleto'], true);
$router->post('/api/facturas/{id}/reclasificar', [FacturaController::class, 'reclasificar'], true);
$router->put('/api/contabilidad/plan-cuentas/{id}', [ContabilidadController::class, 'actualizarCuenta'], true);
$router->post('/api/facturas/{id}/pagar', [FacturaController::class, 'pagar'], true);
$router->post('/api/contabilidad/asiento-manual/avanzado', [ContabilidadController::class, 'registrarAsientoManualAvanzado'], true);

// --- Cotizaciones ---
$router->post('/api/cotizaciones', [CotizacionController::class, 'crear'], true);
$router->get('/api/cotizaciones', [CotizacionController::class, 'listar'], true);
$router->put('/api/cotizaciones/{id}/estado', [CotizacionController::class, 'cambiarEstado'], true);
$router->get('/api/cotizaciones/pdf/{id}', [CotizacionController::class, 'descargarPdf'], true);

// --- Clientes ---
$router->get('/api/clientes', [ClienteController::class, 'index'], true);
$router->post('/api/clientes', [ClienteController::class, 'store'], true);
$router->put('/api/clientes/{id}', [ClienteController::class, 'update'], true);
$router->delete('/api/clientes/{id}', [ClienteController::class, 'delete'], true);

// --- Empresas ---
$router->post('/api/empresas/registro', [EmpresaController::class, 'registrar'], false);
$router->get('/api/empresas/perfil', [EmpresaController::class, 'verPerfil'], true);
$router->put('/api/empresas/perfil', [EmpresaController::class, 'actualizarPerfil'], true);
$router->post('/api/empresas/logo', [EmpresaController::class, 'subirLogo'], true);
$router->post('/api/empresas/bancos', [EmpresaController::class, 'guardarBanco'], true);
$router->delete('/api/empresas/bancos/{id}', [EmpresaController::class, 'eliminarBanco'], true);
$router->get('/api/empresas/catalogo-bancos', [EmpresaController::class, 'listarBancosDisponibles'], true);
$router->get('/api/empresas/centros-costo', [EmpresaController::class, 'listarCentrosCosto'], true);
$router->post('/api/empresas/centros-costo', [EmpresaController::class, 'guardarCentroCosto'], true);
$router->delete('/api/empresas/centros-costo/{id}', [EmpresaController::class, 'eliminarCentroCosto'], true);

// --- Activos Fijos Directos ---
$router->get('/api/activos', [ActivoController::class, 'listar'], true);
$router->post('/api/activos', [ActivoController::class, 'crear'], true);
$router->get('/api/activos/pendientes', [ActivoController::class, 'listarPendientes'], true);
$router->post('/api/activos/depreciar-mes', [ActivoController::class, 'procesarDepreciacion'], true);
$router->post('/api/activos/{id}/activar', [ActivoController::class, 'activar'], true);

// --- Proyectos de Activos (En Construcción) ---
$router->get('/api/activos/parametros', [ActivoController::class, 'getParametros'], true);
$router->get('/api/activos/proyectos', [ActivoController::class, 'listarProyectos'], true);
$router->get('/api/activos/proyectos/facturas-disponibles', [ActivoController::class, 'facturasDisponiblesProyecto'], true);
$router->get('/api/activos/proyectos/{id}/analisis', [ActivoController::class, 'analisisProyecto'], true);
$router->post('/api/activos/proyectos', [ActivoController::class, 'crearProyecto'], true);
$router->post('/api/activos/proyectos/{id}/facturas', [ActivoController::class, 'imputarFacturaProyecto'], true);
$router->put('/api/activos/proyectos/{id}/activar', [ActivoController::class, 'activarProyecto'], true);
$router->post('/api/activos/proyectos/depreciar-mes', [ActivoController::class, 'procesarDepreciacionProyectos'], true);
$router->post('/api/activos/proyectos/{id}/baja', [ActivoController::class, 'bajaProyecto'], true);

// --- Operación Renta (Tributario) ---
$router->get('/api/renta/pre-calculo/{anio}', [RentaController::class, 'obtenerPreRenta'], true);
$router->get('/api/renta/mapeo', [RentaController::class, 'obtenerMapeo'], true);
$router->post('/api/renta/mapeo', [RentaController::class, 'guardarMapeo'], true);
$router->delete('/api/renta/mapeo/{id}', [RentaController::class, 'eliminarMapeo'], true);

// --- Banco y Tesorería ---
$router->get('/api/banco/cuentas', [BancoController::class, 'listarCuentasEmpresa'], true);
$router->post('/api/banco/cartola/importar', [BancoController::class, 'importarExcel'], true);
$router->post('/api/banco/nomina/pagar', [BancoController::class, 'procesarNominaMasiva'], true);
$router->get('/api/banco/movimientos/pendientes/{id}', [BancoController::class, 'listarMovimientosPendientes'], true);
$router->get('/api/banco/cuentas-imputables', [BancoController::class, 'listarCuentasImputables'], true);
$router->post('/api/banco/movimientos/conciliar', [BancoController::class, 'conciliarMovimiento'], true);
$router->post('/api/banco/movimientos/ignorar', [BancoController::class, 'ignorarMovimiento'], true);
$router->get('/api/banco/anticipos-pendientes', [BancoController::class, 'listarAnticiposPendientes'], true);
$router->post('/api/banco/movimientos/conciliar-anticipo', [BancoController::class, 'conciliarConAnticipo'], true);

// --- IMPUESTOS Y F29 ---
$router->get('/api/impuestos/cierre-f29/simular/{mes}/{anio}', [ImpuestoController::class, 'simularCierre'], true);
$router->post('/api/impuestos/cierre-f29/ejecutar', [ImpuestoController::class, 'ejecutarCierre'], true);

// -----------------------------------------------------------------------------
// 4. Despacho de la Petición
// -----------------------------------------------------------------------------
$router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);