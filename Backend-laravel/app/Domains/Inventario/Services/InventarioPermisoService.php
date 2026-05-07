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

        $permisos = $usuario->rol->permisos ?? [];

        if (is_string($permisos)) {
            $permisos = json_decode($permisos, true) ?: [];
        }

        if (!is_array($permisos)) {
            $permisos = [];
        }

        if (!in_array($permiso, $permisos, true)) {
            throw new Exception('No tienes permisos para ejecutar esta operación de inventario.');
        }
    }
}