<?php

namespace App\Domains\Inventario\Services;

use App\Domains\Inventario\Exceptions\InventarioException;

use App\Domains\Core\Models\User;
use App\Domains\Core\Support\ModuloPermisos;

class InventarioPermisoService
{
    public function exigir(User $usuario, string $permiso): void
    {
        if ($this->esAdministradorInventario($usuario)) {
            return;
        }

        if (!in_array($permiso, $this->permisosUsuario($usuario), true)) {
            throw InventarioException::regla('No tienes permisos para ejecutar esta operación de inventario.');
        }
    }

    public function exigirAlguno(User $usuario, array $permisosRequeridos): void
    {
        if ($this->esAdministradorInventario($usuario)) {
            return;
        }

        $permisosUsuario = $this->permisosUsuario($usuario);

        foreach ($permisosRequeridos as $permiso) {
            if (in_array($permiso, $permisosUsuario, true)) {
                return;
            }
        }

        throw InventarioException::regla('No tienes permisos para ejecutar esta operación de inventario.');
    }

    public function tiene(User $usuario, string $permiso): bool
    {
        if ($this->esAdministradorInventario($usuario)) {
            return true;
        }

        return in_array($permiso, $this->permisosUsuario($usuario), true);
    }

    private function permisosUsuario(User $usuario): array
    {
        return ModuloPermisos::permisosUsuario($usuario);
    }

    private function esAdministradorInventario(User $usuario): bool
    {
        $usuario->loadMissing('rol');
        $rol = $usuario->rol;

        if (!$rol) {
            return false;
        }

        $jerarquia = (int) ($rol->jerarquia ?? 0);

        // SuperAdmin / staff interno: siempre exento.
        if ($jerarquia >= 100) {
            return true;
        }

        // Usuario con plan (module_keys): el techo del plan manda, sin atajo de admin.
        if (!empty($usuario->module_keys)) {
            return false;
        }

        // Admin local sin plan: mantiene el atajo historico.
        $nombreRol = strtolower(trim((string) ($rol->nombre ?? '')));

        return $jerarquia >= 80 || in_array($nombreRol, [
            'administrador',
            'admin',
            'super admin',
            'superadmin',
        ], true);
    }
}
