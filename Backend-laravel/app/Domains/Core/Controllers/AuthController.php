<?php

namespace App\Domains\Core\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Domains\Core\Models\User;
use Throwable;

class AuthController
{
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email'    => 'required|email',
                'password' => 'required'
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json(['message' => 'Credenciales incorrectas'], 401);
            }
            $user->update([
                'ultimo_acceso' => now()
            ]);

            $permisos = [];
            
            if ($user->rol_id === 1) {
                $permisos = [
                    'ventas.ver', 'ventas.crear', 'clientes.ver', 'clientes.crear',
                    'compras.ver', 'compras.crear', 'proveedores.ver', 'proveedores.crear',
                    'tesoreria.ver', 'tesoreria.crear', 'contabilidad.ver', 'contabilidad.crear',
                    'activos.ver', 'activos.crear', 'tributario.ver', 'tributario.crear',
                    'usuarios.ver', 'usuarios.gestionar'
                ];
            }

            $token = $user->createToken('react-spa-token')->plainTextToken;

            return response()->json([
                'token' => $token,
                'user'  => [
                    'id'         => $user->id,
                    'nombre'     => $user->nombre,
                    'email'      => $user->email,
                    'empresa_id' => $user->empresa_id,
                    'rol_id'     => $user->rol_id,
                    'permisos'   => $permisos
                ]
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Error Interno de Laravel',
                'error'   => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine()
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load(['empresa', 'rol']);
        
        $permisos = [];
        if ($user->rol_id === 1) {
            $permisos = [
                'ventas.ver', 'ventas.crear', 'clientes.ver', 'clientes.crear',
                'compras.ver', 'compras.crear', 'proveedores.ver', 'proveedores.crear',
                'tesoreria.ver', 'tesoreria.crear', 'contabilidad.ver', 'contabilidad.crear',
                'activos.ver', 'activos.crear', 'tributario.ver', 'tributario.crear',
                'usuarios.ver', 'usuarios.gestionar'
            ];
        }

        $userData = $user->toArray();
        $userData['permisos'] = $permisos;

        return response()->json($userData);
    }
}