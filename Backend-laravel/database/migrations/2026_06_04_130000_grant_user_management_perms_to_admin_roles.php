<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * H15: declara los permisos de gestión de usuarios en los roles que ya la ejercían
 * de facto (jerarquía >= 80). Necesario ANTES de gatear las rutas de usuarios con
 * permiso:usuarios.* para no romper roles existentes en producción (el seeder solo
 * cubre instalaciones nuevas). Idempotente.
 */
return new class extends Migration
{
    private const PERMISOS = ['usuarios.ver', 'usuarios.gestionar'];

    public function up(): void
    {
        $roles = DB::table('roles')->where('jerarquia', '>=', 80)->get(['id', 'permisos']);

        foreach ($roles as $rol) {
            $permisos = json_decode($rol->permisos ?? '[]', true);
            if (!is_array($permisos)) {
                $permisos = [];
            }

            $merged = array_values(array_unique(array_merge($permisos, self::PERMISOS)));

            // Solo escribe si realmente faltaba alguno (evita writes inútiles).
            if (count($merged) !== count($permisos)) {
                DB::table('roles')->where('id', $rol->id)->update([
                    'permisos' => json_encode($merged),
                ]);
            }
        }
    }

    public function down(): void
    {
        // No reversible con seguridad: no se puede distinguir los permisos que este
        // backfill agregó de los que un rol ya tenía legítimamente (p.ej. Super Admin).
    }
};
