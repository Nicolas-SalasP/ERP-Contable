<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

/**
 * Respaldo para servir archivos del disco 'public' (ej. logos de empresa en
 * empresas/logos/...). En producción, si no se ejecutó `php artisan storage:link`,
 * el symlink public/storage no existe y nginx cae a index.php: sin esta ruta,
 * /storage/... devolvia 404 y los logos aparecian rotos. Si el symlink SI existe,
 * nginx sirve el archivo estatico y nunca se llega aqui (esto es solo el respaldo).
 */
Route::get('/storage/{ruta}', function (string $ruta) {
    // Bloquea path traversal: solo se sirve lo que viva dentro del disco public.
    if (str_contains($ruta, '..')) {
        abort(404);
    }

    $disk = Storage::disk('public');
    if (!$disk->exists($ruta)) {
        abort(404);
    }

    return $disk->response($ruta);
})->where('ruta', '.*');
