<?php

namespace App\Domains\Core\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Domains\Core\Models\User;
use Illuminate\Validation\ValidationException;
use App\Domains\Core\Services\ProvisionUserService;
use App\Domains\Core\Services\WebAuthClient;
use Illuminate\Support\Facades\DB;
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

            $webResult = $this->webClient->validateLogin($request->email, $request->password);

            if ($webResult !== null) {
                if (!($webResult['valid'] ?? false)) {
                    return response()->json(['success' => false, 'message' => 'Credenciales inválidas'], 401);
                }

                if (!$user) {
                    $provisioned = $this->provisioner->provision([
                        'tenri_user_id' => $webResult['tenri_user_id'],
                        'email'         => $request->email,
                        'name'          => $webResult['name'] ?? $request->email,
                        'rut'           => null,
                        'password_hash' => $webResult['password_hash'],
                        'plan_slug'     => $webResult['plan_slug'] ?? 'erp-starter',
                        'module_keys'   => $webResult['module_keys'] ?? [],
                        'rol_erp'       => $webResult['rol_erp'] ?? 'Administrador',
                    ]);
                    $user = User::with(['rol', 'estadoSuscripcion'])->find($provisioned->id);
                } else {
                    DB::table('usuarios')->where('id', $user->id)->update([
                        'plan_slug'       => $webResult['plan_slug'],
                        'module_keys'     => json_encode($webResult['module_keys'] ?? []),
                        'tenri_synced_at' => now(),
                    ]);
                    if (!empty($webResult['password_hash'])) {
                        DB::table('usuarios')->where('id', $user->id)
                            ->update(['password' => $webResult['password_hash']]);
                    }
                    $user = User::with(['rol', 'estadoSuscripcion'])->find($user->id);
                }
            } else {
                if (!$user || !Hash::check($request->password, $user->password)) {
                    return response()->json(['success' => false, 'message' => 'Credenciales inválidas'], 401);
                }
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

            $permisos = $user->rol->permisos ?? [];

            if ($user->rol && $user->rol->jerarquia >= 100) {
                $permisos = [
                    'ventas.ver',
                    'ventas.crear',
                    'clientes.ver',
                    'clientes.crear',
                    'compras.ver',
                    'compras.crear',
                    'proveedores.ver',
                    'proveedores.crear',
                    'tesoreria.ver',
                    'tesoreria.crear',
                    'contabilidad.ver',
                    'contabilidad.crear',
                    'activos.ver',
                    'activos.crear',
                    'tributario.ver',
                    'tributario.crear',
                    'usuarios.ver',
                    'usuarios.gestionar',
                    'inventario.productos.ver',
                    'inventario.productos.crear',
                    'inventario.productos.editar',
                    'inventario.bodegas.ver',
                    'inventario.bodegas.crear',
                    'inventario.movimientos.ver',
                    'inventario.movimientos.entrada',
                    'inventario.movimientos.salida',
                    'inventario.movimientos.traspaso',
                    'inventario.movimientos.ajuste',
                    'inventario.kardex.ver',
                    'inventario.valorizacion.ver',
                    'inventario.ajustes_criticos.ver',
                    'inventario.ajustes_criticos.crear',
                    'inventario.lotes.ver',
                    'inventario.lotes.crear',
                    'inventario.lotes.editar',
                    'inventario.reservas.ver',
                    'inventario.reservas.crear',
                    'inventario.reservas.cancelar',
                    'inventario.reservas.liberar',
                    'inventario.reservas.consumir',
                    'inventario.disponibilidad.ver',
                    'inventario.tomas_fisicas.ver',
                    'inventario.tomas_fisicas.crear',
                    'inventario.tomas_fisicas.contar',
                    'inventario.tomas_fisicas.cerrar',
                    'inventario.tomas_fisicas.ajustar',
                    'inventario.tomas_fisicas.cancelar',
                ];
            }

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
        $permisos = $user->rol->permisos ?? [];

        if ($user->rol && $user->rol->jerarquia >= 100) {
            $permisos = [
                'ventas.ver',
                'ventas.crear',
                'clientes.ver',
                'clientes.crear',
                'compras.ver',
                'compras.crear',
                'proveedores.ver',
                'proveedores.crear',
                'tesoreria.ver',
                'tesoreria.crear',
                'contabilidad.ver',
                'contabilidad.crear',
                'activos.ver',
                'activos.crear',
                'tributario.ver',
                'tributario.crear',
                'usuarios.ver',
                'usuarios.gestionar',
                'inventario.productos.ver',
                'inventario.productos.crear',
                'inventario.productos.editar',
                'inventario.bodegas.ver',
                'inventario.bodegas.crear',
                'inventario.movimientos.ver',
                'inventario.movimientos.entrada',
                'inventario.movimientos.salida',
                'inventario.movimientos.traspaso',
                'inventario.movimientos.ajuste',
                'inventario.kardex.ver',
                'inventario.valorizacion.ver',
                'inventario.ajustes_criticos.ver',
                'inventario.ajustes_criticos.crear',
                'inventario.lotes.ver',
                'inventario.lotes.crear',
                'inventario.lotes.editar',
                'inventario.reservas.ver',
                'inventario.reservas.crear',
                'inventario.reservas.cancelar',
                'inventario.reservas.liberar',
                'inventario.reservas.consumir',
                'inventario.disponibilidad.ver',
                'inventario.tomas_fisicas.ver',
                'inventario.tomas_fisicas.crear',
                'inventario.tomas_fisicas.contar',
                'inventario.tomas_fisicas.cerrar',
                'inventario.tomas_fisicas.ajustar',
                'inventario.tomas_fisicas.cancelar',
            ];
        }

        $userData = $user->toArray();
        $userData['permisos'] = $permisos;

        return response()->json($userData);
    }

    public function tokenLogin(\Illuminate\Http\Request $request)
    {
        try {
            $request->validate(['sso_token' => 'required|string']);

            $webResult = $this->webClient->validateToken($request->sso_token);

            if (!$webResult) {
                return response()->json(['success' => false, 'message' => 'Token SSO inválido o expirado.'], 401);
            }

            $user = User::with(['rol', 'estadoSuscripcion'])
                ->where('email', $webResult['email'])
                ->first();

            if (!$user) {
                $provisioned = $this->provisioner->provision([
                    'tenri_user_id' => $webResult['tenri_user_id'],
                    'email'         => $webResult['email'],
                    'name'          => $webResult['name'] ?? $webResult['email'],
                    'rut'           => null,
                    'password_hash' => $webResult['password_hash'],
                    'plan_slug'     => $webResult['plan_slug'] ?? 'erp-starter',
                    'module_keys'   => $webResult['module_keys'] ?? [],
                    'rol_erp'       => $webResult['rol_erp'] ?? 'Administrador',
                ]);
                $user = User::with(['rol', 'estadoSuscripcion'])->find($provisioned->id);
            } else {
                DB::table('usuarios')->where('id', $user->id)->update([
                    'plan_slug'       => $webResult['plan_slug'],
                    'module_keys'     => json_encode($webResult['module_keys'] ?? []),
                    'tenri_synced_at' => now(),
                ]);
                $user = $user->fresh(['rol', 'estadoSuscripcion']);
            }

            if (!$user->estadoSuscripcion || $user->estadoSuscripcion->nombre !== 'Activa') {
                return response()->json(['success' => false, 'message' => 'Cuenta inactiva.'], 403);
            }

            $user->update(['ultimo_acceso' => now()]);
            $token = $user->createToken('react-spa-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'token'   => $token,
                'user'    => [
                    'id'         => $user->id,
                    'nombre'     => $user->nombre,
                    'email'      => $user->email,
                    'empresa_id' => $user->empresa_id,
                    'rol_id'     => $user->rol_id,
                    'permisos'   => $user->rol->permisos ?? [],
                    'plan_slug'  => $user->plan_slug,
                    'module_keys' => $user->module_keys ?? [],
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Error en tokenLogin SSO: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error interno.'], 500);
        }
    }
}
