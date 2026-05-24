<?php

namespace App\Domains\Sii;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class SiiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Reservado para inyeccion de servicios en fases futuras
        // (CertificadoService, CafService, XmlSignerService, etc.).
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');

        $this->mergeConfigFrom(__DIR__ . '/../../../config/sii.php', 'sii');

        Route::middleware(['api', 'auth:sanctum'])
            ->prefix('api/sii')
            ->group(__DIR__ . '/Routes/api.php');
    }
}
