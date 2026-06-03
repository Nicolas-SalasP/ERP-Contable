<?php

namespace App\Domains\Core\Services;

use App\Domains\Core\Models\User;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\EstadoSuscripcion;
use Illuminate\Support\Facades\Hash;
use Exception;

class UsuarioService
{
    public function listarUsuarios(int $empresaId)
    {
        return User::where('empresa_id', $empresaId)->get();
    }

    public function listarRoles(int $empresaId)
    {
        // Roles de sistema (compartidos) + roles propios de la empresa.
        return Rol::visiblesPara($empresaId)->get();
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
            // Buscamos el id del estado 'Activa' en vez de hardcodear 1.
            // Esto previene fallos si el orden de seeders cambia.
            $estadoActiva = EstadoSuscripcion::where('nombre', 'Activa')->firstOrFail();

            User::create([
                'email' => $email,
                'nombre' => 'Usuario Invitado',
                'empresa_id' => $empresaId,
                'rol_id' => $rolId,
                'password' => Hash::make('12345678'),
                'estado_suscripcion_id' => $estadoActiva->id
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

        // SEGURIDAD: revocar todos los tokens del usuario antes de eliminarlo.
        // Sin esto, un usuario eliminado podria seguir usando su token activo
        // hasta que expire.
        $usuario->tokens()->delete();

        $usuario->delete();

        return true;
    }

    public function guardarRol(int $empresaId, array $datos)
    {
        // Los roles creados por una empresa quedan ligados a ella (nunca de sistema).
        $datos['empresa_id'] = $empresaId;

        return Rol::create($datos);
    }

    public function actualizarRolPermisos(int $empresaId, int $rolId, array $datos)
    {
        // Solo se pueden editar roles propios de la empresa: nunca roles de
        // sistema (empresa_id null) ni de otra empresa.
        $rol = Rol::where('empresa_id', $empresaId)->findOrFail($rolId);
        $rol->update($datos);

        return $rol;
    }
}