<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\Rol;
use App\Domains\Sii\Services\Xml\DteXmlBuilder;
use App\Domains\Sii\Services\Xml\DteXsdValidator;
use App\Domains\Sii\Services\Xml\Ted\TedBuilder;
use App\Observers\EmpresaObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // El container no auto-resuelve dependencias tipadas nullable con default null.
        // Bind explicito para que app(DteXmlBuilder::class) reciba TedBuilder y pueda
        // generar TED firmado (F4.2). Sin este bind, build($dte, $caf) lanza
        // LogicException porque tedBuilder llega como null.
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

        // HARDENING-1 R6 — Rate limiters por empresa para endpoints SII.
        //
        // 'sii-empresa': baseline 60 req/min por empresa (60 req/min por IP
        // si no hay usuario autenticado). Aplica a todas las rutas /api/sii.
        //
        // 'sii-uploads-pesados': 10 req/hora para uploads de certificado y
        // CAF (operaciones costosas y poco frecuentes en operacion real).
        //
        // El bucket por empresa garantiza aislamiento multi-tenant: una
        // empresa A excediendo su limite NO afecta a la empresa B.
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
            $permisos = is_string($rol->permisos) ? json_decode($rol->permisos, true) : ($rol->permisos ?? []);

            return is_array($permisos) && (in_array('contabilidad.crear', $permisos) || in_array('activos.crear', $permisos));
        });
    }
}