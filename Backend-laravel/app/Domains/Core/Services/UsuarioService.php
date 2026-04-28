<?php

namespace App\Domains\Core\Services;

use App\Domains\Core\Models\User;
use App\Domains\Core\Models\Rol;
use Illuminate\Support\Facades\Hash;
use Exception;

class UsuarioService
{
    public function listarUsuarios(int $empresaId)
    {
        return User::where('empresa_id', $empresaId)->get();
    }

    public function listarRoles()
    {
        return Rol::all();
    }

    public function invitarUsuario(int $empresaId, string $email, int $rolId)
    {
        $usuario = User::where('email', $email)->first();

        if ($usuario) {
            $usuario->update([
                'empresa_id' => $empresaId,
                'rol_id' => $rolId
            ]);
        } else {
            User::create([
                'email' => $email,
                'nombre' => 'Usuario Invitado',
                'empresa_id' => $empresaId,
                'rol_id' => $rolId,
                'password' => Hash::make('12345678'),
                'estado_suscripcion_id' => 1
            ]);
        }

        return true;
    }

    public function actualizarRol(int $empresaId, int $usuarioId, int $rolId)
    {
        $usuario = User::where('empresa_id', $empresaId)->findOrFail($usuarioId);
        $usuario->update(['rol_id' => $rolId]);

        return true;
    }

    public function desvincularUsuario(int $empresaId, int $usuarioId)
    {
        $usuario = User::where('empresa_id', $empresaId)->findOrFail($usuarioId);
        $usuario->delete();

        return true;
    }

    public function guardarRol(int $empresaId, array $datos)
    {
        return Rol::create($datos);
    }

    public function actualizarRolPermisos(int $rolId, array $datos)
    {
        $rol = Rol::findOrFail($rolId);
        $rol->update($datos);
        return $rol;
    }
}