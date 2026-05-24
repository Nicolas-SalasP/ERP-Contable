<?php

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