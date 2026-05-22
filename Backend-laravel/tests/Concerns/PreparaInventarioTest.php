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
            'inventario.lotes.ver',
            'inventario.lotes.crear',
            'inventario.lotes.editar',
            'inventario.reservas.ver',
            'inventario.reservas.crear',
            'inventario.reservas.cancelar',
            'inventario.reservas.liberar',
            'inventario.reservas.consumir',
            'inventario.disponibilidad.ver',
            'inventario.tomas_fisicas.ver',
            'inventario.tomas_fisicas.crear',
            'inventario.tomas_fisicas.contar',
            'inventario.tomas_fisicas.cerrar',
            'inventario.tomas_fisicas.ajustar',
            'inventario.tomas_fisicas.cancelar',
            'inventario.alertas.ver',
            'inventario.reglas_reposicion.ver',
            'inventario.reglas_reposicion.crear',
            'inventario.reglas_reposicion.editar',
            'inventario.reglas_reposicion.eliminar',
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
            'inventario.lotes.ver',
            'inventario.reservas.ver',
            'inventario.disponibilidad.ver',
            'inventario.tomas_fisicas.ver',
            'inventario.alertas.ver',
            'inventario.reglas_reposicion.ver',
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

    protected function permisosInventarioLotesCompleto(): array
    {
        return [
            'inventario.lotes.ver',
            'inventario.lotes.crear',
            'inventario.lotes.editar',
        ];
    }

    protected function permisosInventarioLotesLectura(): array
    {
        return [
            'inventario.lotes.ver',
        ];
    }

    protected function permisosInventarioReservasCompleto(): array
    {
        return [
            'inventario.reservas.ver',
            'inventario.reservas.crear',
            'inventario.reservas.cancelar',
            'inventario.reservas.liberar',
            'inventario.reservas.consumir',
            'inventario.disponibilidad.ver',
        ];
    }

    protected function permisosInventarioReservasLectura(): array
    {
        return [
            'inventario.reservas.ver',
            'inventario.disponibilidad.ver',
        ];
    }

    protected function permisosInventarioDisponibilidadLectura(): array
    {
        return [
            'inventario.disponibilidad.ver',
        ];
    }

    protected function permisosInventarioTomasFisicasCompleto(): array
    {
        return [
            'inventario.tomas_fisicas.ver',
            'inventario.tomas_fisicas.crear',
            'inventario.tomas_fisicas.contar',
            'inventario.tomas_fisicas.cerrar',
            'inventario.tomas_fisicas.ajustar',
            'inventario.tomas_fisicas.cancelar',
        ];
    }

    protected function permisosInventarioTomasFisicasLectura(): array
    {
        return [
            'inventario.tomas_fisicas.ver',
        ];
    }

    protected function permisosInventarioReposicionCompleto(): array
    {
        return [
            'inventario.alertas.ver',
            'inventario.reglas_reposicion.ver',
            'inventario.reglas_reposicion.crear',
            'inventario.reglas_reposicion.editar',
            'inventario.reglas_reposicion.eliminar',
            'inventario.productos.ver',
            'inventario.bodegas.ver',
        ];
    }

    protected function permisosInventarioReposicionLectura(): array
    {
        return [
            'inventario.alertas.ver',
            'inventario.reglas_reposicion.ver',
            'inventario.productos.ver',
            'inventario.bodegas.ver',
        ];
    }
}
