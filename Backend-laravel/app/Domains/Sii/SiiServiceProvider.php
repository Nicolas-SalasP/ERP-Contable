<?php

namespace App\Domains\Sii;

use App\Domains\Sii\Console\Commands\CargarCafCommand;
use App\Domains\Sii\Console\Commands\EmitirDtePruebaCommand;
use App\Domains\Sii\Console\Commands\EnviarDtePruebaCommand;
use App\Domains\Sii\Console\Commands\FlujoCompletoPruebaCommand;
use App\Domains\Sii\Console\Commands\GenerarXmlPruebaCommand;
use App\Domains\Sii\Console\Commands\ListarEnviosFallidosCommand;
use App\Domains\Sii\Console\Commands\MonitorearCertificadosCommand;
use App\Domains\Sii\Console\Commands\ObtenerTokenPruebaCommand;
use App\Domains\Sii\Events\FacturaListaParaEmitirEvent;
use App\Domains\Sii\Jobs\PollearEnviosPendientesJob;
use App\Domains\Sii\Listeners\ProcesarFacturaParaSiiListener;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
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

        // HARDENING-1 R6: throttle por empresa (60 req/min) en TODAS las rutas SII.
        // El throttle 'sii-uploads-pesados' (10/h) se aplica adicionalmente a
        // endpoints especificos dentro de Routes/api.php (cert + caf store).
        Route::middleware(['api', 'auth:sanctum', 'throttle:sii-empresa'])
            ->prefix('api/sii')
            ->group(__DIR__ . '/Routes/api.php');

        // Views del modulo (namespace 'sii::')
        $this->loadViewsFrom(__DIR__ . '/Resources/views', 'sii');

        // Comandos artisan
        if ($this->app->runningInConsole()) {
            $this->commands([
                MonitorearCertificadosCommand::class,
                CargarCafCommand::class,
                GenerarXmlPruebaCommand::class,
                EmitirDtePruebaCommand::class,
                ObtenerTokenPruebaCommand::class,
                EnviarDtePruebaCommand::class,
                ListarEnviosFallidosCommand::class,
                FlujoCompletoPruebaCommand::class,
            ]);
        }

        // Schedule: monitoreo diario de vencimiento de certificados a las 09:00 hora Chile.
        // F5.3: polling de envios pendientes cada 5 minutos.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('sii:monitorear-certificados')
                ->dailyAt('09:00')
                ->timezone('America/Santiago')
                ->withoutOverlapping()
                ->onOneServer()
                ->name('sii-monitorear-certificados-vencimiento');

            $schedule->job(new PollearEnviosPendientesJob())
                ->everyFiveMinutes()
                ->name('sii-pollear-envios-pendientes')
                ->onOneServer()
                ->withoutOverlapping(10);
        });

        // F6.2 — Puerto de entrada desde Comercial. El evento dispara el
        // listener async (queue=sii) que orquesta map -> emit -> send.
        // ShouldDispatchAfterCommit del evento garantiza que jobs solo se
        // encolan si la transaccion contenedora commitea.
        Event::listen(
            FacturaListaParaEmitirEvent::class,
            [ProcesarFacturaParaSiiListener::class, 'handle']
        );
    }
}
