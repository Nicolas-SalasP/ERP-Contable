<?php

use App\Domains\Sii\Http\Controllers\FacturaSiiController;
use App\Domains\Sii\Http\Controllers\SiiCafController;
use App\Domains\Sii\Http\Controllers\SiiCertificadoController;
use App\Domains\Sii\Http\Controllers\SiiConfiguracionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas del modulo SII
|--------------------------------------------------------------------------
|
| Cargadas por SiiServiceProvider con prefix 'api/sii' y middleware
| ['api', 'auth:sanctum']. NO modificar routes/api.php raiz: este archivo
| es la unica fuente de verdad para endpoints SII.
|
*/

Route::get('ping', function () {
    return response()->json([
        'modulo'    => 'sii',
        'estado'    => 'operativo',
        'ambiente'  => config('sii.ambiente'),
        'version'   => '0.1.0',
        'timestamp' => now()->toIso8601String(),
    ]);
});

// ---------------------------------------------------------------------
// Configuracion SII por empresa (F2.1)
// ---------------------------------------------------------------------
Route::get('configuracion', [SiiConfiguracionController::class, 'show']);
Route::put('configuracion', [SiiConfiguracionController::class, 'update']);

// ---------------------------------------------------------------------
// Certificado digital .pfx (F2.1)
// ---------------------------------------------------------------------
Route::get('certificado', [SiiCertificadoController::class, 'show']);
// HARDENING-1 R6: throttle adicional 10/h en upload de cert (operacion costosa).
Route::post('certificado', [SiiCertificadoController::class, 'store'])
    ->middleware('throttle:sii-uploads-pesados');
Route::post('certificado/verificar', [SiiCertificadoController::class, 'verificar']);
Route::delete('certificado/{id}', [SiiCertificadoController::class, 'destroy']);

// ---------------------------------------------------------------------
// Folios CAF (F3.2)
// ---------------------------------------------------------------------
// IMPORTANTE: /saldos antes de /{id} + constraint whereNumber para evitar
// que Laravel matchee "saldos" como ID en show().
Route::prefix('caf')->group(function () {
    Route::get('saldos',  [SiiCafController::class, 'saldos']);
    Route::get('/',       [SiiCafController::class, 'index']);
    // HARDENING-1 R6: throttle adicional 10/h en upload de CAF.
    Route::post('/',      [SiiCafController::class, 'store'])
        ->middleware('throttle:sii-uploads-pesados');
    Route::get('{id}',    [SiiCafController::class, 'show'])->whereNumber('id');
    Route::delete('{id}', [SiiCafController::class, 'destroy'])->whereNumber('id');
});

// ---------------------------------------------------------------------
// Facturas — visibilidad de estado SII (F6.3) + reintento manual (F6.4)
// ---------------------------------------------------------------------
// throttle:sii-empresa heredado del grupo padre en SiiServiceProvider.
Route::prefix('facturas')->group(function () {
    Route::get('/',                            [FacturaSiiController::class, 'index']);
    Route::get('{factura_id}/estado',          [FacturaSiiController::class, 'estado'])->whereNumber('factura_id');
    Route::post('{factura_id}/reintentar',     [FacturaSiiController::class, 'reintentar'])->whereNumber('factura_id');
    Route::get('{factura_id}',                 [FacturaSiiController::class, 'mostrar'])->whereNumber('factura_id');
});