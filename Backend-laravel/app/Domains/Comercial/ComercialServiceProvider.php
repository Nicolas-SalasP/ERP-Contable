<?php

namespace App\Domains\Comercial;

use App\Domains\Comercial\Console\Commands\VincularCotizacionFacturaCommand;
use Illuminate\Support\ServiceProvider;

class ComercialServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                VincularCotizacionFacturaCommand::class,
            ]);
        }
    }
}
