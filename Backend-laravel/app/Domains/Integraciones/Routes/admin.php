<?php

use App\Domains\Integraciones\Controllers\IntegracionApiKeyController;
use Illuminate\Support\Facades\Route;

Route::middleware('permiso:integraciones.api.ver')
    ->get('/keys', [IntegracionApiKeyController::class, 'index']);

// Emitir/rotar/revocar son escrituras: igual que el resto del proyecto, se bloquean si la
// suscripcion de la empresa esta en modo solo-lectura (vencida/expirada).
Route::middleware(['permiso:integraciones.api.gestionar', 'subscription.writable'])->group(function () {
    Route::post('/keys', [IntegracionApiKeyController::class, 'store']);
    Route::post('/keys/{id}/rotar', [IntegracionApiKeyController::class, 'rotar']);
    Route::delete('/keys/{id}', [IntegracionApiKeyController::class, 'destroy']);
});
