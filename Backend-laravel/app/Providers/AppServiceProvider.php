<?php

namespace App\Providers;

use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Contabilidad\Models\AsientoContable;
use App\Domains\Contabilidad\Observers\AsientoContableObserver;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Observers\AuditoriaPiiObserver;
use App\Domains\CorreccionMonetaria\Providers\IneApiIpcProvider;
use App\Domains\CorreccionMonetaria\Providers\IpcProviderInterface;
use App\Domains\CorreccionMonetaria\Providers\ManualIpcProvider;
use App\Domains\Inventario\Events\LoteVencidoDetectado;
use App\Domains\Inventario\Events\StockMinimoPerforado;
use App\Domains\Inventario\Events\TomaFisicaConfirmada;
use App\Domains\Inventario\Listeners\RegistrarEventoInventarioListener;
use App\Domains\Rrhh\Models\CargaFamiliar;
use App\Domains\Rrhh\Models\Contrato;
use App\Domains\Rrhh\Models\Empleado;
use App\Domains\Rrhh\Models\Liquidacion;
use App\Domains\Sii\Services\Xml\DteXmlBuilder;
use App\Domains\Sii\Services\Xml\DteXsdValidator;
use App\Domains\Sii\Services\Xml\Ted\TedBuilder;
use App\Domains\Tesoreria\Models\CuentaBancariaEmpresa;
use App\Domains\Tesoreria\Models\CuentaBancariaProveedor;
use App\Observers\EmpresaObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // CorreccionMonetaria — proveedor de índices IPC configurable
        $this->app->bind(IpcProviderInterface::class, function () {
            $proveedor = config('correccion_monetaria.ipc_provider', 'manual');

            return match ($proveedor) {
                'api_ine' => new IneApiIpcProvider,
                default => new ManualIpcProvider,
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
        // Cambios sensibles (ej. regimen_tributario, que decide si aplica correccion
        // monetaria) no quedaban en ningun log; el propio provisioning via HMAC no tiene
        // usuario autenticado, por eso el observer generico cae a 'Sistema' en ese caso.
        Empresa::observe(AuditoriaPiiObserver::class);

        // Contabilidad — bloqueo de periodo cerrado (inmutabilidad, F-1/F-2).
        AsientoContable::observe(AsientoContableObserver::class);

        // Auditoria PII — Fase 3 (Ley 21.719): registra CREAR/ACTUALIZAR/ELIMINAR
        // sobre modelos que contienen datos personales sensibles. Nunca almacena
        // valores, solo nombres de campos (array_keys de getChanges()).
        Empleado::observe(AuditoriaPiiObserver::class);
        Contrato::observe(AuditoriaPiiObserver::class);
        Liquidacion::observe(AuditoriaPiiObserver::class);
        CargaFamiliar::observe(AuditoriaPiiObserver::class);
        CuentaBancariaEmpresa::observe(AuditoriaPiiObserver::class);
        CuentaBancariaProveedor::observe(AuditoriaPiiObserver::class);
        // Cliente/Proveedor tienen PII propia (rut, email, telefono, direccion)
        // y habian quedado fuera del observer pese a estar cubiertos por la
        // misma obligacion (Ley 21.719).
        Cliente::observe(AuditoriaPiiObserver::class);
        Proveedor::observe(AuditoriaPiiObserver::class);

        // Facturas/notas de credito-debito y asientos contables no dejaban rastro real de
        // anulacion/reclasificacion/reversa (la pantalla de "auditoria" mostraba solo un
        // fallback sintetico de creacion). Se reusa el mismo observer generico y append-only.
        Factura::observe(AuditoriaPiiObserver::class);
        AsientoContable::observe(AuditoriaPiiObserver::class);

        // Inventario — eventos de dominio
        Event::listen(StockMinimoPerforado::class, RegistrarEventoInventarioListener::class);
        Event::listen(LoteVencidoDetectado::class, RegistrarEventoInventarioListener::class);
        Event::listen(TomaFisicaConfirmada::class, RegistrarEventoInventarioListener::class);

        // SII — Rate limiters por empresa (HARDENING-1 R6)
        RateLimiter::for('sii-empresa', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()->empresa_id ?? $request->ip());
        });

        RateLimiter::for('sii-uploads-pesados', function (Request $request) {
            return Limit::perHour(10)->by($request->user()->empresa_id ?? $request->ip());
        });

        // Integraciones: por empresa de la API-key, no por IP (varios terceros pueden compartir
        // IP de datacenter). La key se resuelve DESPUES de este limiter en el pipeline, asi que
        // se identifica por el prefijo del token crudo -> no filtra si aun no hay key valida.
        RateLimiter::for('integraciones-empresa', function (Request $request) {
            $token = $request->bearerToken() ?? $request->header('X-Api-Key');
            $prefijo = is_string($token) ? explode('_', $token, 3)[1] ?? $request->ip() : $request->ip();

            return Limit::perMinute(60)->by($prefijo);
        });

        Gate::define('gestionar-contabilidad-critica', function ($user) {
            $rol = Rol::find($user->rol_id);

            if (! $rol) {
                return false;
            }

            if ($rol->jerarquia >= 80) {
                return true;
            }

            $permisos = $rol->permisos ?? [];

            return in_array('contabilidad.crear', $permisos) ||
                in_array('activos.crear', $permisos);
        });
    }
}
