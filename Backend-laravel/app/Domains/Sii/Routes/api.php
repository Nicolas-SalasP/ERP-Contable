<?php

use App\Domains\Sii\Http\Controllers\DteRetryController;
use App\Domains\Sii\Http\Controllers\FacturaSiiController;
use App\Domains\Sii\Http\Controllers\SiiCafController;
use App\Domains\Sii\Http\Controllers\SiiCertificadoController;
use App\Domains\Sii\Http\Controllers\SiiConfiguracionController;
use Illuminate\Support\Facades\Route;

/** Rutas del modulo SII, cargadas por SiiServiceProvider con prefix 'api/sii' y middleware ['api', 'auth:sanctum', 'throttle:sii-empresa']; permiso:* valida autorizacion granular por endpoint, ping queda solo autenticado. */

Route::get('ping', function () {
    $payload = [
        'modulo'    => 'sii',
        'estado'    => 'operativo',
        'ambiente'  => config('sii.ambiente'),
        'version'   => '0.1.0',
        'timestamp' => now()->toIso8601String(),
    ];

    return response()->json(array_merge([
        'success' => true,
        'data'    => $payload,
    ], $payload));
});

Route::get('configuracion', [SiiConfiguracionController::class, 'show'])
    ->middleware('permiso:sii.configuracion.ver');
Route::put('configuracion', [SiiConfiguracionController::class, 'update'])
    ->middleware('permiso:sii.configuracion.editar');

Route::get('certificado', [SiiCertificadoController::class, 'show'])
    ->middleware('permiso:sii.certificado.ver');
Route::post('certificado', [SiiCertificadoController::class, 'store'])
    ->middleware(['permiso:sii.certificado.subir', 'throttle:sii-uploads-pesados']);
Route::post('certificado/verificar', [SiiCertificadoController::class, 'verificar'])
    ->middleware('permiso:sii.certificado.ver');
Route::delete('certificado/{id}', [SiiCertificadoController::class, 'destroy'])
    ->whereNumber('id')
    ->middleware('permiso:sii.certificado.revocar');

Route::prefix('caf')->group(function () {
    Route::get('saldos', [SiiCafController::class, 'saldos'])
        ->middleware('permiso:sii.caf.ver');
    Route::get('/', [SiiCafController::class, 'index'])
        ->middleware('permiso:sii.caf.ver');
    Route::post('/', [SiiCafController::class, 'store'])
        ->middleware(['permiso:sii.caf.subir', 'throttle:sii-uploads-pesados']);
    Route::get('{id}', [SiiCafController::class, 'show'])
        ->whereNumber('id')
        ->middleware('permiso:sii.caf.ver');
    Route::delete('{id}', [SiiCafController::class, 'destroy'])
        ->whereNumber('id')
        ->middleware('permiso:sii.caf.revocar');
});

Route::prefix('dte')->group(function () {
    Route::post('{siiDteEmitido}/reintentar', [DteRetryController::class, 'reintentar'])
        ->whereNumber('siiDteEmitido')
        ->middleware('permiso:sii.dte.reintentar');
});

Route::prefix('facturas')->group(function () {
    Route::get('/', [FacturaSiiController::class, 'index'])
        ->middleware('permiso:sii.dte.ver');
    Route::get('{factura_id}/estado', [FacturaSiiController::class, 'estado'])
        ->whereNumber('factura_id')
        ->middleware('permiso:sii.dte.ver');
    Route::post('{factura_id}/reintentar', [FacturaSiiController::class, 'reintentar'])
        ->whereNumber('factura_id')
        ->middleware('permiso:sii.dte.reintentar');
    Route::get('{factura_id}', [FacturaSiiController::class, 'mostrar'])
        ->whereNumber('factura_id')
        ->middleware('permiso:sii.dte.ver');
});
