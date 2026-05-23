<?php

namespace App\Domains\Inventario\Services;

use App\Domains\Core\Models\User;
use Exception;

class InventarioPermisoService
{
    public function exigir(User $usuario, string $permiso): void
    {
        if (!$usuario->relationLoaded('rol')) {
            $usuario->load('rol');
        }

        if ($this->esAdministradorInventario($usuario)) {
            return;
        }

        $permisos = $this->normalizarPermisos($usuario->rol->permisos ?? []);

        if (!in_array($permiso, $permisos, true)) {
            throw new Exception('No tienes permisos para ejecutar esta operación de inventario.');
        }
    }

    public function exigirAlguno(User $usuario, array $permisosRequeridos): void
    {
        if (!$usuario->relationLoaded('rol')) {
            $usuario->load('rol');
        }

        if ($this->esAdministradorInventario($usuario)) {
            return;
        }

        $permisosUsuario = $this->normalizarPermisos($usuario->rol->permisos ?? []);

        foreach ($permisosRequeridos as $permiso) {
            if (in_array($permiso, $permisosUsuario, true)) {
                return;
            }
        }

        throw new Exception('No tienes permisos para ejecutar esta operación de inventario.');
    }

    private function esAdministradorInventario(User $usuario): bool
    {
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

    private function normalizarPermisos(mixed $permisos): array
    {
        if (is_string($permisos)) {
            $permisos = json_decode($permisos, true) ?: [];
        }

        if (!is_array($permisos)) {
            return [];
        }

        return array_values(array_filter($permisos, static function ($permiso) {
            return is_string($permiso) && trim($permiso) !== '';
        }));
    }
}