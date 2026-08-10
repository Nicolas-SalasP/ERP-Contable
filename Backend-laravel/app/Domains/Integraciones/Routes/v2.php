<?php

use App\Domains\Integraciones\Controllers\V2\InventarioProductoController;
use Illuminate\Support\Facades\Route;

Route::prefix('inventario')->group(function () {
    Route::middleware('integracion.scope:inventario:leer')->group(function () {
        Route::get('/productos', [InventarioProductoController::class, 'index']);
        Route::get('/productos/{sku}', [InventarioProductoController::class, 'show']);
    });

    Route::middleware('integracion.scope:inventario:escribir')
        ->patch('/productos/{sku}/visible-web', [InventarioProductoController::class, 'actualizarVisibleWeb']);
});
