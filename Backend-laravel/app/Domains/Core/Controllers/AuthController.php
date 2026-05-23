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

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Credenciales incorrectas'
                ], 401);
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
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Sesión cerrada correctamente']);
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