<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Core\Controllers\AuthController;
use App\Domains\Core\Controllers\Internal\AdminEmpresasController;
use App\Domains\Core\Controllers\Internal\WebProvisioningController;
use App\Domains\Core\Controllers\PaisController;
use App\Domains\Core\Controllers\EmpresaController;
use App\Domains\Core\Controllers\AnulacionController;
use App\Domains\Core\Controllers\UsuarioController;
use App\Domains\Comercial\Controllers\ClienteController;
use App\Domains\Comercial\Controllers\ProveedorController;
use App\Domains\Comercial\Controllers\FacturaController;
use App\Domains\Comercial\Controllers\CotizacionController;
use App\Domains\Comercial\Controllers\AnticipoProveedorController;
use App\Domains\Contabilidad\Controllers\PlanCuentaController;
use App\Domains\Contabilidad\Controllers\AsientoContableController;
use App\Domains\Contabilidad\Controllers\ReporteController;
use App\Domains\Contabilidad\Controllers\ImpuestosController;
use App\Domains\CorreccionMonetaria\Controllers\CorreccionMonetariaController;
use App\Domains\Tesoreria\Controllers\BancoController;
use App\Domains\Tesoreria\Controllers\ConciliacionController;
use App\Domains\Tesoreria\Controllers\CuentaProveedorController;
use App\Domains\Activos\Controllers\ActivoFijoController;
use App\Domains\Inventario\Controllers\InventarioController;
use App\Domains\Inventario\Controllers\InventarioAuditoriaController;
use App\Domains\Inventario\Controllers\InventarioDespachoController;
use App\Domains\Inventario\Controllers\InventarioDevolucionController;
use App\Domains\Inventario\Controllers\InventarioEventoIntegracionController;
use App\Domains\Inventario\Controllers\InventarioPackingController;
use App\Domains\Inventario\Controllers\InventarioPickingController;

/*
|--------------------------------------------------------------------------
| RBAC granular por endpoint
|--------------------------------------------------------------------------
| Cada ruta sensible declara el/los permiso(s) requeridos via el middleware
| 'permiso:' (EnsureUserHasPermission), que pasa si el usuario tiene AL MENOS
| UNO de los permisos listados. Los roles con jerarquia >= 100 (Super Admin)
| reciben todos los permisos; jerarquia >= 80 (Administrador) recibe ademas
| el set completo de inventario (ver ModuloPermisos::permisosUsuario).
| Las rutas de perfil de empresa quedan solo autenticadas (sin permiso
| especifico) porque son configuracion propia de cada miembro de la empresa.
*/

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'track.ultimo.acceso'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::middleware(['auth:sanctum', 'track.ultimo.acceso', 'check.subscription'])->group(function () {
    // Gestion de usuarios y roles
    Route::get('/usuarios', [UsuarioController::class, 'index'])->middleware('permiso:usuarios.ver');
    Route::get('/usuarios/roles', [UsuarioController::class, 'roles'])->middleware('permiso:usuarios.ver,usuarios.gestionar');
    Route::post('/usuarios/invitar', [UsuarioController::class, 'invitar'])->middleware('permiso:usuarios.gestionar');
    Route::put('/usuarios/{id}/rol', [UsuarioController::class, 'actualizarRol'])->middleware('permiso:usuarios.gestionar');
    Route::delete('/usuarios/{id}', [UsuarioController::class, 'desvincular'])->middleware('permiso:usuarios.gestionar');
    Route::post('/usuarios/roles', [UsuarioController::class, 'storeRol'])->middleware('permiso:usuarios.gestionar');
    Route::put('/usuarios/roles/{id}', [UsuarioController::class, 'updateRol'])->middleware('permiso:usuarios.gestionar');

    // Empresa - Perfil (configuracion propia: solo requiere autenticacion)
    Route::get('/empresas/perfil', [EmpresaController::class, 'perfil']);
    Route::put('/empresas/perfil', [EmpresaController::class, 'actualizarPerfil']);
    Route::post('/empresas/logo', [EmpresaController::class, 'subirLogo']);
    Route::get('/empresas/catalogo-bancos', [EmpresaController::class, 'catalogoBancos']);

    // Empresa - Cuentas Bancarias
    Route::post('/empresas/bancos', [EmpresaController::class, 'agregarBanco']);
    Route::put('/empresas/bancos/{id}', [EmpresaController::class, 'actualizarBanco']);
    Route::delete('/empresas/bancos/{id}', [EmpresaController::class, 'eliminarBanco']);

    // Empresa - Centros de Costos
    Route::get('/empresas/centros-costo', [EmpresaController::class, 'listarCentros']);
    Route::get('/centros-costo', [EmpresaController::class, 'listarCentros']);
    Route::post('/empresas/centros-costo', [EmpresaController::class, 'agregarCentro']);
    Route::put('/empresas/centros-costo/{id}', [EmpresaController::class, 'actualizarCentro']);
    Route::delete('/empresas/centros-costo/{id}', [EmpresaController::class, 'eliminarCentro']);

    // Core
    Route::get('/paises', [PaisController::class, 'index']);

    // ---------------------------------------------------------------------
    // Comercial - Clientes
    // ---------------------------------------------------------------------
    Route::get('/clientes', [ClienteController::class, 'index'])->middleware('permiso:clientes.ver,ventas.ver');
    Route::post('/clientes', [ClienteController::class, 'store'])->middleware('permiso:clientes.crear');
    Route::get('/clientes/{id}', [ClienteController::class, 'show'])->middleware('permiso:clientes.ver,ventas.ver');
    Route::put('/clientes/{id}', [ClienteController::class, 'update'])->middleware('permiso:clientes.crear');
    Route::delete('/clientes/{id}', [ClienteController::class, 'destroy'])->middleware('permiso:clientes.crear');
    Route::put('/clientes/{id}/activar', [ClienteController::class, 'activar'])->middleware('permiso:clientes.crear');
    Route::patch('/clientes/{id}/reactivar', [ClienteController::class, 'reactivar'])->middleware('permiso:clientes.crear');

    // ---------------------------------------------------------------------
    // Comercial - Proveedores
    // ---------------------------------------------------------------------
    Route::get('/proveedores/catalogo', [ProveedorController::class, 'catalogo'])->middleware('permiso:proveedores.ver,compras.ver');
    Route::get('/proveedores/ficha/{id}', [ProveedorController::class, 'ficha'])->middleware('permiso:proveedores.ver,compras.ver');
    Route::post('/proveedores/anticipos', [ProveedorController::class, 'guardarAnticipo'])->middleware('permiso:proveedores.crear,compras.crear');

    // Endpoints dedicados de anticipos a proveedores (con saldo disponible)
    Route::get('/anticipos-proveedores', [AnticipoProveedorController::class, 'index'])->middleware('permiso:proveedores.ver,compras.ver');
    Route::post('/anticipos-proveedores', [AnticipoProveedorController::class, 'store'])->middleware('permiso:proveedores.crear,compras.crear');
    Route::post('/anticipos-proveedores/{id}/aplicar', [AnticipoProveedorController::class, 'aplicar'])->middleware('permiso:proveedores.crear,compras.crear');
    Route::post('/proveedores/{id}/cruzar-documentos', [ProveedorController::class, 'cruzarDocumentos'])->middleware('permiso:proveedores.crear,compras.crear');

    // Resource de proveedores (index/store/update; show y destroy excluidos)
    Route::get('/proveedores', [ProveedorController::class, 'index'])->middleware('permiso:proveedores.ver,compras.ver');
    Route::post('/proveedores', [ProveedorController::class, 'store'])->middleware('permiso:proveedores.crear,compras.crear');
    Route::put('/proveedores/{id}', [ProveedorController::class, 'update'])->middleware('permiso:proveedores.crear,compras.crear');
    Route::patch('/proveedores/{id}', [ProveedorController::class, 'update'])->middleware('permiso:proveedores.crear,compras.crear');

    // ---------------------------------------------------------------------
    // Comercial - Facturas
    // ---------------------------------------------------------------------
    Route::get('/facturas/historial', [FacturaController::class, 'historial'])->middleware('permiso:compras.ver');
    Route::get('/facturas/check', [FacturaController::class, 'check'])->middleware('permiso:compras.ver');
    Route::get('/facturas/vencidas', [FacturaController::class, 'vencidas'])->middleware('permiso:compras.ver');
    Route::get('/facturas/exportar/excel', [FacturaController::class, 'exportarExcel'])->middleware('permiso:compras.ver');
    Route::get('/facturas/disponibles-proyectos', [FacturaController::class, 'disponiblesProyectos'])->middleware('permiso:compras.ver,activos.ver');

    // Resource de facturas (index/store/show; update y destroy no existen)
    Route::get('/facturas', [FacturaController::class, 'index'])->middleware('permiso:compras.ver');
    Route::post('/facturas', [FacturaController::class, 'store'])->middleware('permiso:compras.crear');
    Route::get('/facturas/{id}', [FacturaController::class, 'show'])->middleware('permiso:compras.ver');

    Route::get('/facturas/{id}/asiento', [FacturaController::class, 'verAsiento'])->middleware('permiso:compras.ver,contabilidad.ver');
    Route::post('/facturas/{id}/reclasificar', [FacturaController::class, 'reclasificarAsiento'])->middleware('permiso:contabilidad.crear');
    Route::get('/facturas/{id}/auditoria', [FacturaController::class, 'auditoria'])->middleware('permiso:compras.ver,contabilidad.ver');
    Route::post('/facturas/{id}/pagar', [FacturaController::class, 'pagar'])->middleware('permiso:tesoreria.crear,compras.crear');
    Route::post('/facturas/{id}/anular', [FacturaController::class, 'anular'])->middleware('permiso:compras.crear');
    Route::post('/facturas/{id}/vincular-proyecto', [FacturaController::class, 'vincularProyecto'])->middleware('permiso:activos.crear,compras.crear');

    // ---------------------------------------------------------------------
    // Comercial - Cotizaciones
    // ---------------------------------------------------------------------
    Route::get('/cotizaciones/pdf/{id}', [CotizacionController::class, 'generarPdf'])->middleware('permiso:ventas.ver');
    Route::put('/cotizaciones/{id}/estado', [CotizacionController::class, 'actualizarEstado'])->middleware('permiso:ventas.crear');
    Route::patch('/cotizaciones/{id}/estado', [CotizacionController::class, 'actualizarEstado'])->middleware('permiso:ventas.crear');
    Route::post('/cotizaciones/{id}/facturar', [CotizacionController::class, 'facturar'])->middleware('permiso:ventas.crear');

    // Resource de cotizaciones (index/store; show, update y destroy excluidos)
    Route::get('/cotizaciones', [CotizacionController::class, 'index'])->middleware('permiso:ventas.ver');
    Route::post('/cotizaciones', [CotizacionController::class, 'store'])->middleware('permiso:ventas.crear');
    Route::put('/cotizaciones/{id}', [CotizacionController::class, 'update'])->middleware('permiso:ventas.crear');

    // ---------------------------------------------------------------------
    // Tesoreria - Cuentas de Proveedores
    // ---------------------------------------------------------------------
    Route::get('/cuentas-bancarias/proveedor/{proveedorId}', [CuentaProveedorController::class, 'index'])->middleware('permiso:tesoreria.ver,proveedores.ver');
    Route::post('/cuentas-bancarias', [CuentaProveedorController::class, 'store'])->middleware('permiso:tesoreria.crear,proveedores.crear');
    Route::delete('/cuentas-bancarias/{id}', [CuentaProveedorController::class, 'destroy'])->middleware('permiso:tesoreria.crear,proveedores.crear');

    // ---------------------------------------------------------------------
    // Tesoreria - Bancos Propios y Conciliacion
    // ---------------------------------------------------------------------
    Route::get('/tesoreria/bancos-catalogo', [BancoController::class, 'catalogo'])->middleware('permiso:tesoreria.ver');
    Route::get('/tesoreria/cuentas-propias', [BancoController::class, 'cuentasEmpresa'])->middleware('permiso:tesoreria.ver');
    Route::post('/tesoreria/cuentas-propias', [BancoController::class, 'storeCuenta'])->middleware('permiso:tesoreria.crear');
    Route::post('/tesoreria/conciliar/factura-compra', [ConciliacionController::class, 'pagarFacturaCompra'])->middleware('permiso:tesoreria.crear');

    // Tesoreria - Bancos y Conciliacion
    Route::post('/banco/nomina/pagar', [BancoController::class, 'pagarNomina'])->middleware('permiso:tesoreria.crear');
    Route::get('/banco/cuentas', [BancoController::class, 'cuentasEmpresa'])->middleware('permiso:tesoreria.ver');
    Route::get('/banco/cuentas-imputables', [PlanCuentaController::class, 'imputables'])->middleware('permiso:tesoreria.ver,contabilidad.ver');
    Route::post('/banco/ingreso-manual', [BancoController::class, 'ingresoManual'])->middleware('permiso:tesoreria.crear');
    Route::post('/banco/importar', [BancoController::class, 'importarCartola'])->middleware('permiso:tesoreria.crear');

    // Tesoreria - Movimientos
    Route::get('/banco/movimientos/pendientes/{idCuenta}', [ConciliacionController::class, 'movimientosPendientes'])->middleware('permiso:tesoreria.ver');
    Route::get('/banco/movimientos/{id}/sugerencias', [ConciliacionController::class, 'sugerencias'])->middleware('permiso:tesoreria.ver');
    Route::get('/banco/movimientos/{idCuenta}', [BancoController::class, 'movimientos'])->middleware('permiso:tesoreria.ver');

    // Tesoreria - Mesa de Conciliacion
    Route::get('/banco/anticipos-pendientes', [ConciliacionController::class, 'anticiposPendientes'])->middleware('permiso:tesoreria.ver');
    Route::post('/banco/movimientos/conciliar', [ConciliacionController::class, 'conciliar'])->middleware('permiso:tesoreria.crear');
    Route::post('/banco/movimientos/conciliar-anticipo', [ConciliacionController::class, 'conciliarAnticipo'])->middleware('permiso:tesoreria.crear');
    Route::post('/banco/movimientos/conciliar-facturas', [ConciliacionController::class, 'conciliarFacturas'])->middleware('permiso:tesoreria.crear');

    // ---------------------------------------------------------------------
    // Contabilidad - Plan de Cuentas
    // ---------------------------------------------------------------------
    Route::get('/contabilidad/plan-cuentas', [PlanCuentaController::class, 'index'])->middleware('permiso:contabilidad.ver');
    Route::post('/contabilidad/plan-cuentas', [PlanCuentaController::class, 'store'])->middleware('permiso:contabilidad.crear');
    Route::put('/contabilidad/plan-cuentas/{id}', [PlanCuentaController::class, 'update'])->middleware('permiso:contabilidad.crear');
    Route::delete('/contabilidad/plan-cuentas/{id}', [PlanCuentaController::class, 'destroy'])->middleware('permiso:contabilidad.crear');

    // Contabilidad - Asientos Contables
    Route::get('/contabilidad/asientos', [AsientoContableController::class, 'index'])->middleware('permiso:contabilidad.ver');
    Route::post('/contabilidad/asientos', [AsientoContableController::class, 'store'])->middleware('permiso:contabilidad.crear');
    Route::get('/contabilidad/asientos/{id}', [AsientoContableController::class, 'show'])->middleware('permiso:contabilidad.ver');
    Route::post('/contabilidad/asientos/{id}/reversar', [AsientoContableController::class, 'reversar'])->middleware('permiso:contabilidad.crear');
    Route::post('/contabilidad/asiento-manual/avanzado', [AsientoContableController::class, 'storeAvanzado'])->middleware('permiso:contabilidad.crear');

    // Contabilidad - Libros diarios y mayores
    Route::get('/contabilidad/libro-diario', [ReporteController::class, 'libroDiario'])->middleware('permiso:contabilidad.ver');
    Route::get('/contabilidad/reportes/libro-mayor', [ReporteController::class, 'libroMayor'])->middleware('permiso:contabilidad.ver');

    // Contabilidad - Formularios 29 y 22 (Renta)
    Route::get('/impuestos/cierre-f29/simular/{mes}/{anio}', [ImpuestosController::class, 'simularF29'])->middleware('permiso:tributario.ver');
    Route::post('/impuestos/cierre-f29/ejecutar', [ImpuestosController::class, 'ejecutarF29'])->middleware('permiso:tributario.crear');
    Route::get('/renta/pre-calculo/{anio}', [ImpuestosController::class, 'preCalculoRenta'])->middleware('permiso:tributario.ver');
    Route::get('/renta/mapeo', [ImpuestosController::class, 'obtenerMapeo'])->middleware('permiso:tributario.ver');
    Route::post('/renta/mapeo', [ImpuestosController::class, 'guardarMapeo'])->middleware('permiso:tributario.crear');
    Route::delete('/renta/mapeo/{id}', [ImpuestosController::class, 'eliminarMapeo'])->middleware('permiso:tributario.crear');

    // Correccion Monetaria (parte de contabilidad)
    Route::prefix('correccion-monetaria')->group(function () {
        Route::get('/indices/{anio}', [CorreccionMonetariaController::class, 'indices'])->middleware('permiso:contabilidad.ver');
        Route::post('/indices', [CorreccionMonetariaController::class, 'guardarIndice'])->middleware('permiso:contabilidad.crear');
        Route::get('/configuracion', [CorreccionMonetariaController::class, 'configuracion'])->middleware('permiso:contabilidad.ver');
        Route::put('/configuracion', [CorreccionMonetariaController::class, 'actualizarConfiguracion'])->middleware('permiso:contabilidad.crear');
        Route::get('/cuentas', [CorreccionMonetariaController::class, 'cuentasConfiguracion'])->middleware('permiso:contabilidad.ver');
        Route::put('/cuentas', [CorreccionMonetariaController::class, 'actualizarCuentasConfiguracion'])->middleware('permiso:contabilidad.crear');
        Route::post('/cuentas', [CorreccionMonetariaController::class, 'agregarCuenta'])->middleware('permiso:contabilidad.crear');
        Route::get('/estado/{mes}/{anio}', [CorreccionMonetariaController::class, 'estadoPeriodo'])->middleware('permiso:contabilidad.ver');
        Route::get('/simular/{mes}/{anio}', [CorreccionMonetariaController::class, 'simular'])->middleware('permiso:contabilidad.ver');
        Route::post('/ejecutar', [CorreccionMonetariaController::class, 'ejecutar'])->middleware('permiso:contabilidad.crear');
        Route::get('/historial', [CorreccionMonetariaController::class, 'historial'])->middleware('permiso:contabilidad.ver');
    });

    // Contabilidad - Anulaciones
    Route::post('/anulacion/buscar', [AnulacionController::class, 'buscar'])->middleware('permiso:compras.ver,ventas.ver,contabilidad.ver');
    Route::post('/anulacion/anular', [AnulacionController::class, 'anular'])->middleware('permiso:compras.crear,ventas.crear,contabilidad.crear');

    // ---------------------------------------------------------------------
    // Activos Fijos
    // ---------------------------------------------------------------------
    Route::get('/activos', [ActivoFijoController::class, 'index'])->middleware('permiso:activos.ver');
    Route::get('/activos/pendientes', [ActivoFijoController::class, 'pendientes'])->middleware('permiso:activos.ver');
    Route::post('/activos', [ActivoFijoController::class, 'store'])->middleware('permiso:activos.crear');
    Route::get('/activos/parametros', [ActivoFijoController::class, 'parametros'])->middleware('permiso:activos.ver');
    Route::post('/activos/depreciar-mes', [ActivoFijoController::class, 'depreciarMes'])->middleware('permiso:activos.crear');
    Route::put('/activos/{id}/baja', [ActivoFijoController::class, 'darDeBaja'])->middleware('permiso:activos.crear');
    Route::put('/activos/{id}', [ActivoFijoController::class, 'update'])->middleware('permiso:activos.crear');

    // Activos Fijos - Proyectos
    Route::get('/activos/proyectos/facturas-disponibles', [ActivoFijoController::class, 'facturasDisponibles'])->middleware('permiso:activos.ver');
    Route::post('/activos/proyectos/{id}/facturas', [ActivoFijoController::class, 'imputarFactura'])->middleware('permiso:activos.crear');
    Route::put('/activos/proyectos/{id}/activar', [ActivoFijoController::class, 'activarProyecto'])->middleware('permiso:activos.crear');
    Route::put('/activos/proyectos/{id}', [ActivoFijoController::class, 'updateProyecto'])->middleware('permiso:activos.crear');
    Route::delete('/activos/proyectos/{id}', [ActivoFijoController::class, 'deleteProyecto'])->middleware('permiso:activos.crear');
    Route::delete('/activos/proyectos/{proyectoId}/facturas/{facturaId}', [ActivoFijoController::class, 'desvincularFactura'])->middleware('permiso:activos.crear');
    Route::get('/activos/proyectos', [ActivoFijoController::class, 'proyectos'])->middleware('permiso:activos.ver');
    Route::post('/activos/proyectos', [ActivoFijoController::class, 'storeProyecto'])->middleware('permiso:activos.crear');
    Route::get('/activos/proyectos/{id}/analisis', [ActivoFijoController::class, 'analisisProyecto'])->middleware('permiso:activos.ver');

    // ---------------------------------------------------------------------
    // Inventario, Bodegas y Movimientos
    // ---------------------------------------------------------------------
    Route::prefix('inventario')->group(function () {
        Route::get('/dashboard', [InventarioController::class, 'dashboard'])->middleware('permiso:inventario.dashboard.ver');

        Route::get('/auditoria', [InventarioAuditoriaController::class, 'index'])->middleware('permiso:inventario.auditoria.ver');
        Route::get('/auditoria/resumen', [InventarioAuditoriaController::class, 'resumen'])->middleware('permiso:inventario.auditoria.resumen,inventario.auditoria.ver');
        Route::get('/auditoria/{id}', [InventarioAuditoriaController::class, 'show'])->middleware('permiso:inventario.auditoria.detalle,inventario.auditoria.ver');

        Route::get('/eventos-integracion', [InventarioEventoIntegracionController::class, 'index'])->middleware('permiso:inventario.eventos_integracion.ver');
        Route::get('/eventos-integracion/resumen', [InventarioEventoIntegracionController::class, 'resumen'])->middleware('permiso:inventario.eventos_integracion.resumen,inventario.eventos_integracion.ver');
        Route::get('/eventos-integracion/{id}', [InventarioEventoIntegracionController::class, 'show'])->middleware('permiso:inventario.eventos_integracion.detalle,inventario.eventos_integracion.ver');
        Route::post('/eventos-integracion/{id}/procesar', [InventarioEventoIntegracionController::class, 'procesar'])->middleware('permiso:inventario.eventos_integracion.procesar,inventario.eventos_integracion.gestionar');
        Route::post('/eventos-integracion/{id}/ignorar', [InventarioEventoIntegracionController::class, 'ignorar'])->middleware('permiso:inventario.eventos_integracion.gestionar');
        Route::post('/eventos-integracion/{id}/error', [InventarioEventoIntegracionController::class, 'error'])->middleware('permiso:inventario.eventos_integracion.gestionar');

        Route::get('/reportes/stock', [InventarioController::class, 'reporteStock'])->middleware('permiso:inventario.reportes.ver');
        Route::get('/reportes/movimientos', [InventarioController::class, 'reporteMovimientos'])->middleware('permiso:inventario.reportes.ver');
        Route::get('/reportes/valorizacion', [InventarioController::class, 'reporteValorizacion'])->middleware('permiso:inventario.reportes.ver');
        Route::get('/reportes/lotes', [InventarioController::class, 'reporteLotes'])->middleware('permiso:inventario.reportes.ver');
        Route::get('/reportes/reservas', [InventarioController::class, 'reporteReservas'])->middleware('permiso:inventario.reportes.ver');
        Route::get('/reportes/tomas-fisicas', [InventarioController::class, 'reporteTomasFisicas'])->middleware('permiso:inventario.reportes.ver');
        Route::get('/reportes/ajustes', [InventarioController::class, 'reporteAjustes'])->middleware('permiso:inventario.reportes.ver');
        Route::get('/reportes/reposicion-alertas', [InventarioController::class, 'reporteReposicionAlertas'])->middleware('permiso:inventario.reportes.ver');
        Route::get('/reportes/picking', [InventarioPickingController::class, 'reporte'])->middleware('permiso:inventario.reportes.picking,inventario.reportes.ver');
        Route::get('/reportes/packing', [InventarioPackingController::class, 'reporte'])->middleware('permiso:inventario.reportes.packing,inventario.reportes.ver');
        Route::get('/reportes/despachos', [InventarioDespachoController::class, 'reporte'])->middleware('permiso:inventario.reportes.despachos,inventario.reportes.ver');
        Route::get('/reportes/devoluciones', [InventarioDevolucionController::class, 'reporte'])->middleware('permiso:inventario.reportes.devoluciones,inventario.reportes.ver');
        Route::get('/reportes/{tipo}/exportar-csv', [InventarioController::class, 'exportarReporteCsv'])->middleware('permiso:inventario.reportes.exportar,inventario.reportes.ver');

        Route::get('/catalogos', [InventarioController::class, 'catalogos'])->middleware('permiso:inventario.productos.ver');

        Route::get('/ubicaciones', [InventarioController::class, 'ubicaciones'])->middleware('permiso:inventario.ubicaciones.ver');
        Route::post('/ubicaciones', [InventarioController::class, 'storeUbicacion'])->middleware('permiso:inventario.ubicaciones.crear');
        Route::get('/ubicaciones/{id}/stock', [InventarioController::class, 'stockUbicacion'])->middleware('permiso:inventario.ubicaciones.ver,inventario.stock_ubicaciones.ver');
        Route::get('/ubicaciones/{id}', [InventarioController::class, 'showUbicacion'])->middleware('permiso:inventario.ubicaciones.ver');
        Route::put('/ubicaciones/{id}', [InventarioController::class, 'updateUbicacion'])->middleware('permiso:inventario.ubicaciones.editar');
        Route::get('/stock-ubicaciones', [InventarioController::class, 'stockUbicaciones'])->middleware('permiso:inventario.stock_ubicaciones.ver');
        Route::post('/stock-ubicaciones/mover', [InventarioController::class, 'moverStockUbicacion'])->middleware('permiso:inventario.stock_ubicaciones.mover');
        Route::post('/putaway/confirmar', [InventarioController::class, 'confirmarPutaway'])->middleware('permiso:inventario.putaway.ejecutar');

        Route::get('/picking', [InventarioPickingController::class, 'index'])->middleware('permiso:inventario.picking.ver');
        Route::post('/picking', [InventarioPickingController::class, 'store'])->middleware('permiso:inventario.picking.crear');
        Route::get('/picking/{id}', [InventarioPickingController::class, 'show'])->middleware('permiso:inventario.picking.ver');
        Route::post('/picking/{id}/asignar', [InventarioPickingController::class, 'asignar'])->middleware('permiso:inventario.picking.editar');
        Route::post('/picking/{id}/iniciar', [InventarioPickingController::class, 'iniciar'])->middleware('permiso:inventario.picking.editar');
        Route::post('/picking/{id}/confirmar', [InventarioPickingController::class, 'confirmar'])->middleware('permiso:inventario.picking.confirmar');
        Route::post('/picking/{id}/cancelar', [InventarioPickingController::class, 'cancelar'])->middleware('permiso:inventario.picking.cancelar');

        Route::get('/packing', [InventarioPackingController::class, 'index'])->middleware('permiso:inventario.packing.ver');
        Route::post('/packing', [InventarioPackingController::class, 'store'])->middleware('permiso:inventario.packing.crear');
        Route::get('/packing/{id}', [InventarioPackingController::class, 'show'])->middleware('permiso:inventario.packing.ver');
        Route::post('/packing/{id}/iniciar', [InventarioPackingController::class, 'iniciar'])->middleware('permiso:inventario.packing.editar');
        Route::post('/packing/{id}/confirmar', [InventarioPackingController::class, 'confirmar'])->middleware('permiso:inventario.packing.confirmar');
        Route::post('/packing/{id}/cancelar', [InventarioPackingController::class, 'cancelar'])->middleware('permiso:inventario.packing.cancelar');

        Route::get('/despachos', [InventarioDespachoController::class, 'index'])->middleware('permiso:inventario.despachos.ver');
        Route::post('/despachos', [InventarioDespachoController::class, 'store'])->middleware('permiso:inventario.despachos.crear');
        Route::get('/despachos/{id}/reversable', [InventarioDevolucionController::class, 'reversable'])->middleware('permiso:inventario.devoluciones.ver,inventario.despachos.ver');
        Route::get('/despachos/{id}', [InventarioDespachoController::class, 'show'])->middleware('permiso:inventario.despachos.ver');
        Route::post('/despachos/{id}/iniciar', [InventarioDespachoController::class, 'iniciar'])->middleware('permiso:inventario.despachos.editar');
        Route::post('/despachos/{id}/confirmar', [InventarioDespachoController::class, 'confirmar'])->middleware('permiso:inventario.despachos.confirmar');
        Route::post('/despachos/{id}/cancelar', [InventarioDespachoController::class, 'cancelar'])->middleware('permiso:inventario.despachos.cancelar');

        Route::get('/devoluciones', [InventarioDevolucionController::class, 'index'])->middleware('permiso:inventario.devoluciones.ver');
        Route::post('/devoluciones', [InventarioDevolucionController::class, 'store'])->middleware('permiso:inventario.devoluciones.crear');
        Route::get('/devoluciones/{id}', [InventarioDevolucionController::class, 'show'])->middleware('permiso:inventario.devoluciones.ver');
        Route::post('/devoluciones/{id}/confirmar', [InventarioDevolucionController::class, 'confirmar'])->middleware('permiso:inventario.devoluciones.confirmar');
        Route::post('/devoluciones/{id}/cancelar', [InventarioDevolucionController::class, 'cancelar'])->middleware('permiso:inventario.devoluciones.cancelar');

        Route::get('/productos', [InventarioController::class, 'index'])->middleware('permiso:inventario.productos.ver');
        Route::post('/productos', [InventarioController::class, 'store'])->middleware('permiso:inventario.productos.crear');

        Route::get('/bodegas', [InventarioController::class, 'bodegas'])->middleware('permiso:inventario.bodegas.ver');
        Route::post('/bodegas', [InventarioController::class, 'storeBodega'])->middleware('permiso:inventario.bodegas.crear');

        Route::get('/movimientos', [InventarioController::class, 'movimientos'])->middleware('permiso:inventario.movimientos.ver');
        Route::post('/movimientos', [InventarioController::class, 'registrarMovimiento'])->middleware('permiso:inventario.movimientos.entrada,inventario.movimientos.salida,inventario.movimientos.traspaso,inventario.movimientos.ajuste');

        Route::get('/kardex', [InventarioController::class, 'kardex'])->middleware('permiso:inventario.kardex.ver');
        Route::get('/productos/{id}/kardex', [InventarioController::class, 'kardexProducto'])->middleware('permiso:inventario.kardex.ver');

        Route::get('/valorizacion', [InventarioController::class, 'valorizacion'])->middleware('permiso:inventario.valorizacion.ver');
        Route::get('/productos/{id}/valorizacion', [InventarioController::class, 'valorizacionProducto'])->middleware('permiso:inventario.valorizacion.ver');

        Route::get('/ajustes-criticos/tipos', [InventarioController::class, 'tiposAjusteCritico'])->middleware('permiso:inventario.ajustes_criticos.ver');
        Route::get('/ajustes-criticos', [InventarioController::class, 'ajustesCriticos'])->middleware('permiso:inventario.ajustes_criticos.ver');
        Route::post('/ajustes-criticos', [InventarioController::class, 'registrarAjusteCritico'])->middleware('permiso:inventario.ajustes_criticos.crear');
        Route::get('/ajustes-criticos/{id}', [InventarioController::class, 'verAjusteCritico'])->middleware('permiso:inventario.ajustes_criticos.ver');

        Route::get('/lotes', [InventarioController::class, 'lotes'])->middleware('permiso:inventario.lotes.ver');
        Route::post('/lotes', [InventarioController::class, 'storeLote'])->middleware('permiso:inventario.lotes.crear');
        Route::get('/lotes/{id}/stock', [InventarioController::class, 'stockLote'])->middleware('permiso:inventario.lotes.ver');
        Route::get('/lotes/{id}', [InventarioController::class, 'showLote'])->middleware('permiso:inventario.lotes.ver');
        Route::put('/lotes/{id}', [InventarioController::class, 'updateLote'])->middleware('permiso:inventario.lotes.editar');

        Route::get('/productos/{id}/lotes', [InventarioController::class, 'lotesProducto'])->middleware('permiso:inventario.lotes.ver');

        Route::get('/disponibilidad', [InventarioController::class, 'disponibilidad'])->middleware('permiso:inventario.disponibilidad.ver');
        Route::get('/productos/{id}/disponibilidad', [InventarioController::class, 'disponibilidadProducto'])->middleware('permiso:inventario.disponibilidad.ver');

        Route::get('/reglas-reposicion', [InventarioController::class, 'reglasReposicion'])->middleware('permiso:inventario.reglas_reposicion.ver');
        Route::post('/reglas-reposicion', [InventarioController::class, 'storeReglaReposicion'])->middleware('permiso:inventario.reglas_reposicion.crear');
        Route::get('/reglas-reposicion/{id}', [InventarioController::class, 'showReglaReposicion'])->middleware('permiso:inventario.reglas_reposicion.ver');
        Route::put('/reglas-reposicion/{id}', [InventarioController::class, 'updateReglaReposicion'])->middleware('permiso:inventario.reglas_reposicion.editar');
        Route::delete('/reglas-reposicion/{id}', [InventarioController::class, 'destroyReglaReposicion'])->middleware('permiso:inventario.reglas_reposicion.eliminar');
        Route::get('/alertas', [InventarioController::class, 'alertas'])->middleware('permiso:inventario.alertas.ver');
        Route::get('/reposicion/sugerencias', [InventarioController::class, 'sugerenciasReposicion'])->middleware('permiso:inventario.reglas_reposicion.ver,inventario.alertas.ver');

        Route::get('/reservas', [InventarioController::class, 'reservas'])->middleware('permiso:inventario.reservas.ver');
        Route::post('/reservas', [InventarioController::class, 'storeReserva'])->middleware('permiso:inventario.reservas.crear');
        Route::get('/reservas/{id}', [InventarioController::class, 'showReserva'])->middleware('permiso:inventario.reservas.ver');
        Route::post('/reservas/{id}/cancelar', [InventarioController::class, 'cancelarReserva'])->middleware('permiso:inventario.reservas.cancelar');
        Route::post('/reservas/{id}/liberar', [InventarioController::class, 'liberarReserva'])->middleware('permiso:inventario.reservas.liberar');
        Route::post('/reservas/{id}/consumir', [InventarioController::class, 'consumirReserva'])->middleware('permiso:inventario.reservas.consumir');

        Route::get('/productos/{id}', [InventarioController::class, 'show'])->middleware('permiso:inventario.productos.ver');
        Route::put('/productos/{id}', [InventarioController::class, 'update'])->middleware('permiso:inventario.productos.editar');

        Route::get('/tomas-fisicas', [InventarioController::class, 'tomasFisicas'])->middleware('permiso:inventario.tomas_fisicas.ver');
        Route::post('/tomas-fisicas', [InventarioController::class, 'storeTomaFisica'])->middleware('permiso:inventario.tomas_fisicas.crear');
        Route::get('/tomas-fisicas/{id}', [InventarioController::class, 'showTomaFisica'])->middleware('permiso:inventario.tomas_fisicas.ver');
        Route::post('/tomas-fisicas/{id}/iniciar', [InventarioController::class, 'iniciarTomaFisica'])->middleware('permiso:inventario.tomas_fisicas.contar');
        Route::post('/tomas-fisicas/{id}/conteos', [InventarioController::class, 'registrarConteosTomaFisica'])->middleware('permiso:inventario.tomas_fisicas.contar');
        Route::post('/tomas-fisicas/{id}/cerrar', [InventarioController::class, 'cerrarTomaFisica'])->middleware('permiso:inventario.tomas_fisicas.cerrar');
        Route::post('/tomas-fisicas/{id}/ajustar', [InventarioController::class, 'ajustarTomaFisica'])->middleware('permiso:inventario.tomas_fisicas.ajustar');
        Route::post('/tomas-fisicas/{id}/cancelar', [InventarioController::class, 'cancelarTomaFisica'])->middleware('permiso:inventario.tomas_fisicas.cancelar');
    });
});

Route::prefix('internal/web')->middleware('web.api.key')->group(function () {
    Route::post('/provision-user', [WebProvisioningController::class, 'provisionUser']);
    Route::post('/sync-plan',      [WebProvisioningController::class, 'syncPlan']);
    Route::post('/sync-password',  [WebProvisioningController::class, 'syncPassword']);
    Route::get('/online-users',    [WebProvisioningController::class, 'onlineUsers']);

    Route::get('/empresas',        [AdminEmpresasController::class, 'index']);
    Route::get('/empresas/{id}',   [AdminEmpresasController::class, 'show']);
    Route::get('/usuarios',        [AdminEmpresasController::class, 'usuarios']);

    // Acciones administrativas (write-side multitenant)
    Route::post('/empresas/{id}/suspender', [AdminEmpresasController::class, 'suspender']);
    Route::post('/empresas/{id}/activar',   [AdminEmpresasController::class, 'activar']);
    Route::put('/empresas/{id}/plan',       [AdminEmpresasController::class, 'cambiarPlan']);
    Route::post('/usuarios/{id}/bloquear',    [AdminEmpresasController::class, 'bloquearUsuario']);
    Route::post('/usuarios/{id}/desbloquear', [AdminEmpresasController::class, 'desbloquearUsuario']);
});
