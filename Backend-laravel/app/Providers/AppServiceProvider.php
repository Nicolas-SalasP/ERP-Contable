<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Domains\Core\Models\Empresa;
use App\Observers\EmpresaObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Empresa::observe(EmpresaObserver::class);
    }
}
