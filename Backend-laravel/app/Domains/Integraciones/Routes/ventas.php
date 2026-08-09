<?php

use App\Domains\Integraciones\Controllers\V2\VentaIntegracionController;
use Illuminate\Support\Facades\Route;

Route::middleware('integracion.scope:ventas:escribir')->group(function () {
    Route::post('/reservas', [VentaIntegracionController::class, 'reservar']);
    Route::delete('/reservas/{id}', [VentaIntegracionController::class, 'liberar']);
    Route::post('/ventas', [VentaIntegracionController::class, 'confirmar']);
});
