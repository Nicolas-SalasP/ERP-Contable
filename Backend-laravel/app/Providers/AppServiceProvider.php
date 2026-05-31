<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\Rol;
use App\Observers\EmpresaObserver;

// CorreccionMonetaria
use App\Domains\CorreccionMonetaria\Providers\IpcProviderInterface;
use App\Domains\CorreccionMonetaria\Providers\ManualIpcProvider;
use App\Domains\CorreccionMonetaria\Providers\IneApiIpcProvider;

// SII
use App\Domains\Sii\Services\Xml\DteXmlBuilder;
use App\Domains\Sii\Services\Xml\DteXsdValidator;
use App\Domains\Sii\Services\Xml\Ted\TedBuilder;

// Inventario
use App\Domains\Inventario\Events\LoteVencidoDetectado;
use App\Domains\Inventario\Events\StockMinimoPerforado;
use App\Domains\Inventario\Events\TomaFisicaConfirmada;
use App\Domains\Inventario\Listeners\RegistrarEventoInventarioListener;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // CorreccionMonetaria — proveedor de índices IPC configurable
        $this->app->bind(IpcProviderInterface::class, function () {
            $proveedor = config('correccion_monetaria.ipc_provider', 'manual');
            return match ($proveedor) {
                'api_ine' => new IneApiIpcProvider(),
                default   => new ManualIpcProvider(),
            };
        });

        // SII — Bind explícito para que DteXmlBuilder reciba TedBuilder
        // El container no auto-resuelve dependencias nullable con default null.
        $this->app->bind(DteXmlBuilder::class, function ($app) {
            return new DteXmlBuilder(
                $app->make(DteXsdValidator::class),
                $app->make(TedBuilder::class)
            );
        });
    }

    public function boot(): void
    {
        Empresa::observe(EmpresaObserver::class);

        // Inventario — eventos de dominio
        Event::listen(StockMinimoPerforado::class, RegistrarEventoInventarioListener::class);
        Event::listen(LoteVencidoDetectado::class, RegistrarEventoInventarioListener::class);
        Event::listen(TomaFisicaConfirmada::class, RegistrarEventoInventarioListener::class);

        // SII — Rate limiters por empresa (HARDENING-1 R6)
        RateLimiter::for('sii-empresa', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->empresa_id ?? $request->ip());
        });

        RateLimiter::for('sii-uploads-pesados', function (Request $request) {
            return Limit::perHour(10)->by($request->user()?->empresa_id ?? $request->ip());
        });

        Gate::define('gestionar-contabilidad-critica', function ($user) {
            $rol = Rol::find($user->rol_id);

            if (!$rol) {
                return false;
            }

            if ($rol->jerarquia >= 80) {
                return true;
            }

            $permisos = is_string($rol->permisos)
                ? json_decode($rol->permisos, true)
                : ($rol->permisos ?? []);

            return is_array($permisos) && (
                in_array('contabilidad.crear', $permisos) ||
                in_array('activos.crear', $permisos)
            );
        });
    }
}
