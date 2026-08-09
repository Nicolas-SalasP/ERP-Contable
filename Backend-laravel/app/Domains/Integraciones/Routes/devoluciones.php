<?php

use App\Domains\Integraciones\Controllers\V2\DevolucionIntegracionController;
use Illuminate\Support\Facades\Route;

Route::middleware('integracion.scope:devoluciones:escribir')->group(function () {
    Route::post('/devoluciones', [DevolucionIntegracionController::class, 'crear']);
});
