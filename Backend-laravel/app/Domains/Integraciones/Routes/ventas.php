<?php

use App\Domains\Integraciones\Controllers\V2\VentaIntegracionController;
use Illuminate\Support\Facades\Route;

Route::middleware('integracion.scope:ventas:escribir')->group(function () {
    Route::post('/reservas', [VentaIntegracionController::class, 'reservar']);
    Route::delete('/reservas/{id}', [VentaIntegracionController::class, 'liberar']);
    Route::post('/ventas', [VentaIntegracionController::class, 'confirmar']);
});

// Solo lectura: para que el canal externo haga polling del estado del DTE (folio/pdf_url).
Route::middleware('integracion.scope:ventas:leer')->group(function () {
    Route::get('/ventas/{facturaId}', [VentaIntegracionController::class, 'estado']);
});
