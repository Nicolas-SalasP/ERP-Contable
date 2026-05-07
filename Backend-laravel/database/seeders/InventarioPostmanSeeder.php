<?php

namespace Database\Seeders;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class InventarioPostmanSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Seeder opcional para Postman/Demo de Inventario
        |--------------------------------------------------------------------------
        |
        | Este seeder prepara usuarios demo para probar endpoints de Inventario.
        |
        | No crea roles.
        | No asigna permisos.
        | No debe agregarse al DatabaseSeeder.
        | No crea productos, bodegas, stock, lotes, reservas, movimientos
        | ni tomas físicas automáticamente.
        |
        | Para Fase 6, el flujo Postman debe crear datos mediante endpoints:
        | producto -> bodega -> entrada -> reserva -> disponibilidad -> consumo.
        |
        | Para Fase 7, el flujo Postman debe crear datos mediante endpoints:
        | producto -> bodega -> entrada -> toma física -> iniciar -> conteo
        | -> cerrar -> ajustar -> Kardex.
        |
        | La toma física compara contra stock físico, no contra stock disponible.
        | Las reservas activas no deben alterar el snapshot stock_sistema.
        |
        | Uso manual:
        | php artisan db:seed --class=InventarioPostmanSeeder
        |
        */

        if (!app()->environment(['local', 'testing'])) {
            return;
        }

        $empresa = $this->obtenerEmpresaBase();
        $estado = $this->obtenerEstadoActivo();

        $contadorRol = Rol::where('nombre', 'Contador')->firstOrFail();
        $auditorRol = Rol::where('nombre', 'Auditor')->firstOrFail();

        $this->crearOActualizarUsuario(
            email: 'contador@example.com',
            nombre: 'Contador Inventario',
            empresaId: (int) $empresa->id,
            rolId: (int) $contadorRol->id,
            estadoSuscripcionId: (int) $estado->id
        );

        $this->crearOActualizarUsuario(
            email: 'auditor@example.com',
            nombre: 'Auditor Inventario',
            empresaId: (int) $empresa->id,
            rolId: (int) $auditorRol->id,
            estadoSuscripcionId: (int) $estado->id
        );
    }

    private function obtenerEmpresaBase(): Empresa
    {
        return Empresa::firstOrCreate(
            ['rut' => '76999999-9'],
            [
                'razon_social' => 'Empresa Demo Inventario',
            ]
        );
    }

    private function obtenerEstadoActivo(): EstadoSuscripcion
    {
        return EstadoSuscripcion::firstOrCreate([
            'nombre' => 'Activa',
        ]);
    }

    private function crearOActualizarUsuario(
        string $email,
        string $nombre,
        int $empresaId,
        int $rolId,
        int $estadoSuscripcionId
    ): void {
        User::updateOrCreate(
            ['email' => $email],
            [
                'empresa_id' => $empresaId,
                'nombre' => $nombre,
                'password' => Hash::make('password'),
                'rol_id' => $rolId,
                'estado_suscripcion_id' => $estadoSuscripcionId,
            ]
        );
    }
}