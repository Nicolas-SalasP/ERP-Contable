<?php

namespace App\Domains\Core\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Domains\Core\Models\User;
use Illuminate\Validation\ValidationException;
use App\Domains\Core\Services\ProvisionUserService;
use App\Domains\Core\Services\WebAuthClient;
use App\Domains\Core\Support\ModuloPermisos;
use Illuminate\Support\Carbon;
use Throwable;

class AuthController
{
    public function __construct(
        private readonly WebAuthClient $webClient,
        private readonly ProvisionUserService $provisioner,
    ) {
    }

    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required'
            ]);

            $user = User::with(['rol', 'estadoSuscripcion'])->where('email', $request->email)->first();

            $credencialesLocalesValidas = $user && Hash::check($request->password, $user->password);

            // Si fallan las credenciales locales, se intenta contra la web (puede existir ahí sin haber sido provisionado aún).
            if (!$credencialesLocalesValidas) {
                $webResult = $this->webClient->validateLogin($request->email, $request->password);

                if (!$webResult || !($webResult['valid'] ?? false)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Credenciales incorrectas'
                    ], 401);
                }

                // Provisionar / actualizar el usuario desde la web
                $user = $this->provisioner->provision([
                    'tenri_user_id' => $webResult['tenri_user_id'],
                    'email'         => $webResult['email'],
                    'name'          => $webResult['name'],
                    'password_hash' => $webResult['password_hash'],
                    'plan_slug'     => $webResult['plan_slug'],
                    'module_keys'   => $webResult['module_keys'],
                    'rol_erp'       => $webResult['rol_erp'],
                    'subscription_status'  => $webResult['subscription_status'] ?? 'active',
                    'subscription_ends_at' => $webResult['subscription_ends_at'] ?? null,
                ]);

                $user->load(['rol', 'estadoSuscripcion']);
            }

            // Credenciales locales validas pero cache de suscripcion viejo (>1h): re-sincroniza antes de los guards.
            if ($credencialesLocalesValidas
                && (!$user->tenri_synced_at || Carbon::parse($user->tenri_synced_at)->diffInHours(now()) >= 1)) {
                $webResult = $this->webClient->validateLogin($request->email, $request->password);
                if ($webResult && ($webResult['valid'] ?? false)) {
                    $user = $this->provisioner->provision($webResult);
                    $user->load(['rol', 'estadoSuscripcion']);
                }
            }

            // Se valida por nombre del estado, no por id hardcodeado (el id no es estable entre entornos/seeders).
            if (!$user->estadoSuscripcion || $user->estadoSuscripcion->nombre !== 'Activa') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cuenta inactiva o suspendida.'
                ], 403);
            }

            if ($user->empresa_id !== null) {
                $empresa = $user->empresa()->first();
                if ($empresa && (bool) $empresa->activa === false) {
                    return response()->json([
                        'success' => false,
                        'message' => 'La empresa se encuentra suspendida. Contacte al administrador.'
                    ], 403);
                }
            }

            if ($user->bloqueado_hasta !== null
                && Carbon::parse($user->bloqueado_hasta)->isFuture()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario bloqueado temporalmente.'
                ], 403);
            }

            if ($user->subscription_status === 'expired') {
                return response()->json([
                    'success' => false,
                    'message' => 'Tu suscripción venció. Renueva tu plan en tenri.cl para volver a ingresar.'
                ], 403);
            }

            $user->update(['ultimo_acceso' => now()]);

            $permisos = ModuloPermisos::permisosUsuario($user);

            $token = $user->createToken('react-spa-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'nombre' => $user->nombre,
                    'email' => $user->email,
                    'empresa_id' => $user->empresa_id,
                    'rol_id' => $user->rol_id,
                    'plan_slug' => $user->plan_slug,
                    'permisos' => $permisos
                ]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (Throwable $e) {
            Log::error('Error en Login: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error Interno del Servidor. Inténtelo más tarde.'
            ], 500);
        }
    }

    /** Acceso por token SSO de un solo uso: valida contra la web, provisiona/actualiza el usuario y entrega token ERP (mismos guards que login normal). */
    public function tokenLogin(Request $request)
    {
        try {
            $request->validate(['sso_token' => 'required|string']);

            $webResult = $this->webClient->validateSsoToken($request->sso_token);

            if (!$webResult || !($webResult['valid'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token SSO inválido o expirado.',
                ], 401);
            }

            $user = $this->provisioner->provision([
                'tenri_user_id'        => $webResult['tenri_user_id'],
                'email'                => $webResult['email'],
                'name'                 => $webResult['name'],
                'password_hash'        => $webResult['password_hash'],
                'plan_slug'            => $webResult['plan_slug'],
                'module_keys'          => $webResult['module_keys'],
                'rol_erp'              => $webResult['rol_erp'],
                'subscription_status'  => $webResult['subscription_status'] ?? 'active',
                'subscription_ends_at' => $webResult['subscription_ends_at'] ?? null,
            ]);
            $user->load(['rol', 'estadoSuscripcion']);

            if (!$user->estadoSuscripcion || $user->estadoSuscripcion->nombre !== 'Activa') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cuenta inactiva o suspendida.',
                ], 403);
            }

            if ($user->subscription_status === 'expired') {
                return response()->json([
                    'success' => false,
                    'message' => 'Tu suscripción venció. Renueva tu plan en tenri.cl para volver a ingresar.',
                ], 403);
            }

            if ($user->empresa_id !== null) {
                $empresa = $user->empresa()->first();
                if ($empresa && (bool) $empresa->activa === false) {
                    return response()->json([
                        'success' => false,
                        'message' => 'La empresa se encuentra suspendida. Contacte al administrador.',
                    ], 403);
                }
            }

            if ($user->bloqueado_hasta !== null
                && Carbon::parse($user->bloqueado_hasta)->isFuture()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario bloqueado temporalmente.',
                ], 403);
            }

            $user->update(['ultimo_acceso' => now()]);

            $permisos = ModuloPermisos::permisosUsuario($user);
            $token = $user->createToken('react-spa-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'token' => $token,
                'issued_at' => now()->toIso8601String(),
                'user' => [
                    'id' => $user->id,
                    'nombre' => $user->nombre,
                    'email' => $user->email,
                    'empresa_id' => $user->empresa_id,
                    'rol_id' => $user->rol_id,
                    'plan_slug' => $user->plan_slug,
                    'permisos' => $permisos,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('Error en tokenLogin SSO: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error Interno del Servidor. Inténtelo más tarde.',
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sesion cerrada correctamente',
        ]);
    }

    public function refresh(Request $request)
    {
        try {
            $user = $request->user();
            $tokenActual = $request->user()->currentAccessToken();
            $nuevoToken = $user->createToken('react-spa-token')->plainTextToken;
            $tokenActual->delete();

            return response()->json([
                'success' => true,
                'token' => $nuevoToken,
                'issued_at' => now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            Log::error('Error en refresh token: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'No se pudo refrescar el token. Inicia sesion nuevamente.'
            ], 500);
        }
    }

    public function me(Request $request)
    {
        $user = $request->user()->load('rol');

        $permisos = ModuloPermisos::permisosUsuario($user);

        // Whitelist (misma forma que el login): no se expone toArray() completo del usuario.
        return response()->json([
            'id'                   => $user->id,
            'nombre'               => $user->nombre,
            'email'                => $user->email,
            'empresa_id'           => $user->empresa_id,
            'empresa_activa_id'    => $user->empresa_activa_id,
            'rol_id'               => $user->rol_id,
            'plan_slug'            => $user->plan_slug,
            'module_keys'          => $user->module_keys ?? [],
            'subscription_status'  => $user->subscription_status,
            'subscription_ends_at' => $user->subscription_ends_at,
            'permisos'             => $permisos,
            'empresas_count'       => $user->empresas()->count(),
        ]);
    }
}