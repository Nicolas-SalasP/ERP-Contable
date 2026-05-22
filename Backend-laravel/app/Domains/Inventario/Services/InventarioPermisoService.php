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

        $nombreRol = strtolower((string) ($usuario->rol->nombre ?? ''));

        if ($nombreRol === 'administrador') {
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

        $nombreRol = strtolower((string) ($usuario->rol->nombre ?? ''));

        if ($nombreRol === 'administrador') {
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