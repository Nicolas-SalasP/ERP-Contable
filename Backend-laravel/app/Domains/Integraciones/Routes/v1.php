<?php

use App\Domains\Integraciones\Controllers\V1\InventarioProductoController;
use App\Domains\Integraciones\Controllers\V1\PingController;
use Illuminate\Support\Facades\Route;

Route::middleware('integracion.scope:inventario:leer')->get('/ping', [PingController::class, 'index']);

Route::prefix('inventario')->group(function () {
    Route::middleware('integracion.scope:inventario:leer')->group(function () {
        Route::get('/productos', [InventarioProductoController::class, 'index']);
        Route::get('/productos/{sku}', [InventarioProductoController::class, 'show']);
    });

    Route::middleware('integracion.scope:inventario:escribir')
        ->patch('/productos/{sku}/visible-web', [InventarioProductoController::class, 'actualizarVisibleWeb']);
});
