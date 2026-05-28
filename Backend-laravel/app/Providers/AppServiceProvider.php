<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\Rol;
use App\Observers\EmpresaObserver;
use App\Domains\CorreccionMonetaria\Providers\IpcProviderInterface;
use App\Domains\CorreccionMonetaria\Providers\ManualIpcProvider;
use App\Domains\CorreccionMonetaria\Providers\IneApiIpcProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(IpcProviderInterface::class, function () {
            $proveedor = config('correccion_monetaria.ipc_provider', 'manual');
            return match ($proveedor) {
                'api_ine' => new IneApiIpcProvider(),
                default => new ManualIpcProvider(),
            };
        });
    }

    public function boot(): void
    {
        Empresa::observe(EmpresaObserver::class);
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