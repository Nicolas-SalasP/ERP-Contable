<?php

use App\Domains\Integraciones\Controllers\IntegracionApiKeyController;
use Illuminate\Support\Facades\Route;

Route::middleware('permiso:integraciones.api.ver')
    ->get('/keys', [IntegracionApiKeyController::class, 'index']);

Route::middleware('permiso:integraciones.api.gestionar')->group(function () {
    Route::post('/keys', [IntegracionApiKeyController::class, 'store']);
    Route::post('/keys/{id}/rotar', [IntegracionApiKeyController::class, 'rotar']);
    Route::delete('/keys/{id}', [IntegracionApiKeyController::class, 'destroy']);
});
