<?php

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
Route::post('certificado', [SiiCertificadoController::class, 'store']);
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
    Route::post('/',      [SiiCafController::class, 'store']);
    Route::get('{id}',    [SiiCafController::class, 'show'])->whereNumber('id');
    Route::delete('{id}', [SiiCafController::class, 'destroy'])->whereNumber('id');
});