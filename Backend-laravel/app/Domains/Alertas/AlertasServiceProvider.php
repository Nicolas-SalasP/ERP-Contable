<?php

namespace App\Domains\Alertas;

use App\Domains\Alertas\Console\Commands\MonitorearAlertasCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class AlertasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../../config/alertas.php', 'alertas');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MonitorearAlertasCommand::class,
            ]);
        }

        // Corre despues del monitoreo de certificados SII (09:00): revisa periodos, F29, CxC/CxP y RRHH.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('alertas:monitorear')
                ->dailyAt('08:30')
                ->timezone('America/Santiago')
                ->withoutOverlapping()
                ->onOneServer()
                ->name('alertas-monitorear');
        });
    }
}
