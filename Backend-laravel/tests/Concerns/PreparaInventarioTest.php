<?php

namespace Tests\Concerns;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\User;
use Database\Seeders\InventarioPostmanSeeder;

trait PreparaInventarioTest
{
    protected function prepararUsuariosInventarioDemo(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Usuarios demo para tests de Inventario
        |--------------------------------------------------------------------------
        |
        | Este método ejecuta InventarioPostmanSeeder solo dentro del test.
        |
        | No se agrega InventarioPostmanSeeder al DatabaseSeeder global.
        | No crea roles.
        | No asigna permisos.
        |
        | Sirve para disponer de:
        | - contador@example.com
        | - auditor@example.com
        |
        */
        $this->seed(InventarioPostmanSeeder::class);
    }

    protected function usuarioAdministradorSeeder(): array
    {
        $usuario = User::with('rol')
            ->where('email', 'admin@tenri.cl')
            ->firstOrFail();

        $empresa = Empresa::findOrFail($usuario->empresa_id);

        return [$empresa, $usuario];
    }

    protected function usuarioContadorConPermisos(array $permisos): array
    {
        return $this->usuarioConRolYPermisos(
            email: 'contador@example.com',
            nombreRol: 'Contador',
            permisos: $permisos
        );
    }

    protected function usuarioAuditorConPermisos(array $permisos): array
    {
        return $this->usuarioConRolYPermisos(
            email: 'auditor@example.com',
            nombreRol: 'Auditor',
            permisos: $permisos
        );
    }

    protected function usuarioConRolYPermisos(
        string $email,
        string $nombreRol,
        array $permisos
    ): array {
        /*
        |--------------------------------------------------------------------------
        | Simula el gestor visual de roles
        |--------------------------------------------------------------------------
        |
        | En producción/demo los permisos se asignan desde GestionRoles.jsx.
        | En tests actualizamos roles.permisos para preparar cada escenario.
        |
        | Esto NO significa que Inventario gestione roles.
        | Solo prepara el contexto de prueba.
        |
        */
        $rol = Rol::where('nombre', $nombreRol)->firstOrFail();

        $rol->update([
            'permisos' => array_values(array_unique($permisos)),
        ]);

        $usuario = User::with('rol')
            ->where('email', $email)
            ->firstOrFail();

        $empresa = Empresa::findOrFail($usuario->empresa_id);

        return [$empresa, $usuario];
    }

    protected function permisosInventarioOperador(): array
    {
        return [
            'inventario.productos.ver',
            'inventario.productos.crear',
            'inventario.productos.editar',

            'inventario.bodegas.ver',
            'inventario.bodegas.crear',

            'inventario.movimientos.ver',
            'inventario.movimientos.entrada',
            'inventario.movimientos.salida',
            'inventario.movimientos.traspaso',
            'inventario.movimientos.ajuste',

            'inventario.kardex.ver',
            'inventario.valorizacion.ver',

            'inventario.ajustes_criticos.ver',
            'inventario.ajustes_criticos.crear',
        ];
    }

    protected function permisosInventarioAuditor(): array
    {
        return [
            'inventario.productos.ver',
            'inventario.bodegas.ver',
            'inventario.movimientos.ver',
            'inventario.kardex.ver',
            'inventario.valorizacion.ver',
            'inventario.ajustes_criticos.ver',
        ];
    }

    protected function permisosInventarioAjustesCriticosCompleto(): array
    {
        return [
            'inventario.ajustes_criticos.ver',
            'inventario.ajustes_criticos.crear',
        ];
    }

    protected function permisosInventarioAjustesCriticosLectura(): array
    {
        return [
            'inventario.ajustes_criticos.ver',
        ];
    }
}