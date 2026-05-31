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

            // Si las credenciales locales fallan, intentar contra el web page
            // (el usuario puede existir en la web pero no haber sido provisionado aún).
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
                ]);

                $user->load(['rol', 'estadoSuscripcion']);
            }

            // Validar contra el nombre del estado (no contra id hardcodeado).
            // Razon: el id no es estable entre entornos / seeders y puede cambiar.
            if (!$user->estadoSuscripcion || $user->estadoSuscripcion->nombre !== 'Activa') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cuenta inactiva o suspendida.'
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

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();

        if ($token) {
            $token->delete();
        }

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
            if ($tokenActual) {
                $tokenActual->delete();
            }

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
        $user = $request->user()->load(['empresa', 'rol']);

        $permisos = ModuloPermisos::permisosUsuario($user);

        $userData = $user->toArray();
        $userData['permisos'] = $permisos;

        return response()->json($userData);
    }
}