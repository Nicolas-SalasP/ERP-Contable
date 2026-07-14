<?php

namespace App\Domains\Core\Services;

use App\Domains\Core\Models\User;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Core\Exceptions\CoreException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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

    /**
     * @return string|null Password temporal generada, solo cuando se crea un usuario nuevo
     *   (null si ya existía). El controller debe entregarla al admin que invita para que la
     *   comunique fuera de banda -- no hay flujo de email todavía.
     */
    public function invitarUsuario(int $empresaId, string $email, int $rolId, array $moduleKeysInvitador = []): ?string
    {
        $usuario = User::where('email', $email)->first();

        if ($usuario) {
            $perteneceAOtraEmpresa = $usuario->empresa_id !== null && $usuario->empresa_id !== $empresaId;

            if ($perteneceAOtraEmpresa) {
                // SEGURIDAD: hallazgo de auditoria (secuestro de cuenta). Si el usuario ya
                // vive en otra empresa, invitarlo aqui es un ALTA de acceso multiempresa
                // (via empresa_user), nunca una migracion de "dueno": sobreescribir
                // empresa_id lo desvincularia silenciosamente de su empresa original sin
                // que el ni nadie lo autorice, y sin dejar rastro de esa pertenencia.
                if (!in_array('multitenant', $moduleKeysInvitador, true)) {
                    throw CoreException::regla(
                        'La empresa no cuenta con plan multiempresa: no es posible invitar a un usuario que ya pertenece a otra empresa.'
                    );
                }

                // No se toca empresa_id ni empresa_activa_id: el usuario sigue "viviendo"
                // en su empresa original hasta que el mismo elija cambiar de empresa activa
                // (EmpresaCambioController::cambiar). Solo se le da acceso via el pivote.
            } else {
                $usuario->update([
                    'empresa_id' => $empresaId,
                    'rol_id' => $rolId
                ]);
            }

            DB::table('empresa_user')->updateOrInsert(
                ['user_id' => $usuario->id, 'empresa_id' => $empresaId],
                ['rol_id' => $rolId, 'created_at' => now()]
            );

            return null;
        }

        // Se busca el id del estado 'Activa' en vez de hardcodear 1, para no depender del orden de seeders.
        $estadoActiva = EstadoSuscripcion::where('nombre', 'Activa')->firstOrFail();

        // Password aleatoria por invitación, nunca una constante compartida: con un valor fijo
        // cualquiera que conociera el email invitado podía loguearse antes que el titular real.
        $passwordTemporal = Str::password(16);

        $usuario = User::create([
            'email' => $email,
            'nombre' => 'Usuario Invitado',
            'empresa_id' => $empresaId,
            'rol_id' => $rolId,
            'password' => Hash::make($passwordTemporal),
            'estado_suscripcion_id' => $estadoActiva->id
        ]);

        // Sin esto, empresa_user (usado por EmpresaCambioController para listar/autorizar el
        // cambio de empresa activa) nunca se entera de esta invitación: el usuario invitado
        // jamás podría "volver" a esta empresa si su empresa_id cambia más adelante.
        DB::table('empresa_user')->updateOrInsert(
            ['user_id' => $usuario->id, 'empresa_id' => $empresaId],
            ['rol_id' => $rolId, 'created_at' => now()]
        );

        return $passwordTemporal;
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

        // SEGURIDAD: revocar tokens antes de eliminar, o el usuario podria seguir autenticado hasta que expiren.
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
        // Solo se pueden editar roles propios de la empresa: nunca de sistema (empresa_id null) ni de otra empresa.
        $rol = Rol::where('empresa_id', $empresaId)->findOrFail($rolId);
        $rol->update($datos);

        return $rol;
    }
}