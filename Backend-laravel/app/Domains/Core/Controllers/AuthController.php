<?php

namespace App\Domains\Core\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Domains\Core\Models\User;

class AuthController
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            if ($user) {
                $user->increment('intentos_fallidos');
            }
            return response()->json(['message' => 'Credenciales incorrectas'], 401);
        }

        if ($user->bloqueado_hasta && $user->bloqueado_hasta > now()) {
            return response()->json(['message' => 'Usuario bloqueado temporalmente'], 403);
        }

        $user->update(['intentos_fallidos' => 0]);
        $token = $user->createToken('react-spa-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'         => $user->id,
                'nombre'     => $user->nombre,
                'email'      => $user->email,
                'empresa_id' => $user->empresa_id,
                'rol_id'     => $user->rol_id
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user()->load(['empresa', 'rol']));
    }
}