<?php

namespace App\Domains\Inventario\Services;

use App\Domains\Core\Models\User;
use App\Domains\Core\Support\ModuloPermisos;
use Exception;

class InventarioPermisoService
{
    public function exigir(User $usuario, string $permiso): void
    {
        if ($this->esAdministradorInventario($usuario)) {
            return;
        }

        if (!in_array($permiso, $this->permisosUsuario($usuario), true)) {
            throw new Exception('No tienes permisos para ejecutar esta operación de inventario.');
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

        throw new Exception('No tienes permisos para ejecutar esta operación de inventario.');
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
        $nombreRol = strtolower(trim((string) ($rol->nombre ?? '')));

        return $jerarquia >= 80 || in_array($nombreRol, [
            'administrador',
            'admin',
            'super admin',
            'superadmin',
        ], true);
    }
}
